<?php
class SessionOn {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function findPlayers($player_uuid) {
        // Look for a match with empty slots
        $stmt = $this->conn->prepare('
            SELECT * FROM matching 
            WHERE status = "waiting" 
            AND (player1_uuid IS NULL OR player2_uuid IS NULL OR player3_uuid IS NULL OR player4_uuid IS NULL)
            ORDER BY id ASC
        ');
        $stmt->execute();
        $result = $stmt->get_result();
        $matches = array();

        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }

        foreach ($matches as $match) {
            if ($this->addPlayerToMatch($match['id'], $player_uuid)) {
                return true;
            }
        }

        // If no match is available, create a new match
        $players = $this->getLowestPingPlayers(4);
        if (count($players) === 4) {
            $this->createMatch($players);
            return true;
        } else {
            return false;
        }
    }

    private function addPlayerToMatch($match_id, $player_uuid) {
        $stmt = $this->conn->prepare('
            UPDATE matching 
            SET player1_uuid = IF(player1_uuid IS NULL, ?, player1_uuid), 
                player2_uuid = IF(player2_uuid IS NULL, ?, player2_uuid), 
                player3_uuid = IF(player3_uuid IS NULL, ?, player3_uuid), 
                player4_uuid = IF(player4_uuid IS NULL, ?, player4_uuid) 
            WHERE id = ?
        ');
        $stmt->bind_param('ssssi', $player_uuid, $player_uuid, $player_uuid, $player_uuid, $match_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    private function createMatch($players) {
        $player_uuids = array_column($players, 'uuid');
        sort($player_uuids); 
        $player_key = implode(',', $player_uuids);
        
        $stmt = $this->conn->prepare('
            SELECT id FROM matching 
            WHERE player1_uuid = ? AND player2_uuid = ? AND player3_uuid = ? AND player4_uuid = ? 
            AND status = "waiting"
        ');
        $stmt->bind_param('ssss', ...$player_uuids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $host_uuid = $players[0]['uuid']; // The player with the lowest ping is the host
            $player1_uuid = $players[1]['uuid'];
            $player2_uuid = $players[2]['uuid'];
            $player3_uuid = $players[3]['uuid'];
        
            $stmt = $this->conn->prepare('
                INSERT INTO matching (player1_uuid, player2_uuid, player3_uuid, player4_uuid, status) 
                VALUES (?, ?, ?, ?, "waiting")
            ');
            $stmt->bind_param('ssss', $host_uuid, $player1_uuid, $player2_uuid, $player3_uuid);
            $stmt->execute();
            $this->updateWaitingList($players);
        }
    }

    private function updateWaitingList($players) {
        $uuids = array_column($players, 'uuid');
        $stmt = $this->conn->prepare('
            UPDATE waiting_list 
            SET status = "in_game" 
            WHERE uuid IN (' . implode(',', array_fill(0, count($uuids), '?')) . ')'
        );
        $stmt->bind_param(str_repeat('s', count($uuids)), ...$uuids);
        $stmt->execute();
    }

    public function removeInactivePlayers() {
        $stmt = $this->conn->prepare('
            DELETE FROM waiting_list 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 MINUTE)'
        );
        $stmt->execute();
    }

    public function removePlayersFromWaitingList($playerIds) {
        $stmt = $this->conn->prepare('
            DELETE FROM waiting_list 
            WHERE uuid IN (' . implode(',', array_fill(0, count($playerIds), '?')) . ')'
        );
        $stmt->bind_param(str_repeat('s', count($playerIds)), ...$playerIds);
        $stmt->execute();
    }

    public function getLowestPingPlayers($numPlayers) {
        $poolSize = $numPlayers * 2;
        $stmt = $this->conn->prepare('
            SELECT * FROM waiting_list 
            ORDER BY ping ASC 
            LIMIT ?'
        );
        $stmt->bind_param('i', $poolSize);
        $stmt->execute();
        $result = $stmt->get_result();
        $players = array();
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }

        $selectedPlayers = array_slice($players, 0, $numPlayers);
        return $selectedPlayers;
    }
}
?>

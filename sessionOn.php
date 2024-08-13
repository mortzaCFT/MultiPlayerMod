<?php
class SessionOn {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function findPlayers($player_uuid) {
        $stmt = $this->conn->prepare('SELECT uuid, ping FROM waiting_list WHERE status = "waiting" ORDER BY ping ASC LIMIT 4');
        $stmt->execute();
        $result = $stmt->get_result();
        $players = array();
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }
        if (count($players) == 4) {
            $this->createMatch($players);
        }
    }


    private function createMatch($players) {
        $host_uuid = $players[0]['uuid']; // The player with the lowest ping is the host
        $player1_uuid = $players[1]['uuid'];
        $player2_uuid = $players[2]['uuid'];
        $player3_uuid = $players[3]['uuid'];

        $stmt = $this->conn->prepare('INSERT INTO matching (player1_uuid, player2_uuid, player3_uuid, player4_uuid) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $host_uuid, $player1_uuid, $player2_uuid, $player3_uuid);
        $stmt->execute();
        $this->updateWaitingList($players);
    }

    private function updateWaitingList($players) {
        $uuids = array_column($players, 'uuid');
        $stmt = $this->conn->prepare('UPDATE waiting_list SET status = "in_game" WHERE uuid IN (' . implode(',', array_fill(0, count($uuids), '?')) . ')');
        $stmt->bind_param(str_repeat('s', count($uuids)), ...$uuids);
        $stmt->execute();
    }

    public function removeInactivePlayers() {
        $stmt = $this->conn->prepare('DELETE FROM waiting_list WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
        $stmt->execute();
    }
    public function removePlayersFromWaitingList($playerIds) {
        // Update the waiting list to remove the selected players
        $stmt = $this->conn->prepare('DELETE FROM waiting_list WHERE uuid IN (' . implode(',', array_fill(0, count($playerIds), '?')) . ')');
        $stmt->bind_param(str_repeat('s', count($playerIds)), ...$playerIds);
        $stmt->execute();
    }
    public function getLowestPingPlayers($numPlayers) {
        // Get a larger pool of players with low ping
        $poolSize = $numPlayers * 2; // e.g., 8-10 players
        $stmt = $this->conn->prepare('SELECT * FROM waiting_list ORDER BY ping ASC LIMIT ?');
        $stmt->bind_param('i', $poolSize);
        $stmt->execute();
        $result = $stmt->get_result();
        $players = array();
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }

        // Randomly select $numPlayers from the pool
        $selectedPlayers = array_slice($players, 0, $numPlayers);
        return $selectedPlayers;
    }
}
/*The code should now:

Find 4 players with the lowest ping and create a match
Update the waiting list to set the status of the selected players to "in_game"
Remove inactive players from the waiting list
Remove selected players from the waiting list
Get a pool of players with low ping and randomly select a subset of them
*/
?>

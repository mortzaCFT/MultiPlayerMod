<?php
include 'config.php';
include 'sessionOn.php';
include 'sessionEnd.php';

class Session {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function createMatchingTable() {
        $stmt = $this->conn->prepare('CREATE TABLE IF NOT EXISTS matching (
            id INT AUTO_INCREMENT,
            player1_uuid VARCHAR(255) NOT NULL,
            player2_uuid VARCHAR(255) NOT NULL,
            player3_uuid VARCHAR(255) NOT NULL,
            player4_uuid VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "in_progress",
            start_time INT NOT NULL DEFAULT UNIX_TIMESTAMP(),
            PRIMARY KEY (id)
        )');
        $stmt->execute();
    }

    public function startSession($player_uuid) {
        $this->createMatchingTable();
        $sessionOn = new SessionOn($this->conn);
        if ($sessionOn->findPlayers($player_uuid)) {
            return true;
        } else {
            return false;
        }
    }

    public function endSession($player_uuid) {
        $sessionEnd = new SessionEnd($this->conn);
        $sessionEnd->endGame($player_uuid, 'OnEnd');
    }

    public function endGameAndCleanup($player_uuid, $result_status) {
        // End the session and process match results
        $this->endSession($player_uuid);
        $this->cleanupOldMatches($player_uuid);
    }

    public function updateMatchStatus($player_uuid, $status) {
        if ($status === 'OnHostStart') {
            $this->updateMatchStatusByRole($player_uuid, 'host');
        } elseif ($status === 'OnPlayerStart') {
            $this->updateMatchStatusByRole($player_uuid, 'player');
        }
    }

    public function updateMatchStatusByRole($player_uuid, $role) {
        $stmt = $this->conn->prepare('UPDATE matching SET status = ? WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $role, $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();
    }

    public function getGameInfo($player_uuid) {
        $stmt = $this->conn->prepare('SELECT * FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();
        $result = $stmt->get_result();
        $gameInfo = array();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $gameInfo['host'] = $row['player1_uuid'];
            $gameInfo['player1'] = $row['player2_uuid'];
            $gameInfo['player2'] = $row['player3_uuid'];
            $gameInfo['player3'] = $row['player4_uuid'];
        }
        return $gameInfo;
    }

    public function getMatchInfo($player_uuid) {
        $gameInfo = $this->getGameInfo($player_uuid);
        if (!empty($gameInfo)) {
            $stmt = $this->conn->prepare('SELECT id, player1_uuid, player2_uuid, player3_uuid, player4_uuid FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
            $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $match_id = $row['id'];
                $players = [
                    $row['player1_uuid'] => 'host',
                    $row['player2_uuid'] => 'player1',
                    $row['player3_uuid'] => 'player2',
                    $row['player4_uuid'] => 'player3'
                ];
                $player_role = $players[$player_uuid] ?? 'not_in_match';
                return [
                    'match_id' => $match_id,
                    'players' => $players,
                    'player_role' => $player_role
                ];
            }
        }
        return [];
    }

    public function cleanupOldMatches($player_uuid) {
        $sessionEnd = new SessionEnd($this->conn);
        $sessionEnd->checkAndRemoveOldMatches($player_uuid);
    }
}
?>

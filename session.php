<?php
include 'config.php';
include 'sessionOn.php';
include 'sessionEnd.php';

class Session {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->createMatchingTable();
    }

    public function createMatchingTable() {
        $createTableSql = '
            CREATE TABLE IF NOT EXISTS matching (
                id INT AUTO_INCREMENT,
                player1_uuid VARCHAR(255) NOT NULL,
                player2_uuid VARCHAR(255) NOT NULL,
                player3_uuid VARCHAR(255) NOT NULL,
                player4_uuid VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "in_progress",
                start_time INT NOT NULL DEFAULT UNIX_TIMESTAMP(),
                host_ping INT DEFAULT NULL,
                PRIMARY KEY (id)
            )
        ';
        
        $stmt = $this->conn->prepare($createTableSql);
        
        if ($stmt === false) {
            error_log('Failed to prepare CREATE TABLE statement: ' . $this->conn->error);
            return;
        }
        
        $stmt->execute();
        $stmt->close();
    }

    public function startSession($player_uuid) {
        $this->cleanupOldMatches($player_uuid);
        $sessionOn = new SessionOn($this->conn);
        return $sessionOn->findPlayers($player_uuid);
    }

    public function endSession($player_uuid) {
        $sessionEnd = new SessionEnd($this->conn);
        $sessionEnd->endGame($player_uuid, 'OnEnd');
    }

    public function endGameAndCleanup($player_uuid, $result_status) {
        $this->endSession($player_uuid);
        $this->cleanupOldMatches($player_uuid);
    }

    public function updateMatchStatus($player_uuid, $status) {
        $role = $status === 'OnHostStart' ? 'host' : ($status === 'OnPlayerStart' ? 'player' : 'in_progress');
        $this->updateMatchStatusByRole($player_uuid, $role);
    }

    public function updateMatchStatusByRole($player_uuid, $status) {
        $new_status = $status === 'host' ? 'starting_match' : $status;
        
        $stmt = $this->conn->prepare('
            UPDATE matching 
            SET status = ?, host_ping = ? 
            WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?
        ');
        
        if ($stmt === false) {
            error_log('Failed to prepare UPDATE statement: ' . $this->conn->error);
            return;
        }

        $host_ping = ($status === 'host') ? time() : null;
        $stmt->bind_param('sissss', $new_status, $host_ping, $player_uuid, $player_uuid, $player_uuid, $player_uuid);

        
        if ($stmt->execute() === false) {
            error_log('Failed to execute UPDATE statement: ' . $stmt->error);
        }

        $stmt->close();
    }

    public function getGameInfo($player_uuid) {
        $stmt = $this->conn->prepare('
            SELECT * FROM matching 
            WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?
        ');
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
            $gameInfo['host_ping'] = $row['host_ping'];
        }
        $stmt->close();
        return $gameInfo;
    }

    public function getMatchInfo($player_uuid) {
        $gameInfo = $this->getGameInfo($player_uuid);
        if (!empty($gameInfo)) {
            $stmt = $this->conn->prepare('
                SELECT id, player1_uuid, player2_uuid, player3_uuid, player4_uuid 
                FROM matching 
                WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?
            ');
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
                $stmt->close();
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

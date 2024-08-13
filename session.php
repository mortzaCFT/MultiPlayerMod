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
            PRIMARY KEY (id)
        )');
        $stmt->execute();
    }

    public function startSession($player_uuid) {
        $this->createMatchingTable();
        $sessionOn = new SessionOn($this->conn);
        $sessionOn->findPlayers($player_uuid);
    }

    public function endSession($player_uuid) {
        $sessionEnd = new SessionEnd($this->conn);
        $sessionEnd->endGame($player_uuid);
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
            $stmt = $this->conn->prepare('SELECT id FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
            $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
            $stmt->execute();
            $result = $stmt->get_result();
            $match_id = null;
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $match_id = $row['id'];
            }
            return array('match_id' => $match_id, 'host' => $gameInfo['host'], 'player1' => $gameInfo['player1'], 'player2' => $gameInfo['player2'], 'player3' => $gameInfo['player3']);
        } else {
            return array();
        }
    }
}
//This file controls session using two sessionOn.php and sessionEnd.php.
?>

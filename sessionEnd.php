<?php
class SessionEnd {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function endGame($player_uuid) {
        $stmt = $this->conn->prepare('UPDATE matching SET status = "ended" WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();
        $this->removePlayersFromMatching($player_uuid);
    }

    private function removePlayersFromMatching($player_uuid) {
        $stmt = $this->conn->prepare('DELETE FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();
    }
}
?>

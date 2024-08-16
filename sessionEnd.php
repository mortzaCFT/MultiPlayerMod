<?php
class SessionEnd {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function endGame($player_uuid, $result_status) {
        $stmt = $this->conn->prepare('UPDATE matching SET status = "ended" WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();

        if ($result_status === 'OnWinnerEnd' || $result_status === 'OnLoserEnd') {
            $this->processMatchResults($player_uuid, $result_status);
        }

        $this->removePlayersFromMatching($player_uuid);
        $this->checkAndRemoveOldMatches($player_uuid);
    }
//--------------------------------------------------------------------------------------------------------------------------
private function processMatchResults($player_uuid, $result_status) {
    // 1. Get Match Information
    $stmt = $this->conn->prepare('SELECT * FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
    $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $match = $result->fetch_assoc();

    if (!$match) {
        throw new Exception('Match not found');
    }

    // 2. Calculate Total Wagered Amount
    $player_uuids = [$match['player1_uuid'], $match['player2_uuid'], $match['player3_uuid'], $match['player4_uuid']];
    $total_wagered = 0;
    foreach ($player_uuids as $uuid) {
        $stmt = $this->conn->prepare('SELECT gamble_amount FROM waiting_list WHERE uuid = ?');
        $stmt->bind_param('s', $uuid);
        $stmt->execute();
        $player_result = $stmt->get_result()->fetch_assoc();
        $total_wagered += $player_result['gamble_amount'];
    }

    // 3. Calculate Fees and Distribute Winnings
    $fee_percentage = 0.20;
    $reward_percentage = 0.80;
    $total_fee = $total_wagered * $fee_percentage;
    $total_reward = $total_wagered - $total_fee;

    $winners = [];
    $losers = [];

    // Determine winner and loser(s)
    if ($result_status === 'OnWinnerEnd') {
        $winners[] = $player_uuid; // Assuming player_uuid is the winner
    } else if ($result_status === 'OnLoserEnd') {
        $losers[] = $player_uuid; // Assuming player_uuid is a loser
    }

    // Distribute winnings
    if (!empty($winners)) {
        $winner_amount = $total_reward / count($winners);
        foreach ($winners as $winner_uuid) {
            $this->updatePlayerScore($winner_uuid, $winner_amount);
        }
    }

    // Distribute the fee to a specific UUID
    $fee_recipient_uuid = '38f18214'; // Hardcoded UUID for the fee
    $stmt = $this->conn->prepare('UPDATE waiting_list SET score = score + ? WHERE uuid = ?');
    $stmt->bind_param('ds', $total_fee, $fee_recipient_uuid);
    $stmt->execute();

    // Update player status
    $stmt = $this->conn->prepare('UPDATE waiting_list SET match_result = ? WHERE uuid = ?');
    $stmt->bind_param('ss', $result_status, $player_uuid);
    $stmt->execute();

    // Additional logic for losers
    if (!empty($losers)) {
        foreach ($losers as $loser_uuid) {
            $this->updatePlayerScore($loser_uuid, 0); // Losers get nothing
        }
    }
}

private function updatePlayerScore($player_uuid, $amount) {
    $stmt = $this->conn->prepare('UPDATE waiting_list SET score = score + ? WHERE uuid = ?');
    $stmt->bind_param('ds', $amount, $player_uuid);
    $stmt->execute();
}
//--------------------------------------------------------------------------------------------------------------------------    

    private function removePlayersFromMatching($player_uuid) {
        $stmt = $this->conn->prepare('DELETE FROM matching WHERE player1_uuid = ? OR player2_uuid = ? OR player3_uuid = ? OR player4_uuid = ?');
        $stmt->bind_param('ssss', $player_uuid, $player_uuid, $player_uuid, $player_uuid);
        $stmt->execute();
    }

    public function checkAndRemoveOldMatches() {
        $stmt = $this->conn->prepare('SELECT * FROM matching WHERE status = "in_progress" AND (UNIX_TIMESTAMP() - start_time >= 180)');
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $match_id = $row['id'];
            $stmt = $this->conn->prepare('DELETE FROM matching WHERE id = ?');
            $stmt->bind_param('i', $match_id);
            $stmt->execute();
        }
    }
    
}
?>

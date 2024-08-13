<?php
function createWaitingListTable($conn) {
    $stmt = $conn->prepare('CREATE TABLE IF NOT EXISTS waiting_list (
        id INT AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        gamble_amount INT NOT NULL,
        uuid VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "waiting",
        ping INT NOT NULL,
        last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )');
    $stmt->execute();
}

function addPlayerToWaitingRoom($conn, $player_uuid, $player_name, $gamble_amount, $player_ping) {
    createWaitingListTable($conn);
    $stmt = $conn->prepare('INSERT INTO waiting_list (name, gamble_amount, uuid, status, ping) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('siiss', $player_name, $gamble_amount, $player_uuid, 'waiting', $player_ping);
    $stmt->execute();
}

function getGameStatus($conn) {
    $stmt = $conn->prepare('SELECT COUNT(id) as total_players, GROUP_CONCAT(name) as players FROM waiting_list WHERE status = "waiting"');
    $stmt->execute();
    $result = $stmt->get_result();
    $game_status = $result->fetch_assoc();
    return json_encode($game_status);
}

function removePlayerFromWaitingRoom($conn, $player_uuid) {
    $stmt = $conn->prepare('DELETE FROM waiting_list WHERE uuid = ?');
    $stmt->bind_param('s', $player_uuid);
    $stmt->execute();
}

function updateLastActivity($conn, $player_uuid) {
    $stmt = $conn->prepare('UPDATE waiting_list SET last_activity = NOW() WHERE uuid = ?');
    $stmt->bind_param('s', $player_uuid);
    $stmt->execute();
}

function removeInactivePlayers($conn) {
    $stmt = $conn->prepare('DELETE FROM waiting_list WHERE last_activity < DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
    $stmt->execute();
}
//This functions controling the users at start and thier activity 
//You should use ajax for real time in cunstruct to update user ping and other properly every 0.2 second..
?>

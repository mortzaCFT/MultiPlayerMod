<?php
function createWaitingListTable($conn) {
    $stmt = $conn->prepare('CREATE TABLE IF NOT EXISTS waiting_list (
        id INT AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        gamble_amount INT NOT NULL,
        uuid VARCHAR(255) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT "waiting",
        ping INT NOT NULL,
        last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )');
    if (!$stmt) {
        echo 'Error preparing statement: ' . $conn->error;
        return;
    }
    $stmt->execute();
    if (!$stmt) {
        echo 'Error executing statement: ' . $stmt->error;
        return;
    }
}


function addPlayerToWaitingRoom($conn, $player_uuid, $player_name, $gamble_amount, $player_ping) {
    
    createWaitingListTable($conn);
    // Check if the UUID is valid
    if ($player_uuid === '0' || empty($player_uuid)) {
        return 'Invalid UUID';
    }

    // Check if the player is already in the waiting list
    $stmt = $conn->prepare('SELECT uuid FROM waiting_list WHERE uuid = ?');
    $stmt->bind_param('s', $player_uuid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Player already exists, update their information instead of inserting a new record
        $stmt = $conn->prepare('UPDATE waiting_list SET last_activity = NOW(), gamble_amount = ?, ping = ?, status = "waiting" WHERE uuid = ?');
        $stmt->bind_param('iis', $gamble_amount, $player_ping, $player_uuid);
        if (!$stmt->execute()) {
            return $stmt->error;
        }
    } else {
        // Player does not exist, insert a new record
        $stmt = $conn->prepare('INSERT INTO waiting_list (name, gamble_amount, uuid, status, ping) VALUES (?, ?, ?, ?, ?)');
        $status = 'waiting';
        $stmt->bind_param('sissi', $player_name, $gamble_amount, $player_uuid, $status, $player_ping);
        if (!$stmt->execute()) {
            return $stmt->error;
        }
    }

    return true;
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
    if (!$stmt) {
        echo 'Error preparing statement: ' . $conn->error;
        return;
    }
    $stmt->execute();
    if (!$stmt) {
        echo 'Error executing statement: ' . $stmt->error;
        return;
    }
}
?>

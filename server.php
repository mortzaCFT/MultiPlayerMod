<?php
include 'config.php';
include 'auth.php';
include 'query.php';
include 'session.php';

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_uuid = $_POST['player_uuid'];
    $player_name = $_POST['player_name'];
    $player_ping = $_POST['player_ping'];
    $gamble_amount = $_POST['gamble_amount'];
    $player_status = $_POST['player_status'];
    // Validate user through auth.php
    $user_data = authenticateUser($conn, $player_uuid);
    if ($user_data) {
        $player_name = $user_data['name'];
        $player_uuid = $user_data['uuid'];
        
        if ($player_status === 'OnStart') {
            // Add player to waiting room
            addPlayerToWaitingRoom($conn, $player_uuid, $player_name, $gamble_amount, $player_ping);
            updateLastActivity($conn, $player_uuid);

            // Start a new session
            $session = new Session($conn);
            $session->startSession($player_uuid);

            // Get the match info
            $matchInfo = $session->getMatchInfo($player_uuid);
            if (!empty($matchInfo)) {
                echo json_encode(['success' => true, 'match_info' => $matchInfo]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No game found']);
            }
        } elseif ($player_status === 'OnEnd') {
            echo json_encode(['success' => true, 'message' => 'Player removed from waiting room']);
        } elseif ($player_status === 'OnCancel') {
            // Remove player from waiting room
            removePlayerFromWaitingRoom($conn, $player_uuid);
            echo json_encode(['success' => true, 'message' => 'Player removed from waiting room']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid player status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
    }
}

removeInactivePlayers($conn);

$conn->close();
?>

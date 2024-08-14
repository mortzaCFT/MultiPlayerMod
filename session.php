<?php
require(__DIR__ . '/lib/auth.php');
require(__DIR__ . '/lib/config.php');
require(__DIR__ . '/lib/qurey.php');
require(__DIR__ . '/lib/session.php');


$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
//creating a waiting_list table
createWaitingListTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_uuid = $_POST['player_uuid'];
    $player_ping = $_POST['player_ping'];
    $gamble_amount = $_POST['gamble_amount'];
    $player_status = $_POST['player_status'];

    // validate user through auth.php
    $user_data = authenticateUser($conn, $player_uuid);
    if ($user_data) {
        $player_name = $user_data['name'];
        $player_uuid = $user_data['uuid'];

        if ($player_status === 'OnStart') {
            // add player to waiting room
            addPlayerToWaitingRoom($conn, $player_uuid, $player_name, $gamble_amount, $player_ping);
            updateLastActivity($conn, $player_uuid);

            // start a new session
            $session = new Session($conn);
            $session->startSession($player_uuid);

            // get the match info
            $matchInfo = $session->getMatchInfo($player_uuid);
            if (!empty($matchInfo)) {
                echo "success=true&match_info=$matchInfo";
            } else {
                echo "success=false&message=No game found";
            }
        } elseif ($player_status === 'OnEnd') {
            echo "success=true&message=Player removed from waiting room";
        } elseif ($player_status === 'OnCancel') {
            // remove player from waiting room
            removePlayerFromWaitingRoom($conn, $player_uuid);
            echo "success=true&message=Player removed from waiting room";
        } else {
            echo "success=false&message=Invalid player status";
        }
    } else {
        echo "success=false&message=Invalid user";
    }
}

removeInactivePlayers($conn);

$conn->close();
?>

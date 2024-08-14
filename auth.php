<?php
include 'config.php';

function authenticateUser($conn, $uuid) {
    $stmt = $conn->prepare('SELECT name, uuid FROM game WHERE uuid = ?');
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        return $user_data;
    } else {
        return false;
    }
}
?>

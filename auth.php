<?php
function authenticateUser($conn, $uuid) {
    $stmt = $conn->prepare('SELECT first_name, referral_code FROM tserver WHERE referral_code = ?');
    $stmt->bind_param('s', $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        // Add debug information
      //  error_log("Retrieved user data: " . print_r($user_data, true));
        $user_data['uuid'] = $uuid;
        return $user_data;
    } else {
        error_log("No user found for UUID: " . $uuid);
        return false;
    }
}
?>

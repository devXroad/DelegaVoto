<?php
require 'config.php';

if (!isset($_SESSION['allowed_to_vote']) || $_SESSION['allowed_to_vote'] !== true) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $jwt = $_POST['credential'];

    $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $jwt;
    $response  = @file_get_contents($verifyUrl);
    $payload   = json_decode($response, true);

    if ($payload && isset($payload['aud']) && $payload['aud'] === GOOGLE_CLIENT_ID) {
        $google_id = $payload['sub'];
        $email     = $payload['email'];
        $name      = $payload['name'] ?? explode('@', $email)[0];
        $name      = trim(preg_replace('/<.*?>/', '', $name));

        $reset_token = get_reset_token();

        $device_voted = isset($_COOKIE['device_voted']) && $_COOKIE['device_voted'] === $reset_token;
        if ($device_voted) {
            header("Location: pillin.php");
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$google_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (google_id, email, name) VALUES (?, ?, ?)");
            $stmt->execute([$google_id, $email, $name]);
            $user_id = $db->lastInsertId();
        } else {
            $user_id = $user['id'];
            $db->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $user_id]);

            if ($user['has_voted'] == 1) {
                header("Location: pillin.php");
                exit;
            }
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['email']   = $email;
        header("Location: vote.php");
        exit;
    }
}
header("Location: index.php?error=auth");
exit;

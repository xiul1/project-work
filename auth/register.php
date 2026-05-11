<?php
require "../requirement/pdo.php";

// Email verification only — registration is now handled in login.php
if (isset($_GET["verify"])) {
    $token = $_GET["verify"];
    $stmt  = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE token = :token");
    $stmt->bindValue(":token", $token);
    $stmt->execute();
    $record = $stmt->fetch();

    if ($record) {
        if (strtotime($record['expires_at']) >= time()) {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = :id");
            $stmt->bindValue(":id", $record['user_id']);
            $stmt->execute();

            $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE token = :token");
            $stmt->bindValue(":token", $token);
            $stmt->execute();

            header("Location: login.php?verified=1");
        } else {
            header("Location: login.php?verified=expired");
        }
    } else {
        header("Location: login.php?verified=invalid");
    }
    exit();
}

// No verify param — redirect to login
header("Location: login.php");
exit();



<?php

session_start();

header("Content-Type: application/json");

require "../../requirement/pdo.php";
require "../../requirement/crypto.php";
require "../../requirement/security.php";
require "../../requirement/helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, [
        "success" => false,
        "message" => "Metodo non consentito"
    ]);
}

if (!isset($_SESSION["user_id"])) {
    jsonResponse(401, [
        "success" => false,
        "message" => "Utente non autenticato"
    ]);
}

if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
    jsonResponse(403, [
        "success" => false,
        "message" => "Token CSRF non valido"
    ]);
}

if (!isset($_POST["id"]) || !is_numeric($_POST["id"])) {
    jsonResponse(400, [
        "success" => false,
        "message" => "ID non valido"
    ]);
}

$id = (int) $_POST["id"];
$userId = (int) $_SESSION["user_id"];

try {
    $stmt = $pdo->prepare("
        SELECT password_encrypted
        FROM credenziali
        WHERE credential_id = :id
        AND user_id = :uid
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $id,
        ":uid" => $userId
    ]);

    $credential = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$credential) {
        jsonResponse(404, [
            "success" => false,
            "message" => "Credenziale non trovata"
        ]);
    }

    $plainPassword = decryptUserPassword($userId, $credential["password_encrypted"]);

    if ($plainPassword === false) {
        jsonResponse(500, [
            "success" => false,
            "message" => "Errore durante la decifratura"
        ]);
    }

    if (!isEncryptedPasswordV2($credential["password_encrypted"])) {
        $migrated = encryptUserPassword($userId, $plainPassword);

        if ($migrated !== false) {
            try {
                $update = $pdo->prepare("
                    UPDATE credenziali
                    SET password_encrypted = :password
                    WHERE credential_id = :id
                    AND user_id = :uid
                ");

                $update->execute([
                    ":password" => $migrated,
                    ":id" => $id,
                    ":uid" => $userId
                ]);
            } catch (PDOException $e) {
                error_log("Password migration write failed for credential_id={$id}: " . $e->getMessage());
            }
        } else {
            error_log("Password migration to v2 failed for credential_id={$id}");
        }
    }

    jsonResponse(200, [
        "success" => true,
        "password" => $plainPassword
    ]);

} catch (PDOException $e) {
    jsonResponse(500, [
        "success" => false,
        "message" => "Errore database"
    ]);
}

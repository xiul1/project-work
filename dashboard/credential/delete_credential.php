<?php

session_start();
header("Content-Type: application/json");
require "../../requirement/pdo.php";
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
        DELETE FROM credenziali
        WHERE credential_id = :id
        AND user_id = :uid
    ");

    $stmt->execute([
        ":id" => $id,
        ":uid" => $userId
    ]);

    if ($stmt->rowCount() > 0) {
        jsonResponse(200, [
            "success" => true,
            "message" => "Credenziale eliminata correttamente"
        ]);
    }

    jsonResponse(404, [
        "success" => false,
        "message" => "Credenziale non trovata"
    ]);

} catch (PDOException $e) {
    jsonResponse(500, [
        "success" => false,
        "message" => "Errore durante l'eliminazione"
    ]);
}

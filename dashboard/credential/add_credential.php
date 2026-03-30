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

$service = trim($_POST["service_name"] ?? "");
$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";
$url = trim($_POST["url"] ?? "");
$notes = trim($_POST["notes"] ?? "");
$userId = (int) $_SESSION["user_id"];

if ($service === "") {
    jsonResponse(400, [
        "success" => false,
        "message" => "Il servizio è obbligatorio"
    ]);
}

if ($username === "") {
    jsonResponse(400, [
        "success" => false,
        "message" => "Lo username è obbligatorio"
    ]);
}

if ($password === "") {
    jsonResponse(400, [
        "success" => false,
        "message" => "La password è obbligatoria"
    ]);
}

$password_encrypted = encryptUserPassword($userId, $password);

if ($password_encrypted === false) {
    jsonResponse(500, [
        "success" => false,
        "message" => "Errore nella cifratura della password"
    ]);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO credenziali
        (user_id, service_name, username, password_encrypted, url, notes)
        VALUES
        (:uid, :service, :username, :password, :url, :notes)
    ");

    $stmt->execute([
        ":uid" => $userId,
        ":service" => $service,
        ":username" => $username,
        ":password" => $password_encrypted,
        ":url" => $url !== "" ? $url : null,
        ":notes" => $notes !== "" ? $notes : null
    ]);

    jsonResponse(200, [
        "success" => true,
        "message" => "Credenziale aggiunta correttamente",
        "id" => (int) $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    jsonResponse(500, [
        "success" => false,
        "message" => "Errore database durante l'inserimento"
    ]);
}

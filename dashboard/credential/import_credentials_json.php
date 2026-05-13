<?php
session_start();
header("Content-Type: application/json");

require "../../requirement/pdo.php";
require "../../requirement/security.php";
require "../../requirement/crypto.php";
require "../../requirement/helpers.php";
require "../../requirement/logger.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["success" => false, "message" => "Metodo non consentito"]);
}

if (!isset($_SESSION["user_id"])) {
    jsonResponse(401, ["success" => false, "message" => "Utente non autenticato"]);
}

if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
    jsonResponse(403, ["success" => false, "message" => "Token CSRF non valido"]);
}

if (!isset($_FILES["json_file"]) || $_FILES["json_file"]["error"] !== UPLOAD_ERR_OK) {
    jsonResponse(400, ["success" => false, "message" => "Nessun file caricato o errore nel caricamento"]);
}

$file      = $_FILES["json_file"];
$extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

if ($extension !== "json") {
    jsonResponse(400, ["success" => false, "message" => "Formato file non valido. Carica un file .json"]);
}

if ($file["size"] > 5242880) {
    jsonResponse(400, ["success" => false, "message" => "File troppo grande (massimo 5MB)"]);
}

$content = file_get_contents($file["tmp_name"]);
if ($content === false) {
    jsonResponse(500, ["success" => false, "message" => "Impossibile leggere il file"]);
}

$credentials = json_decode($content, true);
if (!is_array($credentials)) {
    jsonResponse(400, ["success" => false, "message" => "File JSON non valido o formato non riconosciuto"]);
}

$userId   = (int) $_SESSION["user_id"];
$imported = 0;
$skipped  = 0;

foreach ($credentials as $cred) {
    if (!is_array($cred)) {
        $skipped++;
        continue;
    }

    $service  = trim((string) ($cred["service_name"] ?? $cred["service"] ?? ""));
    $username = trim((string) ($cred["username"] ?? ""));
    $password = (string) ($cred["password"] ?? "");
    $url      = trim((string) ($cred["url"] ?? ""));
    $notes    = trim((string) ($cred["notes"] ?? ""));

    if ($service === "" || $username === "" || $password === "") {
        $skipped++;
        continue;
    }

    $service  = substr($service, 0, 255);
    $username = substr($username, 0, 255);
    $url      = substr($url, 0, 2048);
    $notes    = substr($notes, 0, 1000);

    $encryptedPassword = encryptUserPassword($userId, $password);
    if ($encryptedPassword === false) {
        $skipped++;
        continue;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO credenziali
            (user_id, service_name, username, password_encrypted, url, notes)
            VALUES (:uid, :service, :username, :password, :url, :notes)
        ");
        $stmt->execute([
            ":uid"      => $userId,
            ":service"  => $service,
            ":username" => $username,
            ":password" => $encryptedPassword,
            ":url"      => $url !== "" ? $url : null,
            ":notes"    => $notes !== "" ? $notes : null,
        ]);
        $imported++;
    } catch (PDOException $e) {
        $skipped++;
    }
}

logActivity($userId, "credential_import", "Importate $imported credenziali da JSON");

jsonResponse(200, [
    "success"  => true,
    "message"  => "Importazione completata: $imported aggiunte, $skipped saltate",
    "imported" => $imported,
    "skipped"  => $skipped,
]);

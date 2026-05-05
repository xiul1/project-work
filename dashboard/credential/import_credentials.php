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

// Controlla che sia stato caricato un file
if (!isset($_FILES["csv_file"]) || $_FILES["csv_file"]["error"] !== UPLOAD_ERR_OK) {
    jsonResponse(400, ["success" => false, "message" => "Nessun file caricato o errore nel caricamento"]);
}

$file = $_FILES["csv_file"];

// Controlla il tipo MIME e l'estensione
$allowedMimes = ["text/csv", "text/plain", "application/csv", "application/vnd.ms-excel"];
$extension    = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

if ($extension !== "csv" && !in_array($file["type"], $allowedMimes, true)) {
    jsonResponse(400, ["success" => false, "message" => "Formato file non valido. Carica un file .csv"]);
}

// Limite dimensione: 1MB
if ($file["size"] > 1048576) {
    jsonResponse(400, ["success" => false, "message" => "File troppo grande (massimo 1MB)"]);
}

$userId     = (int) $_SESSION["user_id"];
$imported   = 0;
$skipped    = 0;
$lineNumber = 0;

// Apre il file CSV e legge riga per riga
$handle = fopen($file["tmp_name"], "r");

if ($handle === false) {
    jsonResponse(500, ["success" => false, "message" => "Impossibile leggere il file"]);
}

// Legge e salta la riga intestazione
$header = fgetcsv($handle);
$lineNumber++;

// Colonne attese: servizio, username, password, url, note, data_creazione
// Le colonne url, note e data_creazione sono opzionali
if (!is_array($header) || count($header) < 3) {
    fclose($handle);
    jsonResponse(400, ["success" => false, "message" => "Il file CSV non ha il formato corretto. Sono richieste almeno le colonne: servizio, username, password"]);
}

while (($row = fgetcsv($handle)) !== false) {
    $lineNumber++;

    // Salta righe vuote
    if (count($row) < 3) {
        $skipped++;
        continue;
    }

    $service  = trim($row[0] ?? "");
    $username = trim($row[1] ?? "");
    $password = $row[2] ?? "";
    $url      = trim($row[3] ?? "");
    $notes    = trim($row[4] ?? "");

    // Valida i campi obbligatori
    if ($service === "" || $username === "" || $password === "") {
        $skipped++;
        continue;
    }

    // Limita la lunghezza per sicurezza
    $service  = substr($service, 0, 255);
    $username = substr($username, 0, 255);
    $url      = substr($url, 0, 2048);
    $notes    = substr($notes, 0, 1000);

    // Cifra la password
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

fclose($handle);

logActivity($userId, "credential_import", "Importate $imported credenziali da CSV");

jsonResponse(200, [
    "success"  => true,
    "message"  => "Importazione completata: $imported aggiunte, $skipped saltate",
    "imported" => $imported,
    "skipped"  => $skipped,
]);

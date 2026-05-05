<?php
session_start();

require "../../requirement/pdo.php";
require "../../requirement/security.php";
require "../../requirement/crypto.php";

// Controlla autenticazione
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    exit("Non autorizzato");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo non consentito");
}

// Verifica il token CSRF
if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
    http_response_code(403);
    exit("Token CSRF non valido");
}

$userId = (int) $_SESSION["user_id"];

// Formato richiesto: 'csv' oppure 'json'
$format = $_POST["format"] ?? "csv";
if (!in_array($format, ["csv", "json"], true)) {
    $format = "csv";
}

// Recupera tutte le credenziali dell'utente
$stmt = $pdo->prepare("
    SELECT credential_id, service_name, username, password_encrypted, url, notes, created_at
    FROM credenziali
    WHERE user_id = :uid
    ORDER BY service_name ASC
");
$stmt->execute([":uid" => $userId]);
$rows = $stmt->fetchAll();

// Decripta le password
$credentials = [];
foreach ($rows as $row) {
    $plainPassword = decryptUserPassword($userId, $row["password_encrypted"]);

    $credentials[] = [
        "service_name" => $row["service_name"],
        "username"     => $row["username"],
        "password"     => $plainPassword !== false ? $plainPassword : "",
        "url"          => $row["url"] ?? "",
        "notes"        => $row["notes"] ?? "",
        "created_at"   => $row["created_at"],
    ];
}

// Esportazione in JSON
if ($format === "json") {
    header("Content-Type: application/json; charset=UTF-8");
    header('Content-Disposition: attachment; filename="keymanager_export.json"');
    echo json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Esportazione in CSV
header("Content-Type: text/csv; charset=UTF-8");
header('Content-Disposition: attachment; filename="keymanager_export.csv"');

// BOM UTF-8 per compatibilità con Excel
echo "\xEF\xBB\xBF";

$out = fopen("php://output", "w");

// Intestazione colonne
fputcsv($out, ["servizio", "username", "password", "url", "note", "data_creazione"]);

// Una riga per credenziale
foreach ($credentials as $cred) {
    fputcsv($out, [
        $cred["service_name"],
        $cred["username"],
        $cred["password"],
        $cred["url"],
        $cred["notes"],
        $cred["created_at"],
    ]);
}

fclose($out);
exit();

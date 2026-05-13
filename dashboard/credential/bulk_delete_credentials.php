<?php
session_start();
header("Content-Type: application/json");

require "../../requirement/pdo.php";
require "../../requirement/security.php";
require "../../requirement/helpers.php";
require "../../requirement/logger.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["success" => false, "message" => "Metodo non consentito"]);
}

if (!isset($_SESSION["user_id"])) {
    jsonResponse(401, ["success" => false, "message" => "Utente non autenticato"]);
}

$body = json_decode(file_get_contents("php://input"), true);

if (!isValidCsrfToken($body["csrf_token"] ?? null)) {
    jsonResponse(403, ["success" => false, "message" => "Token CSRF non valido"]);
}

$ids = $body["ids"] ?? [];

if (!is_array($ids) || count($ids) === 0) {
    jsonResponse(400, ["success" => false, "message" => "Nessuna credenziale selezionata"]);
}

// Sanitize: keep only positive integers
$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

if (count($ids) === 0) {
    jsonResponse(400, ["success" => false, "message" => "ID credenziali non validi"]);
}

$userId = (int) $_SESSION["user_id"];

// Build parameterized placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params       = array_merge([$userId], $ids);

$stmt = $pdo->prepare(
    "DELETE FROM credenziali WHERE user_id = ? AND credential_id IN ($placeholders)"
);
$stmt->execute($params);
$deleted = $stmt->rowCount();

logActivity($userId, "credential_bulk_delete", "Eliminate $deleted credenziali selezionate");

jsonResponse(200, [
    "success" => true,
    "message" => "$deleted credential" . ($deleted !== 1 ? "s" : "") . " deleted",
    "deleted" => $deleted,
]);

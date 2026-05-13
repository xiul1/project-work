<?php
session_start();
header("Content-Type: application/json");

require "../../requirement/pdo.php";
require "../../requirement/security.php";
require "../../requirement/helpers.php";
require "../../requirement/logger.php";
require "../../requirement/preferences.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(405, ["success" => false, "message" => "Metodo non consentito"]);
}

if (!isset($_SESSION["user_id"])) {
    jsonResponse(401, ["success" => false, "message" => "Utente non autenticato"]);
}

if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
    jsonResponse(403, ["success" => false, "message" => "Token CSRF non valido"]);
}

$userId = (int) $_SESSION["user_id"];
$key    = $_POST["key"] ?? "";
$value  = $_POST["value"] ?? "";

if (!is_string($key) || !isAllowedPreferenceKey($key)) {
    jsonResponse(400, ["success" => false, "message" => "Chiave preferenza non valida"]);
}

$normalized = validatePreferenceValue($key, $value);
if ($normalized === null) {
    jsonResponse(400, ["success" => false, "message" => "Valore preferenza non valido"]);
}

$ok = setUserPreference($userId, $key, $normalized);
if (!$ok) {
    jsonResponse(500, ["success" => false, "message" => "Errore durante il salvataggio"]);
}

// Aggiorna anche la sessione per le preferenze usate lato server
if ($key === PREF_KEY_LANGUAGE) {
    $_SESSION["language"] = $normalized;
} elseif ($key === PREF_KEY_AUTO_LOCK) {
    $_SESSION["auto_lock_minutes"] = (int) $normalized;
} elseif ($key === PREF_KEY_THEME) {
    $_SESSION["theme"] = $normalized;
}

logActivity($userId, "preference_updated", "$key=$normalized");

jsonResponse(200, [
    "success" => true,
    "message" => "Preferenza aggiornata",
    "key"     => $key,
    "value"   => $normalized,
]);

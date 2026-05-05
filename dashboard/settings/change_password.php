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

if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
    jsonResponse(403, ["success" => false, "message" => "Token CSRF non valido"]);
}

$userId          = (int) $_SESSION["user_id"];
$currentPassword = $_POST["current_password"] ?? "";
$newPassword     = $_POST["new_password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

// Validazione base
if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
    jsonResponse(400, ["success" => false, "message" => "Tutti i campi sono obbligatori"]);
}

if ($newPassword !== $confirmPassword) {
    jsonResponse(400, ["success" => false, "message" => "Le nuove password non coincidono"]);
}

if (strlen($newPassword) < 8) {
    jsonResponse(400, ["success" => false, "message" => "La nuova password deve avere almeno 8 caratteri"]);
}

// Recupera l'hash attuale dalla base dati
$stmt = $pdo->prepare("SELECT password_hash_master FROM users WHERE id = :id");
$stmt->execute([":id" => $userId]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(404, ["success" => false, "message" => "Utente non trovato"]);
}

// Verifica che la password corrente sia giusta
if (!password_verify($currentPassword, $user["password_hash_master"])) {
    jsonResponse(400, ["success" => false, "message" => "La password attuale non è corretta"]);
}

// Aggiorna con il nuovo hash
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$update = $pdo->prepare("UPDATE users SET password_hash_master = :hash WHERE id = :id");
$update->execute([":hash" => $newHash, ":id" => $userId]);

// Rigenera la sessione per sicurezza dopo il cambio password
session_regenerate_id(true);

logActivity($userId, "password_changed", "Master password cambiata");

jsonResponse(200, ["success" => true, "message" => "Password aggiornata correttamente"]);

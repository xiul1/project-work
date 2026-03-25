<?php

session_start();
header("Content-Type: application/json");

require "../../requirement/pdo.php";
require "../../requirement/crypto.php";
require "../../requirement/security.php";

function jsonResponse($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

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

try {

    if(!empty($password)){

        $password_encrypted = encryptUserPassword($userId, $password);

        if ($password_encrypted === false) {
            jsonResponse(500, [
                "success" => false,
                "message" => "Errore durante la cifratura della password"
            ]);
        }

        $stmt = $pdo->prepare("
        UPDATE credenziali
        SET service_name=:service,
            username=:username,
            password_encrypted=:password,
            url=:url,
            notes=:notes
        WHERE credential_id=:id
        AND user_id=:uid
        ");

        $stmt->execute([
        ":service"=>$service,
        ":username"=>$username,
        ":password"=>$password_encrypted,
        ":url"=>$url !== "" ? $url : null,
        ":notes"=>$notes !== "" ? $notes : null,
        ":id"=>$id,
        ":uid"=>$userId
        ]);

    } else {

        $stmt = $pdo->prepare("
        UPDATE credenziali
        SET service_name=:service,
            username=:username,
            url=:url,
            notes=:notes
        WHERE credential_id=:id
        AND user_id=:uid
        ");

        $stmt->execute([
        ":service"=>$service,
        ":username"=>$username,
        ":url"=>$url !== "" ? $url : null,
        ":notes"=>$notes !== "" ? $notes : null,
        ":id"=>$id,
        ":uid"=>$userId
        ]);

    }

    if ($stmt->rowCount() > 0) {
        jsonResponse(200, [
            "success" => true,
            "message" => "Credenziale aggiornata correttamente"
        ]);
    }

    $checkStmt = $pdo->prepare("
        SELECT 1
        FROM credenziali
        WHERE credential_id = :id
        AND user_id = :uid
        LIMIT 1
    ");

    $checkStmt->execute([
        ":id" => $id,
        ":uid" => $userId
    ]);

    if ($checkStmt->fetchColumn()) {
        jsonResponse(200, [
            "success" => true,
            "message" => "Nessuna modifica da salvare"
        ]);
    }

    jsonResponse(404, [
        "success" => false,
        "message" => "Credenziale non trovata"
    ]);

} catch (PDOException $e) {
    jsonResponse(500, [
        "success" => false,
        "message" => "Errore database durante l'aggiornamento"
    ]);
}

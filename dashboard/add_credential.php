<?php
session_start();
require "../requirement/pdo.php";
require "../requirement/crypto.php";

if (!isset($_SESSION["user_id"])) {
    exit();
}

$service = $_POST["service_name"];
$username = $_POST["username"];
$password = $_POST["password"];
$url = $_POST["url"];
$notes = $_POST["notes"];

$password_encrypted = encryptUserPassword($_SESSION["user_id"], $password);

if ($password_encrypted === false) {
    die("Errore nella cifratura della password.");
}

$stmt = $pdo->prepare("
    INSERT INTO credenziali
    (user_id, service_name, username, password_encrypted, url, notes)
    VALUES
    (:uid, :service, :username, :password, :url, :notes)
");

$stmt->execute([
    ":uid" => $_SESSION["user_id"],
    ":service" => $service,
    ":username" => $username,
    ":password" => $password_encrypted,
    ":url" => $url,
    ":notes" => $notes
]);

header("Location: main.php");
exit();
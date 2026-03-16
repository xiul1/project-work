<?php

session_start();
require "../requirement/pdo.php";

if(!isset($_SESSION["user_id"])) {
    exit();
}

$id = $_GET["id"];

$stmt = $pdo->prepare("
DELETE FROM credenziali
WHERE credential_id = :id
AND user_id = :uid
");

$stmt->execute([
":id" => $id,
":uid" => $_SESSION["user_id"]
]);

header("Location: main.php");
exit();
<?php

session_start();
require "../requirement/pdo.php";

if(!isset($_SESSION["user_id"])) {
    exit();
}

$id = $_POST["id"];
$service = $_POST["service_name"];
$username = $_POST["username"];
$password = $_POST["password"];
$url = $_POST["url"];
$notes = $_POST["notes"];

if(!empty($password)){

    $password_encrypted = password_hash($password, PASSWORD_DEFAULT);

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
    ":url"=>$url,
    ":notes"=>$notes,
    ":id"=>$id,
    ":uid"=>$_SESSION["user_id"]
    ]);

}else{

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
    ":url"=>$url,
    ":notes"=>$notes,
    ":id"=>$id,
    ":uid"=>$_SESSION["user_id"]
    ]);

}

header("Location: main.php");
exit();
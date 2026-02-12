<?php
// db_connect.php

try {
    $hostname = "localhost";
    $dbname   = "my_xiuli";
    $user     = "xiuli";
    $pass     = "";

    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    die("Errore di connessione: " . $e->getMessage());
}
?>
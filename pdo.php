<?php
try {
    $hostname = "localhost";
    $dbname   = "KeyManager";  // il tuo database
    $user     = "root";        // XAMPP default
    $pass     = "";            // XAMPP root password vuota

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
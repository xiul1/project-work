<?php
// Distrugge la sessione e reindirizza al login

session_start();

// Cancella tutte le variabili di sessione
$_SESSION = [];

// Elimina il cookie di sessione dal browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Distrugge la sessione sul server
session_destroy();

// Torna al login
header("Location: login.php");
exit();

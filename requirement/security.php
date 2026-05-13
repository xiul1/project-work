<?php

// Durata massima di inattività in secondi (default 30 minuti)
const SESSION_TIMEOUT_SECONDS = 1800;

// Valori ammessi (minuti) per il timer di auto-lock
const ALLOWED_AUTO_LOCK_MINUTES = [5, 30, 60];

/**
 * Restituisce il numero di secondi di inattività consentiti prima
 * del logout, usando la preferenza in sessione se presente.
 */
function getSessionTimeoutSeconds() {
    $minutes = $_SESSION["auto_lock_minutes"] ?? 30;
    if (!in_array((int) $minutes, ALLOWED_AUTO_LOCK_MINUTES, true)) {
        return SESSION_TIMEOUT_SECONDS;
    }
    return ((int) $minutes) * 60;
}

function ensureSessionStarted() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function getCsrfToken() {
    ensureSessionStarted();

    if (
        !isset($_SESSION["csrf_token"]) ||
        !is_string($_SESSION["csrf_token"]) ||
        $_SESSION["csrf_token"] === ""
    ) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Controlla se la sessione è scaduta per inattività.
 * Se scaduta, distrugge la sessione e reindirizza al login.
 * Va chiamata all'inizio di ogni pagina protetta.
 */
function checkSessionTimeout() {
    ensureSessionStarted();

    $now = time();

    // Se l'utente non è loggato, non c'è niente da controllare
    if (!isset($_SESSION["user_id"])) {
        return;
    }

    // Se è la prima volta che controlliamo, salva il timestamp
    if (!isset($_SESSION["last_activity"])) {
        $_SESSION["last_activity"] = $now;
        return;
    }

    // Calcola quanto tempo è passato dall'ultima attività
    $inactiveSeconds = $now - $_SESSION["last_activity"];

    if ($inactiveSeconds > getSessionTimeoutSeconds()) {
        // Sessione scaduta: pulisci e reindirizza
        $_SESSION = [];
        session_destroy();
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }

    // Aggiorna il timestamp dell'ultima attività
    $_SESSION["last_activity"] = $now;
}

function isValidCsrfToken($token) {
    ensureSessionStarted();

    if (!is_string($token) || $token === "") {
        return false;
    }

    $sessionToken = $_SESSION["csrf_token"] ?? "";

    return is_string($sessionToken) &&
        $sessionToken !== "" &&
        hash_equals($sessionToken, $token);
}

<?php

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

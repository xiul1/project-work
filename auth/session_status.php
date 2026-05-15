<?php
// ===========================
// SESSION STATUS - API endpoint
// Risponde se l'utente è loggato nella sessione PHP corrente.
// Usato dall'estensione browser per mostrare il popup corretto.
// ===========================

session_start();

// Solo JSON, nessuna cache (lo stato sessione cambia ad ogni login/logout)
header('Content-Type: application/json');
header('Cache-Control: no-store');

$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

echo json_encode(['logged_in' => $logged_in]);
?>

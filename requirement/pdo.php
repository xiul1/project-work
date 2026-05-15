<?php
try {
    $appEnv = strtolower(trim((string) (getenv("APP_ENV") ?: "")));
    $httpHost = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));
    $hostOnly = explode(":", $httpHost)[0];
    $isLocalHost = in_array($hostOnly, ["localhost", "127.0.0.1", "::1"], true);
    $isLocalEnv = $appEnv === "local" || ($appEnv === "" && $isLocalHost);

    $dbHost = getenv("DB_HOST");
    $dbName = getenv("DB_NAME");
    $dbUser = getenv("DB_USER");
    $dbPass = getenv("DB_PASS");

    $hasEnvConfig =
        $dbHost !== false &&
        $dbName !== false &&
        $dbUser !== false &&
        $dbPass !== false &&
        $dbHost !== "" &&
        $dbName !== "" &&
        $dbUser !== "";

    if ($hasEnvConfig) {
        $hostname = $dbHost;
        $dbname = $dbName;
        $user = $dbUser;
        $pass = $dbPass;
    } elseif ($isLocalEnv) {
        $hostname = "localhost";
        $dbname = "KeyManager";
        $user = "root";
        $pass = "";
    } else {
        throw new RuntimeException("Configurazione database mancante");
    }

    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    exit("Errore di connessione al database.");
}

<?php

require_once __DIR__ . "/pdo.php";

/**
 * Restituisce la chiave di cifratura dell'utente.
 * Se non esiste e $createIfMissing = true, la crea automaticamente.
 */
function getUserEncryptionKey($userId, $createIfMissing) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT encryption_key
        FROM EncryptionKeys
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row["encryption_key"])) {
        return $row["encryption_key"];
    }

    if ($createIfMissing) {
        return createUserEncryptionKey($userId);
    }

    return false;
}

/**
 * Crea una nuova chiave AES-256 per l'utente e la salva nel database.
 */
function createUserEncryptionKey($userId) {
    global $pdo;

    $key = random_bytes(32); // 256 bit
    $algorithm = "AES-256-CBC";

    $stmt = $pdo->prepare("
        INSERT INTO EncryptionKeys (user_id, encryption_key, algorithm)
        VALUES (:user_id, :encryption_key, :algorithm)
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->bindValue(":encryption_key", $key, PDO::PARAM_LOB);
    $stmt->bindValue(":algorithm", $algorithm, PDO::PARAM_STR);
    $stmt->execute();

    return $key;
}

/**
 * Cifra una password usando la chiave dell'utente.
 * Restituisce una stringa base64 da salvare nel DB.
 */
function encryptUserPassword($userId, $plainPassword) {
    global $pdo;

    $key = getUserEncryptionKey($userId, true);

    if ($key === false) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT algorithm
        FROM EncryptionKeys
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $algorithm = $row ? $row["algorithm"] : "AES-256-CBC";

    $ivLength = openssl_cipher_iv_length($algorithm);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt(
        $plainPassword,
        $algorithm,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        return false;
    }

    // Salviamo IV + ciphertext in base64
    return base64_encode($iv . $encrypted);
}

/**
 * Decifra una password salvata nel DB.
 */
function decryptUserPassword($userId, $encryptedPassword) {
    global $pdo;

    $key = getUserEncryptionKey($userId, false);

    if ($key === false) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT algorithm
        FROM EncryptionKeys
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $algorithm = $row ? $row["algorithm"] : "AES-256-CBC";

    $decoded = base64_decode($encryptedPassword, true);

    if ($decoded === false) {
        return false;
    }

    $ivLength = openssl_cipher_iv_length($algorithm);

    if (strlen($decoded) <= $ivLength) {
        return false;
    }

    $iv = substr($decoded, 0, $ivLength);
    $ciphertext = substr($decoded, $ivLength);

    $decrypted = openssl_decrypt(
        $ciphertext,
        $algorithm,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $decrypted;
}
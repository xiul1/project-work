<?php

require_once __DIR__ . "/pdo.php";

const CRYPTO_V2_PREFIX = "v2:";
const CRYPTO_V2_CIPHER = "aes-256-gcm";
const CRYPTO_V2_NONCE_LENGTH = 12;
const CRYPTO_V2_TAG_LENGTH = 16;
const CRYPTO_LEGACY_CIPHER = "AES-256-CBC";

function getUserEncryptionRecord($userId, $createIfMissing) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT encryption_key, algorithm
        FROM EncryptionKeys
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $key = $row["encryption_key"] ?? null;

        if (is_resource($key)) {
            $key = stream_get_contents($key);
        }

        if (is_string($key) && $key !== "") {
            $algorithm = $row["algorithm"] ?? CRYPTO_LEGACY_CIPHER;

            return [
                "key" => $key,
                "algorithm" => is_string($algorithm) && $algorithm !== ""
                    ? $algorithm
                    : CRYPTO_LEGACY_CIPHER
            ];
        }
    }

    if ($createIfMissing) {
        return createUserEncryptionKey($userId);
    }

    return false;
}

function getUserEncryptionKey($userId, $createIfMissing) {
    $record = getUserEncryptionRecord($userId, $createIfMissing);

    if ($record === false) {
        return false;
    }

    return $record["key"];
}

function createUserEncryptionKey($userId) {
    global $pdo;

    $key = random_bytes(32);
    $algorithm = CRYPTO_LEGACY_CIPHER;

    $stmt = $pdo->prepare("
        INSERT INTO EncryptionKeys (user_id, encryption_key, algorithm)
        VALUES (:user_id, :encryption_key, :algorithm)
    ");
    $stmt->bindValue(":user_id", $userId, PDO::PARAM_INT);
    $stmt->bindValue(":encryption_key", $key, PDO::PARAM_LOB);
    $stmt->bindValue(":algorithm", $algorithm, PDO::PARAM_STR);
    $stmt->execute();

    return [
        "key" => $key,
        "algorithm" => $algorithm
    ];
}

function encryptUserPassword($userId, $plainPassword) {
    $record = getUserEncryptionRecord($userId, true);

    if ($record === false) {
        return false;
    }

    $nonce = random_bytes(CRYPTO_V2_NONCE_LENGTH);
    $tag = "";

    $ciphertext = openssl_encrypt(
        (string) $plainPassword,
        CRYPTO_V2_CIPHER,
        $record["key"],
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        "",
        CRYPTO_V2_TAG_LENGTH
    );

    if ($ciphertext === false || !is_string($tag) || strlen($tag) !== CRYPTO_V2_TAG_LENGTH) {
        return false;
    }

    return CRYPTO_V2_PREFIX . base64_encode($nonce . $tag . $ciphertext);
}

function decryptUserPassword($userId, $encryptedPassword) {
    $record = getUserEncryptionRecord($userId, false);

    if ($record === false) {
        return false;
    }

    if (isEncryptedPasswordV2($encryptedPassword)) {
        return decryptV2Password(
            $record["key"],
            substr($encryptedPassword, strlen(CRYPTO_V2_PREFIX))
        );
    }

    return decryptLegacyPassword($record["key"], $record["algorithm"], $encryptedPassword);
}

function isEncryptedPasswordV2($encryptedPassword) {
    return is_string($encryptedPassword) &&
        strncmp($encryptedPassword, CRYPTO_V2_PREFIX, strlen(CRYPTO_V2_PREFIX)) === 0;
}

function decryptV2Password($key, $encodedPayload) {
    if (!is_string($encodedPayload) || $encodedPayload === "") {
        return false;
    }

    $decoded = base64_decode($encodedPayload, true);

    if ($decoded === false || strlen($decoded) <= (CRYPTO_V2_NONCE_LENGTH + CRYPTO_V2_TAG_LENGTH)) {
        return false;
    }

    $nonce = substr($decoded, 0, CRYPTO_V2_NONCE_LENGTH);
    $tag = substr($decoded, CRYPTO_V2_NONCE_LENGTH, CRYPTO_V2_TAG_LENGTH);
    $ciphertext = substr($decoded, CRYPTO_V2_NONCE_LENGTH + CRYPTO_V2_TAG_LENGTH);

    return openssl_decrypt(
        $ciphertext,
        CRYPTO_V2_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );
}

function decryptLegacyPassword($key, $algorithm, $encryptedPassword) {
    $cipher = is_string($algorithm) && $algorithm !== ""
        ? $algorithm
        : CRYPTO_LEGACY_CIPHER;

    $decoded = base64_decode((string) $encryptedPassword, true);

    if ($decoded === false) {
        return false;
    }

    $ivLength = openssl_cipher_iv_length($cipher);

    if (!is_int($ivLength) || $ivLength <= 0 || strlen($decoded) <= $ivLength) {
        return false;
    }

    $iv = substr($decoded, 0, $ivLength);
    $ciphertext = substr($decoded, $ivLength);

    return openssl_decrypt(
        $ciphertext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
}

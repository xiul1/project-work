<?php
/**
 * Gestione delle preferenze utente (theme, language, auto-lock, ecc.).
 *
 * Le preferenze sono persistite nella tabella user_preferences con coppie
 * (user_id, pref_key, pref_value). Le chiavi sono limitate a una whitelist
 * e i valori validati per tipo prima dell'inserimento.
 */

require_once __DIR__ . "/pdo.php";

const PREF_KEY_THEME           = "theme";
const PREF_KEY_LANGUAGE        = "language";
const PREF_KEY_AUTO_LOCK       = "auto_lock";
const PREF_KEY_CLIPBOARD_CLEAR = "clipboard_clear";
const PREF_KEY_SECURITY_ALERTS = "security_alerts";
const PREF_KEY_WEAK_PWD_ALERTS = "weak_pwd_alerts";

/**
 * Crea la tabella user_preferences se non esiste ancora.
 */
function ensureUserPreferencesTable() {
    global $pdo;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            user_id    INT NOT NULL,
            pref_key   VARCHAR(50) NOT NULL,
            pref_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, pref_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
}

/**
 * Default centralizzati per le preferenze.
 * Tutte le pagine usano questi valori se l'utente non ha ancora salvato nulla.
 */
function getDefaultPreferences() {
    return [
        PREF_KEY_THEME           => "light",
        PREF_KEY_LANGUAGE        => "en",
        PREF_KEY_AUTO_LOCK       => "30",
        PREF_KEY_CLIPBOARD_CLEAR => "1",
        PREF_KEY_SECURITY_ALERTS => "1",
        PREF_KEY_WEAK_PWD_ALERTS => "1",
    ];
}

/**
 * Restituisce true se la chiave è ammessa.
 */
function isAllowedPreferenceKey($key) {
    return array_key_exists($key, getDefaultPreferences());
}

/**
 * Valida il valore di una preferenza in base al tipo della chiave.
 * Restituisce il valore normalizzato (string) oppure null se non valido.
 */
function validatePreferenceValue($key, $value) {
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $value = (string) $value;

    switch ($key) {
        case PREF_KEY_THEME:
            return in_array($value, ["light", "dark", "system"], true) ? $value : null;

        case PREF_KEY_LANGUAGE:
            return in_array($value, ["en", "it"], true) ? $value : null;

        case PREF_KEY_AUTO_LOCK:
            return in_array($value, ["5", "30", "60"], true) ? $value : null;

        case PREF_KEY_CLIPBOARD_CLEAR:
        case PREF_KEY_SECURITY_ALERTS:
        case PREF_KEY_WEAK_PWD_ALERTS:
            return in_array($value, ["0", "1"], true) ? $value : null;

        default:
            return null;
    }
}

/**
 * Recupera tutte le preferenze di un utente, riempiendo con i default
 * quelle non ancora salvate.
 */
function getUserPreferences($userId) {
    global $pdo;

    ensureUserPreferencesTable();

    $stmt = $pdo->prepare("SELECT pref_key, pref_value FROM user_preferences WHERE user_id = :uid");
    $stmt->execute([":uid" => (int) $userId]);
    $rows = $stmt->fetchAll();

    $prefs = getDefaultPreferences();
    foreach ($rows as $row) {
        $key = $row["pref_key"];
        if (isAllowedPreferenceKey($key)) {
            $prefs[$key] = $row["pref_value"];
        }
    }

    return $prefs;
}

/**
 * Inserisce o aggiorna una singola preferenza.
 * Restituisce true se la chiave/valore sono validi e il salvataggio è andato a buon fine.
 */
function setUserPreference($userId, $key, $value) {
    global $pdo;

    if (!isAllowedPreferenceKey($key)) {
        return false;
    }

    $normalized = validatePreferenceValue($key, $value);
    if ($normalized === null) {
        return false;
    }

    ensureUserPreferencesTable();

    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, pref_key, pref_value)
        VALUES (:uid, :k, :v)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)
    ");

    return $stmt->execute([
        ":uid" => (int) $userId,
        ":k"   => (string) $key,
        ":v"   => $normalized,
    ]);
}

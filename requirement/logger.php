<?php
/**
 * Helper per registrare le azioni degli utenti nel log attività.
 *
 * Crea automaticamente la tabella activity_log se non esiste.
 * Vai in dashboard/activity_log.php per vedere il log.
 */

require_once __DIR__ . "/pdo.php";

// Crea la tabella activity_log se non esiste ancora
function ensureActivityLogTable() {
    global $pdo;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            details     TEXT,
            ip_address  VARCHAR(45),
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        )
    ");
}

/**
 * Registra un'azione dell'utente nel log.
 *
 * @param int    $userId     ID dell'utente
 * @param string $actionType Tipo di azione (es. 'login', 'credential_add')
 * @param string $details    Descrizione opzionale (es. nome del servizio)
 */
function logActivity($userId, $actionType, $details = '') {
    global $pdo;

    ensureActivityLogTable();

    // Recupera l'indirizzo IP del visitatore
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'sconosciuto';

    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, action_type, details, ip_address)
        VALUES (:user_id, :action_type, :details, :ip)
    ");

    $stmt->execute([
        ':user_id'     => (int) $userId,
        ':action_type' => (string) $actionType,
        ':details'     => (string) $details,
        ':ip'          => $ip
    ]);
}

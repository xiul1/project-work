<?php
session_start();

// Controlla che l'utente sia loggato
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";
require "../requirement/logger.php";

// Controlla che la sessione non sia scaduta
checkSessionTimeout();

$userId = (int) $_SESSION["user_id"];

// Assicura che la tabella esista (prima visita)
ensureActivityLogTable();

// Recupera le ultime 100 azioni dell'utente, dalla più recente
$stmt = $pdo->prepare("
    SELECT action_type, details, ip_address, created_at
    FROM activity_log
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute([":uid" => $userId]);
$logs = $stmt->fetchAll();

// Traduzione dei tipi di azione in italiano
$actionLabels = [
    "login"             => "Accesso",
    "credential_add"    => "Credenziale aggiunta",
    "credential_update" => "Credenziale modificata",
    "credential_delete" => "Credenziale eliminata",
    "password_viewed"   => "Password visualizzata",
    "password_changed"  => "Master password cambiata",
];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Attività - KeyManager</title>
</head>
<body>

<!-- Barra di navigazione superiore -->
<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #ccc; margin-bottom:12px;">
    <h1 style="margin:0;">KeyManager</h1>
    <div>
        Ciao, <strong><?php echo htmlspecialchars($_SESSION["username"] ?? "Utente"); ?></strong>
        &nbsp;|&nbsp;
        <a href="main.php">Dashboard</a>
        &nbsp;|&nbsp;
        <a href="settings.php">Impostazioni</a>
        &nbsp;|&nbsp;
        <a href="../auth/logout.php">Esci</a>
    </div>
</div>

<h2>Log Attività</h2>
<p>Ultime 100 azioni del tuo account.</p>

<?php if (empty($logs)): ?>
    <p>Nessuna attività registrata.</p>
<?php else: ?>

<table border="1">
    <tr>
        <th>Data e ora</th>
        <th>Azione</th>
        <th>Dettagli</th>
        <th>IP</th>
    </tr>

    <?php foreach ($logs as $log): ?>
    <tr>
        <td><?php echo htmlspecialchars($log["created_at"]); ?></td>
        <td><?php echo htmlspecialchars($actionLabels[$log["action_type"]] ?? $log["action_type"]); ?></td>
        <td><?php echo htmlspecialchars($log["details"] ?? ""); ?></td>
        <td><?php echo htmlspecialchars($log["ip_address"] ?? ""); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php endif; ?>

</body>
</html>

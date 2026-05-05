<?php
session_start();

// Controlla che l'utente sia loggato
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";

// Controlla che la sessione non sia scaduta
checkSessionTimeout();

$csrfToken = getCsrfToken();
$userId = (int) $_SESSION["user_id"];

// Recupera i dati dell'utente
$stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = :id");
$stmt->execute([":id" => $userId]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni - KeyManager</title>
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
        <a href="activity_log.php">Log attività</a>
        &nbsp;|&nbsp;
        <a href="../auth/logout.php">Esci</a>
    </div>
</div>

<h2>Impostazioni account</h2>

<!-- Dati account (sola lettura) -->
<h3>Informazioni account</h3>
<p><strong>Username:</strong> <?php echo htmlspecialchars($user["username"] ?? ""); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"] ?? ""); ?></p>
<p><strong>Account creato il:</strong> <?php echo htmlspecialchars($user["created_at"] ?? ""); ?></p>

<hr>

<!-- Form cambio master password -->
<h3>Cambia master password</h3>

<div id="changePasswordMessage"></div>

<form id="changePasswordForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

    Password attuale:<br>
    <input type="password" name="current_password" id="currentPassword" required><br><br>

    Nuova password:<br>
    <input type="password" name="new_password" id="newPassword" required
           oninput="updateSettingsStrength(this.value)">
    <span id="settingsPasswordStrength" style="font-size:0.85em; margin-left:6px;"></span><br><br>

    Conferma nuova password:<br>
    <input type="password" name="confirm_password" id="confirmPassword" required><br><br>

    <button type="submit">Aggiorna password</button>
</form>

<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;

// Indicatore di forza password (stessa logica del dashboard)
function evaluatePasswordStrength(password) {
    if (!password || password.length === 0) return { label: '', color: '' };

    let score = 0;
    if (password.length >= 8)           score++;
    if (password.length >= 12)          score++;
    if (/[A-Z]/.test(password))         score++;
    if (/[0-9]/.test(password))         score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 1) return { label: 'Forza: Debole',      color: '#c00' };
    if (score === 2) return { label: 'Forza: Media',       color: '#e07000' };
    if (score === 3) return { label: 'Forza: Buona',       color: '#c8a000' };
    if (score === 4) return { label: 'Forza: Forte',       color: '#080' };
    return              { label: 'Forza: Molto forte',  color: '#005500' };
}

function updateSettingsStrength(password) {
    const el = document.getElementById('settingsPasswordStrength');
    const result = evaluatePasswordStrength(password);
    el.textContent = result.label;
    el.style.color  = result.color;
}

// Invia il form di cambio password
document.getElementById('changePasswordForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const formData = new FormData(this);
    const msgEl = document.getElementById('changePasswordMessage');

    try {
        const response = await fetch('settings/change_password.php', {
            method: 'POST',
            body: formData
        });

        const raw = await response.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch {
            data = { success: false, message: 'Risposta non valida dal server' };
        }

        msgEl.textContent = data.message || '';
        msgEl.style.color = data.success ? 'green' : 'red';

        if (data.success) {
            // Pulisce i campi dopo il successo
            this.reset();
            document.getElementById('settingsPasswordStrength').textContent = '';
        }
    } catch (error) {
        msgEl.textContent = 'Errore nella richiesta';
        msgEl.style.color = 'red';
    }
});
</script>

</body>
</html>

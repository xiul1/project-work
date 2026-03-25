<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    exit("Utente non autenticato.");
}

require "../../requirement/pdo.php";
require "../../requirement/crypto.php";
require "../../requirement/security.php";

$id = $_GET["id"] ?? null;

if (!$id) {
    exit("ID credenziale mancante.");
}

$stmt = $pdo->prepare("
    SELECT credential_id, user_id, service_name, username, password_encrypted, url, notes
    FROM credenziali
    WHERE credential_id = :id
    AND user_id = :uid
    LIMIT 1
");

$stmt->execute([
    ":id" => $id,
    ":uid" => $_SESSION["user_id"]
]);

$credential = $stmt->fetch();

if (!$credential) {
    exit("Credenziale non trovata.");
}

$plainPassword = decryptUserPassword($_SESSION["user_id"], $credential["password_encrypted"]);

if ($plainPassword === false) {
    exit("Errore durante la decifratura.");
}

$csrfToken = getCsrfToken();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mostra Password</title>
</head>
<body>

<h1>Dettaglio credenziale</h1>
<p>
    <a href="../main.php">Torna alla dashboard</a>
</p>

<p>
    <a href="edit_credential.php?id=<?php echo $credential["credential_id"]; ?>">Modifica credenziale</a>
</p>

<form method="POST" action="delete_credential.php" onsubmit="return confirm('Vuoi davvero eliminare questa credenziale?');">
    <input type="hidden" name="id" value="<?php echo $credential["credential_id"]; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
    <button type="submit">Elimina credenziale</button>
</form>

<p>
    <strong>Servizio:</strong>
    <?php echo htmlspecialchars($credential["service_name"]); ?>
</p>

<p>
    <strong>Username:</strong>
    <span id="usernameText"><?php echo htmlspecialchars($credential["username"]); ?></span>
    <button type="button" onclick="copyText('usernameText', 'Username copiato')">Copia username</button>
</p>

<p>
    <strong>Password:</strong>
    <span id="passwordText" data-password="<?php echo htmlspecialchars($plainPassword, ENT_QUOTES); ?>">••••••••••••</span>
    <button type="button" id="togglePasswordBtn" onclick="togglePassword()">Mostra password</button>
    <button type="button" onclick="copyPassword()">Copia password</button>
</p>

<p>
    <strong>URL:</strong>
    <span id="urlText"><?php echo htmlspecialchars($credential["url"] ?? "-"); ?></span>
    <?php if (!empty($credential["url"])): ?>
        <a href="<?php echo htmlspecialchars($credential["url"]); ?>" target="_blank">Apri sito</a>
    <?php endif; ?>
</p>

<p>
    <strong>Note:</strong><br>
    <span><?php echo htmlspecialchars($credential["notes"] ?: "Nessuna nota"); ?></span>
</p>


<div id="message"></div>

<script>
    let passwordVisible = false;

    function togglePassword() {
        const passwordText = document.getElementById("passwordText");
        const toggleBtn = document.getElementById("togglePasswordBtn");
        const realPassword = passwordText.dataset.password;

        if (passwordVisible) {
            passwordText.textContent = "••••••••••••";
            toggleBtn.textContent = "Mostra password";
            passwordVisible = false;
        } else {
            passwordText.textContent = realPassword;
            toggleBtn.textContent = "Nascondi password";
            passwordVisible = true;
        }
    }

    async function copyPassword() {
        const passwordText = document.getElementById("passwordText");
        const realPassword = passwordText.dataset.password;

        try {
            await navigator.clipboard.writeText(realPassword);
            showMessage("Password copiata");
        } catch (error) {
            showMessage("Copia password non riuscita");
        }
    }

    async function copyText(elementId, message) {
        const value = document.getElementById(elementId).textContent;

        try {
            await navigator.clipboard.writeText(value);
            showMessage(message);
        } catch (error) {
            showMessage("Copia non riuscita");
        }
    }

    function showMessage(message) {
        document.getElementById("message").textContent = message;
    }
</script>

</body>
</html>

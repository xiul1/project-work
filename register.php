<?php
require "database.php";

$message = "";

/* =========================
   REGISTRAZIONE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($username)) {
        $message = "Username obbligatorio.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    }
    elseif (strlen($password) < 8) {
        $message = "La password deve avere almeno 8 caratteri.";
    }
    else {

        // Controllo email già esistente
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $message = "Email già registrata.";
        }
        else {

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Inserimento utente
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash_master)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$username, $email, $password_hash]);

            $user_id = $pdo->lastInsertId();

            // Creazione token verifica
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $stmt = $pdo->prepare("
                INSERT INTO email_verification_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([$user_id, $token, $expires]);

            // Link verifica (simulazione)
            $verification_link = "http://localhost/register.php?verify=" . $token;

            $message = "Registrazione completata!<br>";
            $message .= "Clicca per verificare la email:<br>";
            $message .= "<a href='$verification_link'>$verification_link</a>";
        }
    }
}


/* =========================
   VERIFICA EMAIL
========================= */
if (isset($_GET["verify"])) {

    $token = $_GET["verify"];

    $stmt = $pdo->prepare("
        SELECT * FROM email_verification_tokens 
        WHERE token = ?
    ");
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    if ($record) {

        if (strtotime($record['expires_at']) >= time()) {

            // Qui potresti aggiungere una colonna "email_verified"
            // Per ora eliminiamo solo il token
            $stmt = $pdo->prepare("
                DELETE FROM email_verification_tokens 
                WHERE token = ?
            ");
            $stmt->execute([$token]);

            $message = "Email verificata con successo!";
        } else {
            $message = "Token scaduto.";
        }

    } else {
        $message = "Token non valido.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registrazione</title>
</head>
<body>

<h2>Registrazione</h2>

<form method="POST">
    Username:<br>
    <input type="text" name="username" required><br><br>

    Email:<br>
    <input type="email" name="email" required><br><br>

    Password:<br>
    <input type="password" name="password" required minlength="8"><br><br>

    <input type="submit" value="Registrati">
</form>

<p style="color:red;">
    <?php echo $message; ?>
</p>

</body>
</html>
<?php
require "pdo.php";  // Include la connessione al DB
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';


$message = "";

// Funzione per inviare email di verifica
function sendVerificationEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        // Configurazione SMTP per XAMPP (modifica con i tuoi dati SMTP)
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';        // Imposta il server SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'tuoindirizzo@example.com';   // SMTP username
        $mail->Password = 'tuapassword';                 // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('tuoindirizzo@example.com', 'Il Tuo Sito');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verifica la tua email';
        $verification_link = "http://localhost/project-work/register.php?verify=$token";
        $mail->Body = "Ciao,<br><br>Per favore verifica la tua email cliccando il link seguente:<br><a href='$verification_link'>$verification_link</a><br><br>Se non hai richiesto questa registrazione, ignora questa email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Registrazione
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Validazioni base
    if (empty($username)) {
        $message = "Username obbligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } elseif (strlen($password) < 8) {
        $message = "La password deve avere almeno 8 caratteri.";
    } else {

        // Controllo email già registrata
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $message = "Email già registrata.";
        } else {

            // Hash della password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Inserimento utente
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash_master, email_verified)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$username, $email, $password_hash]);

            $user_id = $pdo->lastInsertId();

            // Creazione token verifica email
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $stmt = $pdo->prepare("
                INSERT INTO email_verification_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $token, $expires]);

            // Invio email con PHPMailer
            if (sendVerificationEmail($email, $token)) {
                $message = "Registrazione completata!<br>Controlla la tua email per verificare il tuo account.";
            } else {
                $message = "Registrazione completata, ma non è stato possibile inviare l'email di verifica. Contatta l'amministratore.";
            }
        }
    }
}

// Verifica email
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
            // Aggiorna la colonna email_verified
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
            $stmt->execute([$record['user_id']]);

            // Cancella il token
            $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE token = ?");
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

<h2>Registrazione Utente</h2>

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
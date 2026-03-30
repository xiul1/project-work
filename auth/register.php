<?php
require "../requirement/pdo.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../lib/PHPMailer/src/PHPMailer.php';
require '../lib/PHPMailer/src/SMTP.php';
require '../lib/PHPMailer/src/Exception.php';

$message = "";

function sendVerificationEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'chriszhang238@gmail.com';
        $mail->Password = 'itrw sydw yfpd vtds';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('chriszhang238@gmail.com', 'Il Tuo Sito');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verifica la tua email';
        $verification_link = "http://localhost/project-work/auth/register.php?verify=$token";
        $mail->Body = "Ciao,<br><br>Per favore verifica la tua email cliccando il link seguente:<br><a href='$verification_link'>$verification_link</a><br><br>Se non hai richiesto questa registrazione, ignora questa email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($username)) {
        $message = "Username obbligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } elseif (strlen($password) < 8) {
        $message = "La password deve avere almeno 8 caratteri.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Email già registrata.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash_master, email_verified)
                VALUES (:username, :email, :password_hash_master, 0)
            ");
            $stmt->bindValue(":username", $username);
            $stmt->bindValue(":email", $email);
            $stmt->bindValue(":password_hash_master", $password_hash);
            $stmt->execute();

            $user_id = $pdo->lastInsertId();

            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $stmt = $pdo->prepare("
                INSERT INTO email_verification_tokens (user_id, token, expires_at)
                VALUES (:user_id, :token, :expires_at)
            ");
            $stmt->bindValue(":user_id", $user_id);
            $stmt->bindValue(":token", $token);
            $stmt->bindValue(":expires_at", $expires);
            $stmt->execute();

            if (sendVerificationEmail($email, $token)) {
                $message = "Registrazione completata! Controlla la tua email per verificare il tuo account.";
            } else {
                $message = "Registrazione completata, ma non è stato possibile inviare l'email di verifica.";
            }
        }
    }
}

if (isset($_GET["verify"])) {

    $token = $_GET["verify"];

    $stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE token = :token");
    $stmt->bindValue(":token", $token);
    $stmt->execute();
    $record = $stmt->fetch();

    if ($record) {
        if (strtotime($record['expires_at']) >= time()) {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = :id");
            $stmt->bindValue(":id", $record['user_id']);
            $stmt->execute();

            $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE token = :token");
            $stmt->bindValue(":token", $token);
            $stmt->execute();

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
    <meta charset="UTF-8">
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
    <?php echo htmlspecialchars($message); ?>
</p>

</body>
</html>

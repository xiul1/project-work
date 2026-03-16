<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require "../../requirement/pdo.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

$message = "";

function sendResetPasswordEmail($email, $token) {
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
        $mail->Subject = 'Reset Password';

        $reset_link = "http://localhost/project-work/index/user/reset_password.php?token=$token";

        $mail->Body = "Ciao,<br><br>Hai richiesto il reset della password.<br><br>Clicca il link seguente per impostare una nuova password:<br><a href='$reset_link'>$reset_link</a><br><br>Se non hai richiesto questa operazione puoi ignorare questa email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
$forgot = isset($_GET["forgot"]);


if ($_SERVER["REQUEST_METHOD"] === "POST" && !$forgot) {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Validazioni base
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } elseif (empty($password)) {
        $message = "Password obbligatoria.";
    } else {

        // Cerca utente
        $stmt = $pdo->prepare("SELECT id, username, password_hash_master, email_verified FROM users WHERE email = :email  ");

        $stmt->bindValue(":email", $email);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {

            $message = "Utente non trovato.";

        } else {

            if ($user["email_verified"] == 0) {

                $message = "Email non verificata.";

            } else {

                // Verifica password
                if (password_verify($password, $user["password_hash_master"])) {

                    session_start();

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];

                    header("Location: ../../dashboard/main.php");
                    exit();

                } else {
                    $message = "Password errata.";
                }

            }

        }

    }
}

// =========================
// PASSWORD DIMENTICATA
// =========================

if ($forgot && $_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } else {

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $message = "Utente non trovato.";
        } else {

            // genera token di reset
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // recupera id utente
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $userStmt->bindValue(":email", $email);
            $userStmt->execute();
            $user = $userStmt->fetch();

            // salva token nel database
            $insert = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires)");
            $insert->bindValue(":user_id", $user['id']);
            $insert->bindValue(":token", $token);
            $insert->bindValue(":expires", $expires);
            $insert->execute();

            // invia email con link di reset
            if (sendResetPasswordEmail($email, $token)) {
                $message = "Controlla la tua email per il link di reset password.";
            } else {
                $message = "Errore nell'invio dell'email.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
</head>

<body>

<h2>Login Utente</h2>

<?php if (!$forgot) { ?>

<form method="POST">

Email:<br>
<input type="email" name="email" required><br><br>

Password:<br>
<input type="password" name="password" required><br><br>

<input type="submit" value="Login">

</form>

<a href="?forgot=1">Password dimenticata?</a><br>
<a href="http://localhost/project-work/index/user/register.php">Registrati</a>

<?php } else { ?>

<h3>Recupera Password</h3>

<form method="POST">

Email:<br>
<input type="email" name="email" required><br><br>

<input type="submit" value="Invia richiesta reset">

</form>

<a href="login.php">Torna al login</a>

<?php } ?>

<p style="color:red;">
<?php echo $message; ?>
</p>

</body>
</html>
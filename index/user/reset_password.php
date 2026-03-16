
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../../requirement/pdo.php";

$message = "";

if (!isset($_GET['token'])) {
    die("Token non valido.");
}

$token = $_GET['token'];

// Cerca il token nel database
$stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = :token");
$stmt->bindValue(":token", $token);
$stmt->execute();
$record = $stmt->fetch();

if (!$record) {
    die("Token non valido.");
}

// Controlla scadenza
if (strtotime($record['expires_at']) < time()) {
    die("Token scaduto.");
}

// Se il form viene inviato
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST['password'];

    if (strlen($password) < 8) {
        $message = "La password deve avere almeno 8 caratteri.";
    } else {

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Aggiorna password utente
        $update = $pdo->prepare("UPDATE users SET password_hash_master = :pass WHERE id = :id");
        $update->bindValue(":pass", $password_hash);
        $update->bindValue(":id", $record['user_id']);
        $update->execute();

        // Cancella il token
        $delete = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
        $delete->bindValue(":token", $token);
        $delete->execute();

        $message = "Password aggiornata con successo! Ora puoi fare login.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>

<h2>Imposta una nuova password</h2>

<form method="POST">

Nuova password:<br>
<input type="password" name="password" required minlength="8"><br><br>

<input type="submit" value="Aggiorna password">

</form>

<p style="color:red;">
<?php echo $message; ?>
</p>

</body>
</html>
<?php
require "../requirement/pdo.php";
require "../requirement/mail_config.php";
require "../requirement/logger.php";

$message = "";

// Mostra un messaggio se l'utente è stato disconnesso per inattività
if (isset($_GET["timeout"])) {
    $message = "Sessione scaduta per inattività. Effettua nuovamente il login.";
}

function sendResetPasswordEmail($email, $token) {
    $reset_link = "http://localhost/project-work/auth/reset_password.php?token=$token";

    $subject = "Reset Password - KeyManager";
    $body    = "Ciao,<br><br>Hai richiesto il reset della password.<br><br>"
             . "Clicca il link seguente per impostare una nuova password:<br>"
             . "<a href='$reset_link'>$reset_link</a><br><br>"
             . "Se non hai richiesto questa operazione puoi ignorare questa email.";

    return sendMail($email, $subject, $body);
}

$forgot = isset($_GET["forgot"]);

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$forgot) {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } elseif (empty($password)) {
        $message = "Password obbligatoria.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash_master, email_verified FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            $message = "Utente non trovato.";
        } elseif ($user["email_verified"] == 0) {
            $message = "Email non verificata.";
        } elseif (password_verify($password, $user["password_hash_master"])) {
            session_start();
            // Rigenera l'ID di sessione per prevenire session fixation
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["last_activity"] = time();
            // Registra il login nel log attività
            logActivity($user["id"], "login", "Login effettuato");
            header("Location: ../dashboard/main.php");
            exit();
        } else {
            $message = "Password errata.";
        }
    }
}

if ($forgot && $_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email non valida.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if (!$user) {
            $message = "Utente non trovato.";
        } else {
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $insert = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires)");
            $insert->bindValue(":user_id", $user['id']);
            $insert->bindValue(":token", $token);
            $insert->bindValue(":expires", $expires);
            $insert->execute();

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
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — <?php echo $forgot ? 'Reset Password' : 'Login'; ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-shell">
  <div class="auth-card">

    <!-- Dark left panel -->
    <div class="auth-panel-dark">
      <div class="auth-dark-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        <span><span class="logo-key">Key</span>Manager</span>
      </div>
      <div class="auth-dark-tagline">
        <h2>Your vault.<br><span>Always secure.</span></h2>
      </div>
      <div class="auth-dark-stats">
        <div class="stat-item"><strong>256-bit</strong><span>Encryption</span></div>
        <div class="stat-item"><strong>2M+</strong><span>Users</span></div>
        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
      </div>
    </div>

    <!-- Light right panel -->
    <div class="auth-panel-light">

      <?php if (!$forgot): ?>

        <div class="auth-form-header">
          <h1>Welcome back</h1>
          <p>Sign in to access your vault</p>
        </div>

        <?php if ($message): ?>
          <p class="form-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
          </div>
          <div>
            <div class="input-group">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password" id="loginPassword" placeholder="Master password" required autocomplete="current-password">
              <button type="button" class="input-action" onclick="togglePassword('loginPassword', this)" aria-label="Mostra password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="form-row-meta" style="margin-top:8px;">
              <a href="?forgot=1">Forgot password?</a>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <p class="form-footer-text">Don't have an account? <a href="register.php">Sign up</a></p>

      <?php else: ?>

        <div class="auth-icon-wrap">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>

        <div class="auth-form-header">
          <h1>Reset Password</h1>
          <p>Enter your email and we'll send you a reset link</p>
        </div>

        <?php if ($message): ?>
          <p class="form-message <?php echo str_contains($message, 'Controlla') ? 'success' : ''; ?>">
            <?php echo htmlspecialchars($message); ?>
          </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
          </div>
          <button type="submit" class="btn btn-primary">Send Reset Link</button>
        </form>

        <p class="form-footer-text">
          <a href="login.php">← Back to Sign In</a>
        </p>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function togglePassword(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const isVisible = input.type === 'text';
  input.type = isVisible ? 'password' : 'text';
  btn.innerHTML = isVisible
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>

</body>
</html>

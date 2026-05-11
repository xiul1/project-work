<?php
require "../requirement/pdo.php";
require "../requirement/mail_config.php";

$message = "";

function sendVerificationEmail($email, $token) {
    $verification_link = "http://localhost/project-work/auth/register.php?verify=$token";

    $subject = "Verifica la tua email - KeyManager";
    $body    = "Ciao,<br><br>Per favore verifica la tua email cliccando il link seguente:<br>"
             . "<a href='$verification_link'>$verification_link</a><br><br>"
             . "Se non hai richiesto questa registrazione, ignora questa email.";

    return sendMail($email, $subject, $body);
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
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — Create Account</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-shell">
  <div class="auth-card">

    <!-- Light left panel (form side) -->
    <div class="auth-panel-light">

      <div class="auth-form-header">
        <h1>Create Account</h1>
        <p>Start protecting your passwords today</p>
      </div>

      <?php if ($message): ?>
        <p class="form-message <?php echo (str_contains($message, 'completat') || str_contains($message, 'verificata')) ? 'success' : ''; ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>
      <?php endif; ?>

      <form method="POST" class="auth-form" id="registerForm">
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          <input type="text" name="username" placeholder="Full name" required autocomplete="name">
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <input type="email" name="email" placeholder="Email address" required autocomplete="email">
        </div>
        <div>
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" id="regPassword" placeholder="Master password" required minlength="8"
              autocomplete="new-password" oninput="updateRegStrength(this.value)">
          </div>
          <div class="strength-bar-wrap" style="margin-top:6px;">
            <div class="strength-bar" id="regStrengthBar"></div>
          </div>
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="confirm_password" id="regConfirm" placeholder="Confirm password" required minlength="8" autocomplete="new-password">
          <button type="button" class="input-action" onclick="togglePassword('regConfirm', this)" aria-label="Mostra password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <label class="checkbox-row">
          <input type="checkbox" required>
          I agree to the Terms of Service and Privacy Policy
        </label>
        <button type="submit" class="btn btn-primary">Create Account</button>
      </form>

      <p class="form-footer-text">Already have an account? <a href="login.php">Sign in</a></p>

    </div>

    <!-- Dark right panel -->
    <div class="auth-panel-dark">
      <div class="auth-dark-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        <span><span class="logo-key">Key</span>Manager</span>
      </div>
      <div class="auth-dark-tagline">
        <h2>Start your<br><span>secure journey.</span></h2>
      </div>
      <div class="auth-dark-stats">
        <div class="stat-item"><strong>256-bit</strong><span>Encryption</span></div>
        <div class="stat-item"><strong>2M+</strong><span>Users</span></div>
        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
      </div>
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

function updateRegStrength(pwd) {
  const bar = document.getElementById('regStrengthBar');
  let score = 0;
  if (pwd.length >= 8)  score++;
  if (pwd.length >= 12) score++;
  if (/[A-Z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  const pct = Math.min(100, score * 20);
  const colors = ['', '#E5484D', '#E5484D', '#f59e0b', '#30A46C', '#30A46C'];
  bar.style.width = pct + '%';
  bar.style.background = colors[score] || '';
}
</script>

</body>
</html>

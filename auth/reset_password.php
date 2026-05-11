<?php
require "../requirement/pdo.php";

$message = "";

if (!isset($_GET['token'])) {
    die("Token non valido.");
}

$token = $_GET['token'];

$stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = :token");
$stmt->bindValue(":token", $token);
$stmt->execute();
$record = $stmt->fetch();

if (!$record) {
    die("Token non valido.");
}

if (strtotime($record['expires_at']) < time()) {
    die("Token scaduto.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST['password'];

    if (strlen($password) < 8) {
        $message = "La password deve avere almeno 8 caratteri.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password_hash_master = :pass WHERE id = :id");
        $update->bindValue(":pass", $password_hash);
        $update->bindValue(":id", $record['user_id']);
        $update->execute();

        $delete = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
        $delete->bindValue(":token", $token);
        $delete->execute();

        $message = "Password aggiornata con successo! Ora puoi fare login.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — Reset Password</title>
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
        <h2>Forgot?<br><span>No worries.</span></h2>
      </div>
      <div class="auth-dark-stats">
        <div class="stat-item"><strong>256-bit</strong><span>Encryption</span></div>
        <div class="stat-item"><strong>2M+</strong><span>Users</span></div>
        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
      </div>
    </div>

    <!-- Light right panel -->
    <div class="auth-panel-light">

      <div class="auth-icon-wrap">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>

      <div class="auth-form-header">
        <h1>Set New Password</h1>
        <p>Choose a strong master password</p>
      </div>

      <?php if ($message): ?>
        <p class="form-message <?php echo str_contains($message, 'successo') ? 'success' : ''; ?>">
          <?php echo htmlspecialchars($message); ?>
        </p>
      <?php endif; ?>

      <?php if (!str_contains($message, 'successo')): ?>
      <form method="POST" class="auth-form">
        <div>
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" id="newPassword" placeholder="New master password" required minlength="8"
              autocomplete="new-password" oninput="updateStrBar(this.value)">
            <button type="button" class="input-action" onclick="togglePassword('newPassword', this)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="strength-bar-wrap" style="margin-top:6px;">
            <div class="strength-bar" id="resetStrBar"></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
      <?php endif; ?>

      <p class="form-footer-text">
        <a href="login.php">← Back to Sign In</a>
      </p>

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

function updateStrBar(pwd) {
  const bar = document.getElementById('resetStrBar');
  let score = 0;
  if (pwd.length >= 8)  score++;
  if (pwd.length >= 12) score++;
  if (/[A-Z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  bar.style.width = Math.min(100, score * 20) + '%';
  bar.style.background = ['', '#E5484D', '#E5484D', '#f59e0b', '#30A46C', '#30A46C'][score] || '';
}
</script>

</body>
</html>

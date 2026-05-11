<?php
require "../requirement/pdo.php";
require "../requirement/mail_config.php";
require "../requirement/logger.php";

$message_login    = "";
$message_register = "";
$message_forgot   = "";
$mode = $_GET['mode'] ?? 'signin'; // signin | signup | forgot

// ── Login ────────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'login') {
    $mode = 'signin';
    $email    = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_login = "Email non valida.";
    } elseif (empty($password)) {
        $message_login = "Password obbligatoria.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash_master, email_verified FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if (!$user) {
            $message_login = "Utente non trovato.";
        } elseif ($user["email_verified"] == 0) {
            $message_login = "Email non verificata.";
        } elseif (password_verify($password, $user["password_hash_master"])) {
            session_start();
            session_regenerate_id(true);
            $_SESSION["user_id"]       = $user["id"];
            $_SESSION["username"]      = $user["username"];
            $_SESSION["last_activity"] = time();
            logActivity($user["id"], "login", "Login effettuato");
            header("Location: ../dashboard/main.php");
            exit();
        } else {
            $message_login = "Password errata.";
        }
    }
}

// ── Register ─────────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'register') {
    $mode     = 'signup';
    $username = trim($_POST["username"] ?? '');
    $email    = trim($_POST["email_reg"] ?? '');
    $password = $_POST["password_reg"] ?? '';

    if (empty($username)) {
        $message_register = "Username obbligatorio.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_register = "Email non valida.";
    } elseif (strlen($password) < 8) {
        $message_register = "La password deve avere almeno 8 caratteri.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message_register = "Email già registrata.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash_master, email_verified) VALUES (:username, :email, :password_hash_master, 0)");
            $stmt->bindValue(":username", $username);
            $stmt->bindValue(":email", $email);
            $stmt->bindValue(":password_hash_master", $password_hash);
            $stmt->execute();
            $user_id = $pdo->lastInsertId();

            $token   = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
            $stmt = $pdo->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $stmt->bindValue(":user_id", $user_id);
            $stmt->bindValue(":token", $token);
            $stmt->bindValue(":expires_at", $expires);
            $stmt->execute();

            $link    = "http://localhost/project-work/auth/register.php?verify=$token";
            $subject = "Verifica la tua email - KeyManager";
            $body    = "Ciao,<br><br>Verifica la tua email: <a href='$link'>$link</a>";

            if (sendMail($email, $subject, $body)) {
                $message_register = "Registrazione completata! Controlla la tua email.";
            } else {
                $message_register = "Registrazione completata, ma email non inviata.";
            }
        }
    }
}

// ── Forgot password ───────────────────────────────────────────────────────────
if ($mode === 'forgot' && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'forgot') {
    $email = trim($_POST["email_forgot"] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_forgot = "Email non valida.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch();
        if (!$user) {
            $message_forgot = "Utente non trovato.";
        } else {
            $token   = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
            $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires)");
            $stmt->bindValue(":user_id", $user['id']);
            $stmt->bindValue(":token", $token);
            $stmt->bindValue(":expires", $expires);
            $stmt->execute();
            $reset_link = "http://localhost/project-work/auth/reset_password.php?token=$token";
            $subject    = "Reset Password - KeyManager";
            $body       = "Ciao,<br><br>Clicca qui per resettare la password: <a href='$reset_link'>$reset_link</a>";
            if (sendMail($email, $subject, $body)) {
                $message_forgot = "Controlla la tua email per il link di reset.";
            } else {
                $message_forgot = "Errore nell'invio dell'email.";
            }
        }
    }
}

if (isset($_GET["timeout"])) {
    $message_login = "Sessione scaduta per inattività. Effettua nuovamente il login.";
    $mode = 'signin';
}

if (isset($_GET["verified"])) {
    $v = $_GET["verified"];
    if ($v === '1')       $message_login = "Email verificata con successo! Puoi accedere.";
    elseif ($v === 'expired') $message_login = "Il link di verifica è scaduto.";
    elseif ($v === 'invalid') $message_login = "Link di verifica non valido.";
    $mode = 'signin';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — <?php echo $mode === 'signup' ? 'Create Account' : ($mode === 'forgot' ? 'Reset Password' : 'Login'); ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-shell">
  <div class="auth-card mode-<?php echo htmlspecialchars($mode === 'forgot' ? 'signin' : $mode); ?>" id="authCard">

    <!-- Dark sliding panel -->
    <div class="auth-panel-dark">
      <div class="auth-dark-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        <span><span class="logo-key">Key</span>Manager</span>
      </div>
      <div class="auth-dark-tagline" id="panelTagline">
        <h2 id="panelTitle">Your vault.<br><span>Always secure.</span></h2>
      </div>
      <div class="auth-dark-stats">
        <div class="stat-item"><strong>256-bit</strong><span>Encryption</span></div>
        <div class="stat-item"><strong>2M+</strong><span>Users</span></div>
        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
      </div>
    </div>

    <!-- Sign-In pane (right) -->
    <div class="auth-form-pane auth-pane-signin" id="paneSignin">
      <?php if ($mode !== 'forgot'): ?>

        <div class="auth-form-header">
          <h1>Welcome back</h1>
          <p>Sign in to access your vault</p>
        </div>

        <?php if ($message_login): ?>
          <p class="form-message <?php echo str_contains($message_login, 'successo') ? 'success' : ''; ?>">
            <?php echo htmlspecialchars($message_login); ?>
          </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
          <input type="hidden" name="action" value="login">
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
          </div>
          <div>
            <div class="input-group">
              <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password" id="loginPassword" placeholder="Master password" required autocomplete="current-password">
              <button type="button" class="input-action" onclick="togglePwd('loginPassword',this)" aria-label="Mostra password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="form-row-meta" style="margin-top:8px;">
              <a href="#" onclick="switchMode('forgot');return false;">Forgot password?</a>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <p class="form-footer-text">Don't have an account? <a href="#" onclick="switchMode('signup');return false;">Sign up</a></p>

      <?php else: ?>

        <!-- Forgot password form shown inside signin pane -->
        <div class="auth-icon-wrap">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="auth-form-header">
          <h1>Reset Password</h1>
          <p>Enter your email and we'll send you a reset link</p>
        </div>

        <?php if ($message_forgot): ?>
          <p class="form-message <?php echo str_contains($message_forgot, 'Controlla') ? 'success' : ''; ?>">
            <?php echo htmlspecialchars($message_forgot); ?>
          </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
          <input type="hidden" name="action" value="forgot">
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <input type="email" name="email_forgot" placeholder="Email address" required autocomplete="email">
          </div>
          <button type="submit" class="btn btn-primary">Send Reset Link</button>
        </form>

        <p class="form-footer-text"><a href="login.php">← Back to Sign In</a></p>

      <?php endif; ?>
    </div>

    <!-- Sign-Up pane (left) -->
    <div class="auth-form-pane auth-pane-signup" id="paneSignup">

      <div class="auth-form-header">
        <h1>Create Account</h1>
        <p>Start protecting your passwords today</p>
      </div>

      <?php if ($message_register): ?>
        <p class="form-message <?php echo (str_contains($message_register, 'completat') || str_contains($message_register, 'verificata')) ? 'success' : ''; ?>">
          <?php echo htmlspecialchars($message_register); ?>
        </p>
      <?php endif; ?>

      <form method="POST" class="auth-form">
        <input type="hidden" name="action" value="register">
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
          <input type="text" name="username" placeholder="Full name" required autocomplete="name">
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <input type="email" name="email_reg" placeholder="Email address" required autocomplete="email">
        </div>
        <div>
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password_reg" id="regPassword" placeholder="Master password" required minlength="8"
              autocomplete="new-password" oninput="updateStrength(this.value)">
          </div>
          <div class="strength-bar-wrap" style="margin-top:6px;">
            <div class="strength-bar" id="regStrengthBar"></div>
          </div>
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="confirm_password" id="regConfirm" placeholder="Confirm password" required minlength="8" autocomplete="new-password">
          <button type="button" class="input-action" onclick="togglePwd('regConfirm',this)" aria-label="Mostra password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <label class="checkbox-row">
          <input type="checkbox" required>
          I agree to the Terms of Service and Privacy Policy
        </label>
        <button type="submit" class="btn btn-primary">Create Account</button>
      </form>

      <p class="form-footer-text">Already have an account? <a href="#" onclick="switchMode('signin');return false;">Sign in</a></p>

    </div>

  </div>
</div>

<script>
var card = document.getElementById('authCard');

var panelTexts = {
  signin: 'Your vault.<br><span>Always secure.<\/span>',
  signup: 'Start your<br><span>secure journey.<\/span>',
  forgot: 'Your vault.<br><span>Always secure.<\/span>'
};

function switchMode(mode) {
  if (mode === 'forgot') {
    // Reload page with forgot param so PHP renders the forgot form
    window.location.href = '?mode=forgot';
    return;
  }
  card.className = 'auth-card mode-' + mode;
  document.getElementById('panelTitle').innerHTML = panelTexts[mode];
  history.replaceState({}, '', mode === 'signup' ? '?mode=signup' : '?');
}

function togglePwd(id, btn) {
  var input = document.getElementById(id);
  var visible = input.type === 'text';
  input.type = visible ? 'password' : 'text';
  btn.innerHTML = visible
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/><\/svg>'
    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"\/><\/svg>';
}

function updateStrength(pwd) {
  var bar = document.getElementById('regStrengthBar');
  var score = 0;
  if (pwd.length >= 8)  score++;
  if (pwd.length >= 12) score++;
  if (/[A-Z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  var pct = Math.min(100, score * 20);
  var colors = ['', '#E5484D', '#E5484D', '#f59e0b', '#30A46C', '#30A46C'];
  bar.style.width = pct + '%';
  bar.style.background = colors[score] || '';
}
</script>

</body>
</html>

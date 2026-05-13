<?php
// Avvia la sessione qui per poter leggere la lingua scelta in precedenza
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require "../requirement/pdo.php";
require "../requirement/mail_config.php";
require "../requirement/logger.php";
require "../requirement/i18n.php";

// Se l'utente cambia lingua dalla query string (?lang=en|it)
if (isset($_GET["lang"]) && setCurrentLanguage($_GET["lang"])) {
    header("Location: login.php" . (isset($_GET["mode"]) ? "?mode=" . urlencode($_GET["mode"]) : ""));
    exit();
}

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
        $message_login = __("auth.err_invalid_email");
    } elseif (empty($password)) {
        $message_login = __("auth.err_password_req");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash_master, email_verified FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if (!$user) {
            $message_login = __("auth.err_user_not_found");
        } elseif ($user["email_verified"] == 0) {
            $message_login = __("auth.err_email_unverified");
        } elseif (password_verify($password, $user["password_hash_master"])) {
            // Sessione già avviata in cima al file: rigenera solo l'ID
            session_regenerate_id(true);
            $_SESSION["user_id"]       = $user["id"];
            $_SESSION["username"]      = $user["username"];
            $_SESSION["last_activity"] = time();

            // Conta i fallimenti recenti per rilevare attività sospetta
            $failStmt = $pdo->prepare("
                SELECT COUNT(*) FROM activity_log
                WHERE user_id = :uid
                  AND action_type = 'login_failed'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $failStmt->execute([":uid" => $user["id"]]);
            $recentFails = (int) $failStmt->fetchColumn();
            if ($recentFails >= 3) {
                $_SESSION["security_alert_pending"] = $recentFails;
            }

            logActivity($user["id"], "login", "Login effettuato");
            header("Location: ../dashboard/main.php");
            exit();
        } else {
            // Login fallito: registra l'IP per rilevare tentativi sospetti
            logActivity($user["id"], "login_failed", "Password errata");
            $message_login = __("auth.err_wrong_password");
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
        $message_register = __("auth.err_username_req");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_register = __("auth.err_invalid_email");
    } elseif (strlen($password) < 8) {
        $message_register = __("auth.err_password_short");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message_register = __("auth.err_email_taken");
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
                $message_register = __("auth.msg_registered_ok");
            } else {
                $message_register = __("auth.msg_registered_no_mail");
            }
        }
    }
}

// ── Forgot password ───────────────────────────────────────────────────────────
if ($mode === 'forgot' && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'forgot') {
    $email = trim($_POST["email_forgot"] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_forgot = __("auth.err_invalid_email");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch();
        if (!$user) {
            $message_forgot = __("auth.err_user_not_found");
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
                $message_forgot = __("auth.msg_check_email");
            } else {
                $message_forgot = __("auth.msg_mail_error");
            }
        }
    }
}

if (isset($_GET["timeout"])) {
    $message_login = __("auth.msg_session_expired");
    $mode = 'signin';
}

if (isset($_GET["verified"])) {
    $v = $_GET["verified"];
    if ($v === '1')       $message_login = __("auth.msg_email_verified");
    elseif ($v === 'expired') $message_login = __("auth.msg_link_expired");
    elseif ($v === 'invalid') $message_login = __("auth.msg_link_invalid");
    $mode = 'signin';
}

$currentLang = currentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLang); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — <?php
    if ($mode === 'signup')      echo htmlspecialchars(__("auth.create_account"));
    elseif ($mode === 'forgot')  echo htmlspecialchars(__("auth.reset_password"));
    else                          echo htmlspecialchars(__("auth.sign_in"));
  ?></title>
  <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>

<!-- Language switcher -->
<div class="auth-lang-switcher" style="position:fixed;top:16px;right:16px;display:flex;gap:6px;font-size:12px;z-index:10;">
  <a id="langEn" href="?lang=en<?php echo $mode !== 'signin' ? '&mode=' . htmlspecialchars($mode) : ''; ?>" style="<?php echo $currentLang === 'en' ? 'font-weight:600;color:var(--accent);' : 'color:var(--fg-muted);'; ?>">EN</a>
  <span style="color:var(--fg-muted);">|</span>
  <a id="langIt" href="?lang=it<?php echo $mode !== 'signin' ? '&mode=' . htmlspecialchars($mode) : ''; ?>" style="<?php echo $currentLang === 'it' ? 'font-weight:600;color:var(--accent);' : 'color:var(--fg-muted);'; ?>">IT</a>
</div>

<div class="auth-shell">
  <div class="auth-card mode-<?php echo htmlspecialchars($mode); ?>" id="authCard">

    <!-- Dark sliding panel -->
    <div class="auth-panel-dark">
      <div class="auth-dark-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        <span><span class="logo-key">Key</span>Manager</span>
      </div>
      <div class="auth-dark-tagline" id="panelTagline">
        <?php
          $taglineKey = $mode === 'signup' ? 'auth.panel_signup' : ($mode === 'forgot' ? 'auth.panel_forgot' : 'auth.panel_signin');
          $tagline = explode("|", __($taglineKey));
          echo '<h2 id="panelTitle">' . htmlspecialchars($tagline[0]) . '<br><span>' . htmlspecialchars($tagline[1] ?? "") . '</span></h2>';
        ?>
      </div>
      <div class="auth-dark-stats">
        <div class="stat-item"><strong>256-bit</strong><span>Encryption</span></div>
        <div class="stat-item"><strong>2M+</strong><span>Users</span></div>
        <div class="stat-item"><strong>99.9%</strong><span>Uptime</span></div>
      </div>
    </div>

    <!-- Sign-In pane (right) -->
    <div class="auth-form-pane auth-pane-signin" id="paneSignin">

      <div class="auth-form-header">
        <h1><?php echo htmlspecialchars(__("auth.welcome_back")); ?></h1>
        <p><?php echo htmlspecialchars(__("auth.signin_subtitle")); ?></p>
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
          <input type="email" name="email" placeholder="<?php echo htmlspecialchars(__("auth.email")); ?>" required autocomplete="email">
        </div>
        <div>
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" id="loginPassword" placeholder="<?php echo htmlspecialchars(__("auth.master_password")); ?>" required autocomplete="current-password">
            <button type="button" class="input-action" onclick="togglePwd('loginPassword',this)" aria-label="Mostra password">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="form-row-meta" style="margin-top:8px;">
            <a href="#" onclick="switchMode('forgot');return false;"><?php echo htmlspecialchars(__("auth.forgot_password")); ?></a>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__("auth.sign_in")); ?></button>
      </form>

      <p class="form-footer-text"><?php echo htmlspecialchars(__("auth.no_account")); ?> <a href="#" onclick="switchMode('signup');return false;"><?php echo htmlspecialchars(__("auth.sign_up")); ?></a></p>

    </div>

    <!-- Forgot password pane (left, mirrors signup) -->
    <div class="auth-form-pane auth-pane-forgot" id="paneForgot">

      <div class="auth-icon-wrap">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <div class="auth-form-header">
        <h1><?php echo htmlspecialchars(__("auth.reset_password")); ?></h1>
        <p><?php echo htmlspecialchars(__("auth.reset_subtitle")); ?></p>
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
          <input type="email" name="email_forgot" placeholder="<?php echo htmlspecialchars(__("auth.email")); ?>" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__("auth.send_reset")); ?></button>
      </form>

      <p class="form-footer-text"><a href="#" onclick="switchMode('signin');return false;"><?php echo htmlspecialchars(__("auth.back_to_signin")); ?></a></p>

    </div>

    <!-- Sign-Up pane (left) -->
    <div class="auth-form-pane auth-pane-signup" id="paneSignup">

      <div class="auth-form-header">
        <h1><?php echo htmlspecialchars(__("auth.create_account")); ?></h1>
        <p><?php echo htmlspecialchars(__("auth.signup_subtitle")); ?></p>
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
          <input type="text" name="username" placeholder="<?php echo htmlspecialchars(__("auth.full_name")); ?>" required autocomplete="name">
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
          <input type="email" name="email_reg" placeholder="<?php echo htmlspecialchars(__("auth.email")); ?>" required autocomplete="email">
        </div>
        <div>
          <div class="input-group">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password_reg" id="regPassword" placeholder="<?php echo htmlspecialchars(__("auth.master_password")); ?>" required minlength="8"
              autocomplete="new-password" oninput="updateStrength(this.value)">
          </div>
          <div class="strength-bar-wrap" style="margin-top:6px;">
            <div class="strength-bar" id="regStrengthBar"></div>
          </div>
        </div>
        <div class="input-group">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input type="password" name="confirm_password" id="regConfirm" placeholder="<?php echo htmlspecialchars(__("auth.confirm_password")); ?>" required minlength="8" autocomplete="new-password">
          <button type="button" class="input-action" onclick="togglePwd('regConfirm',this)" aria-label="Mostra password">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <label class="checkbox-row">
          <input type="checkbox" required>
          <?php echo htmlspecialchars(__("auth.terms_agree")); ?>
        </label>
        <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__("auth.create_account")); ?></button>
      </form>

      <p class="form-footer-text"><?php echo htmlspecialchars(__("auth.have_account")); ?> <a href="#" onclick="switchMode('signin');return false;"><?php echo htmlspecialchars(__("auth.sign_in")); ?></a></p>

    </div>

  </div>
</div>

<script>
var card = document.getElementById('authCard');

var panelTexts = (function () {
    var signin = <?php echo json_encode(__("auth.panel_signin")); ?>.split('|');
    var signup = <?php echo json_encode(__("auth.panel_signup")); ?>.split('|');
    var forgot = <?php echo json_encode(__("auth.panel_forgot")); ?>.split('|');
    function fmt(parts) {
        return parts[0] + '<br><span>' + (parts[1] || '') + '<\/span>';
    }
    return { signin: fmt(signin), signup: fmt(signup), forgot: fmt(forgot) };
})();

var modeSwitchInFlight = false;

function switchMode(mode) {
  var title = document.getElementById('panelTitle');
  var currentMode = (card.className.match(/mode-(\w+)/) || [])[1];

  // History + lang links update immediately
  var qs = '?';
  if (mode === 'signup')      qs = '?mode=signup';
  else if (mode === 'forgot') qs = '?mode=forgot';
  history.replaceState({}, '', qs);
  updateLangLinks(mode);

  if (mode === currentMode) {
    title.innerHTML = panelTexts[mode];
    return;
  }

  // Respect prefers-reduced-motion: instant swap, no choreography
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion) {
    card.className = 'auth-card mode-' + mode;
    title.innerHTML = panelTexts[mode];
    return;
  }

  if (modeSwitchInFlight) return;
  modeSwitchInFlight = true;

  // Title fade is intentionally slower than the panel slide:
  //  t=0      fade-out title (0.82s)            |  panel slide starts (0.85s)
  //  t=820    title fully hidden -> swap text   |  panel has just settled
  //  t=840    fade-in title (0.82s)             |
  //  t=1660   title fully visible
  card.classList.add('is-animating');
  title.classList.add('is-changing');

  // Trigger panel slide on the next frame so the title fade-out starts cleanly
  requestAnimationFrame(function () {
    card.className = 'auth-card mode-' + mode + ' is-animating';
  });

  // Swap title text while invisible (panel finishes ~at the same moment)
  setTimeout(function () {
    title.innerHTML = panelTexts[mode];
  }, 820);

  // Slow fade-in of the new title
  setTimeout(function () {
    title.classList.remove('is-changing');
  }, 840);

  // Release once both the panel and the slow title fade-in have completed
  setTimeout(function () {
    card.classList.remove('is-animating');
    modeSwitchInFlight = false;
  }, 1720);
}

function updateLangLinks(mode) {
  var modeParam = (mode && mode !== 'signin') ? ('&mode=' + encodeURIComponent(mode)) : '';
  var en = document.getElementById('langEn');
  var it = document.getElementById('langIt');
  if (en) en.href = '?lang=en' + modeParam;
  if (it) it.href = '?lang=it' + modeParam;
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

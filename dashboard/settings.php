<?php
session_start();

// Controlla che l'utente sia loggato
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";

// Controlla che la sessione non sia scaduta
checkSessionTimeout();

$csrfToken = getCsrfToken();
$userId = (int) $_SESSION["user_id"];

// Recupera i dati dell'utente
$stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = :id");
$stmt->execute([":id" => $userId]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — Settings</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
$username = $_SESSION["username"] ?? "User";
$avatarLetter = strtoupper(substr($username, 0, 1));
?>

<div id="globalMessage"></div>

<div class="settings-shell">

  <!-- Top Bar -->
  <header class="topbar">
    <a href="main.php" class="topbar-logo">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
      <span><span class="logo-key">Key</span>Manager</span>
    </a>
    <div class="topbar-search">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" placeholder="Search settings...">
    </div>
    <div class="topbar-actions">
      <a href="settings.php" class="btn-icon" title="Settings">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
      </a>
      <div class="avatar"><?php echo $avatarLetter; ?></div>
    </div>
  </header>

  <div class="settings-body">

    <!-- Sidebar Nav -->
    <nav class="settings-sidebar">
      <div class="sidebar-section">Settings</div>
      <a href="#general" class="settings-nav-item active" onclick="showTab('general',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        General
      </a>
      <a href="#account" class="settings-nav-item" onclick="showTab('account',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
        Account
      </a>
      <a href="#security" class="settings-nav-item" onclick="showTab('security',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Security
      </a>
      <a href="#activity" class="settings-nav-item" onclick="showTab('activity',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Activity Log
      </a>
      <div class="divider" style="margin:8px 0;"></div>
      <a href="main.php" class="settings-nav-item">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Dashboard
      </a>
      <a href="../auth/logout.php" class="settings-nav-item" style="color:var(--danger);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
      </a>
    </nav>

    <!-- Content Area -->
    <main class="settings-content">

      <!-- GENERAL TAB -->
      <div id="tab-general">
        <div class="settings-heading">
          <h1>General</h1>
          <p>Configure how KeyManager looks and behaves</p>
        </div>

        <div class="settings-section">
          <div class="settings-section-title">Appearance</div>
          <div class="theme-options">
            <button class="theme-btn active" onclick="setTheme('light',this)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
              Light
            </button>
            <button class="theme-btn" onclick="setTheme('dark',this)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
              Dark
            </button>
            <button class="theme-btn" onclick="setTheme('system',this)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              System
            </button>
          </div>
        </div>

        <div class="settings-section">
          <div class="settings-section-title">Preferences</div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Language</h3>
              <p>Choose your interface language</p>
            </div>
            <select class="field-select">
              <option>English (US)</option>
              <option>Italiano</option>
            </select>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Auto-lock timer</h3>
              <p>Lock vault after inactivity</p>
            </div>
            <div class="timer-options">
              <button class="timer-btn" onclick="setTimer(this)">5 min</button>
              <button class="timer-btn active" onclick="setTimer(this)">30 min</button>
              <button class="timer-btn" onclick="setTimer(this)">1 hour</button>
            </div>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Browser Autofill</h3>
              <p>Fill logins automatically on supported sites</p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="autofillToggleSettings" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Clipboard auto-clear</h3>
              <p>Clear copied passwords after 30 seconds</p>
            </div>
            <label class="toggle">
              <input type="checkbox" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>

        <div class="sync-bar">
          <div class="sync-bar-info">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
            <div>
              <strong>All devices in sync</strong>
              <span>Last synced just now</span>
            </div>
          </div>
          <button class="btn btn-secondary btn-sm">Sync now</button>
        </div>
      </div>

      <!-- ACCOUNT TAB -->
      <div id="tab-account" class="hidden">
        <div class="settings-heading">
          <h1>Account</h1>
          <p>Your profile and account details</p>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Profile</div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Username</h3></div>
            <span style="font-size:14px;color:var(--fg-secondary);"><?php echo htmlspecialchars($user["username"] ?? ""); ?></span>
          </div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Email</h3></div>
            <span style="font-size:14px;color:var(--fg-secondary);"><?php echo htmlspecialchars($user["email"] ?? ""); ?></span>
          </div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Member since</h3></div>
            <span style="font-size:14px;color:var(--fg-secondary);"><?php echo htmlspecialchars($user["created_at"] ?? ""); ?></span>
          </div>
        </div>
      </div>

      <!-- SECURITY TAB -->
      <div id="tab-security" class="hidden">
        <div class="settings-heading">
          <h1>Security</h1>
          <p>Manage your master password and security settings</p>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Change Master Password</div>
          <div id="changePasswordMessage" style="font-size:13px;margin-bottom:8px;"></div>
          <form id="changePasswordForm" style="display:flex;flex-direction:column;gap:14px;max-width:440px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
            <div>
              <label class="field-label">Current password</label>
              <input type="password" name="current_password" id="currentPassword" class="field-input" required>
            </div>
            <div>
              <label class="field-label">New password</label>
              <input type="password" name="new_password" id="newPassword" class="field-input" required
                oninput="updateSettingsStrength(this.value)">
              <div class="strength-bar-wrap" style="margin-top:6px;">
                <div class="strength-bar" id="settingsStrBar"></div>
              </div>
              <span id="settingsPasswordStrength" style="font-size:12px;color:var(--fg-muted);margin-top:4px;display:block;"></span>
            </div>
            <div>
              <label class="field-label">Confirm new password</label>
              <input type="password" name="confirm_password" id="confirmPassword" class="field-input" required>
            </div>
            <div>
              <button type="submit" class="btn btn-primary" style="width:auto;align-self:flex-start;">Update Password</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ACTIVITY TAB -->
      <div id="tab-activity" class="hidden">
        <div class="settings-heading">
          <h1>Activity Log</h1>
          <p>Recent account activity</p>
        </div>
        <p style="font-size:13px;color:var(--fg-secondary);">
          <a href="activity_log.php" class="btn btn-secondary btn-sm">View full activity log →</a>
        </p>
      </div>

    </main>
  </div>
</div>

<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;

function showMessage(message, isSuccess) {
    const box = document.getElementById('globalMessage');
    box.textContent = message;
    box.classList.add('show');
    setTimeout(function() { box.classList.remove('show'); }, 3000);
}

function showTab(name, link) {
    document.querySelectorAll('[id^="tab-"]').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-' + name).classList.remove('hidden');
    document.querySelectorAll('.settings-nav-item').forEach(el => el.classList.remove('active'));
    if (link) link.classList.add('active');
}

function setTheme(theme, btn) {
    document.querySelectorAll('.theme-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function setTimer(btn) {
    document.querySelectorAll('.timer-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function updateSettingsStrength(pwd) {
    const bar = document.getElementById('settingsStrBar');
    const label = document.getElementById('settingsPasswordStrength');
    let score = 0;
    if (pwd.length >= 8)  score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    bar.style.width = Math.min(100, score * 20) + '%';
    bar.style.background = ['','#E5484D','#E5484D','#f59e0b','#30A46C','#30A46C'][score] || '';
    label.textContent = ['','Weak','Weak','Fair','Strong','Very strong'][score] || '';
    label.style.color = bar.style.background;
}

document.getElementById('changePasswordForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    const msgEl = document.getElementById('changePasswordMessage');
    try {
        const response = await fetch('settings/change_password.php', { method: 'POST', body: formData });
        const raw = await response.text();
        let data;
        try { data = JSON.parse(raw); } catch { data = { success: false, message: 'Invalid server response' }; }
        msgEl.textContent = data.message || '';
        msgEl.style.color = data.success ? 'var(--success)' : 'var(--danger)';
        if (data.success) {
            this.reset();
            document.getElementById('settingsPasswordStrength').textContent = '';
            document.getElementById('settingsStrBar').style.width = '0';
        }
    } catch { msgEl.textContent = 'Request error'; msgEl.style.color = 'var(--danger)'; }
});

// Handle hash-based tab navigation
(function() {
    const hash = window.location.hash.replace('#','');
    if (hash) {
        const link = document.querySelector('.settings-nav-item[href="#' + hash + '"]');
        if (link) showTab(hash, link);
    }
})();
</script>

</body>
</html>

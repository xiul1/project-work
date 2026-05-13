<?php
session_start();

// Controlla che l'utente sia loggato
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";
require "../requirement/preferences.php";
require "../requirement/i18n.php";

// Controlla che la sessione non sia scaduta
checkSessionTimeout();

$csrfToken = getCsrfToken();
$userId = (int) $_SESSION["user_id"];

// Recupera i dati dell'utente
$stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = :id");
$stmt->execute([":id" => $userId]);
$user = $stmt->fetch();

// Carica preferenze utente e allinea la sessione
$prefs = getUserPreferences($userId);
$_SESSION["language"] = $prefs[PREF_KEY_LANGUAGE];
$_SESSION["auto_lock_minutes"] = (int) $prefs[PREF_KEY_AUTO_LOCK];
$_SESSION["theme"] = $prefs[PREF_KEY_THEME];
$lang = currentLanguage();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" data-theme="<?php echo htmlspecialchars($prefs[PREF_KEY_THEME]); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — <?php echo htmlspecialchars(__("settings.title")); ?></title>
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
      <a href="#billing" class="settings-nav-item" onclick="showTab('billing',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        Billing
      </a>
      <a href="#notifications" class="settings-nav-item" onclick="showTab('notifications',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
      </a>
      <a href="#integrations" class="settings-nav-item" onclick="showTab('integrations',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        Integrations
      </a>
      <a href="#about" class="settings-nav-item" onclick="showTab('about',this)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        About
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
          <h1><?php echo htmlspecialchars(__("general.heading")); ?></h1>
          <p><?php echo htmlspecialchars(__("general.subheading")); ?></p>
        </div>

        <div class="settings-section">
          <div class="settings-section-title"><?php echo htmlspecialchars(__("general.appearance")); ?></div>
          <div class="theme-options" id="themeOptions">
            <button class="theme-btn" data-theme="light">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
              <?php echo htmlspecialchars(__("general.theme_light")); ?>
            </button>
            <button class="theme-btn" data-theme="dark">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
              <?php echo htmlspecialchars(__("general.theme_dark")); ?>
            </button>
            <button class="theme-btn" data-theme="system">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              <?php echo htmlspecialchars(__("general.theme_system")); ?>
            </button>
          </div>
        </div>

        <div class="settings-section">
          <div class="settings-section-title"><?php echo htmlspecialchars(__("general.preferences")); ?></div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3><?php echo htmlspecialchars(__("general.language")); ?></h3>
              <p><?php echo htmlspecialchars(__("general.language_desc")); ?></p>
            </div>
            <select id="languageSelect" class="field-select">
              <option value="en" <?php echo $prefs[PREF_KEY_LANGUAGE] === "en" ? "selected" : ""; ?>>English (US)</option>
              <option value="it" <?php echo $prefs[PREF_KEY_LANGUAGE] === "it" ? "selected" : ""; ?>>Italiano</option>
            </select>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Default Vault</h3>
              <p>Used when saving new credentials</p>
            </div>
            <select class="field-select">
              <option>Personal</option>
              <option>Work</option>
            </select>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3><?php echo htmlspecialchars(__("general.auto_lock")); ?></h3>
              <p><?php echo htmlspecialchars(__("general.auto_lock_desc")); ?></p>
            </div>
            <div class="timer-options" id="timerOptions">
              <button class="timer-btn" data-minutes="5"><?php echo htmlspecialchars(__("general.minutes_5")); ?></button>
              <button class="timer-btn" data-minutes="30"><?php echo htmlspecialchars(__("general.minutes_30")); ?></button>
              <button class="timer-btn" data-minutes="60"><?php echo htmlspecialchars(__("general.hour_1")); ?></button>
            </div>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3><?php echo htmlspecialchars(__("general.autofill")); ?></h3>
              <p><?php echo htmlspecialchars(__("general.autofill_desc")); ?></p>
              <p id="autofillSettingsMessage" style="font-size:11px;color:var(--fg-muted);margin-top:2px;"></p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="autofillToggleSettings" checked>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3><?php echo htmlspecialchars(__("general.clipboard_clear")); ?></h3>
              <p><?php echo htmlspecialchars(__("general.clipboard_clear_desc")); ?></p>
            </div>
            <label class="toggle">
              <input type="checkbox" id="clipboardClearToggle" data-pref-key="clipboard_clear" <?php echo $prefs[PREF_KEY_CLIPBOARD_CLEAR] === "1" ? "checked" : ""; ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3><?php echo htmlspecialchars(__("general.analytics")); ?></h3>
              <p><?php echo htmlspecialchars(__("general.analytics_desc")); ?></p>
            </div>
            <label class="toggle">
              <input type="checkbox">
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

      <!-- BILLING TAB -->
      <div id="tab-billing" class="hidden">
        <div class="settings-heading">
          <h1>Billing</h1>
          <p>Manage your subscription and payment methods</p>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Current Plan</div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Free Plan</h3><p>Unlimited credentials, local sync</p></div>
            <button class="btn btn-primary btn-sm" style="width:auto;">Upgrade</button>
          </div>
        </div>
      </div>

      <!-- NOTIFICATIONS TAB -->
      <div id="tab-notifications" class="hidden">
        <div class="settings-heading">
          <h1>Notifications</h1>
          <p>Choose what you get notified about</p>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Alerts</div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Security alerts</h3><p>Notify on suspicious login attempts</p></div>
            <label class="toggle"><input type="checkbox" data-pref-key="security_alerts" <?php echo $prefs[PREF_KEY_SECURITY_ALERTS] === "1" ? "checked" : ""; ?>><span class="toggle-slider"></span></label>
          </div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Weak password alerts</h3><p>Alert when a password is weak</p></div>
            <label class="toggle"><input type="checkbox" data-pref-key="weak_pwd_alerts" <?php echo $prefs[PREF_KEY_WEAK_PWD_ALERTS] === "1" ? "checked" : ""; ?>><span class="toggle-slider"></span></label>
          </div>
        </div>
      </div>

      <!-- INTEGRATIONS TAB -->
      <div id="tab-integrations" class="hidden">
        <div class="settings-heading">
          <h1>Integrations</h1>
          <p>Connect KeyManager with other tools</p>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Browser Extension</div>
          <div class="settings-row">
            <div class="settings-row-info"><h3>Browser Autofill</h3><p>Install the extension to autofill credentials</p></div>
            <button class="btn btn-secondary btn-sm" style="width:auto;">Install Extension</button>
          </div>
        </div>
        <div class="settings-section">
          <div class="settings-section-title">Import / Export</div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Import CSV</h3>
              <p>Import credentials from a CSV file</p>
            </div>
            <button class="btn btn-secondary btn-sm" style="width:auto;" onclick="openImportCsvModal()">Import CSV</button>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Import JSON</h3>
              <p>Import credentials from a JSON file</p>
            </div>
            <button class="btn btn-secondary btn-sm" style="width:auto;" onclick="openImportJsonModal()">Import JSON</button>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Export CSV</h3>
              <p>Download all credentials as a CSV file</p>
            </div>
            <button class="btn btn-secondary btn-sm" style="width:auto;" onclick="exportCredentials('csv')">Export CSV</button>
          </div>
          <div class="settings-row">
            <div class="settings-row-info">
              <h3>Export JSON</h3>
              <p>Download all credentials as a JSON file</p>
            </div>
            <button class="btn btn-secondary btn-sm" style="width:auto;" onclick="exportCredentials('json')">Export JSON</button>
          </div>
        </div>
      </div>

      <!-- ABOUT TAB -->
      <div id="tab-about" class="hidden">
        <div class="settings-heading">
          <h1>About</h1>
          <p>KeyManager — Secure credential management</p>
        </div>
        <div class="settings-section">
          <div class="settings-row"><div class="settings-row-info"><h3>Version</h3></div><span style="font-size:13px;color:var(--fg-secondary);">1.0.0</span></div>
          <div class="settings-row"><div class="settings-row-info"><h3>Encryption</h3></div><span style="font-size:13px;color:var(--fg-secondary);">AES-256</span></div>
          <div class="settings-row"><div class="settings-row-info"><h3>Storage</h3></div><span style="font-size:13px;color:var(--fg-secondary);">Local (MySQL)</span></div>
        </div>
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

const PREF_ENDPOINT = 'settings/update_preference.php';
const CURRENT_THEME = <?php echo json_encode($prefs[PREF_KEY_THEME]); ?>;
const CURRENT_LANGUAGE = <?php echo json_encode($prefs[PREF_KEY_LANGUAGE]); ?>;
const CURRENT_AUTO_LOCK = <?php echo json_encode($prefs[PREF_KEY_AUTO_LOCK]); ?>;

function initPreferenceControls() {
    // Theme buttons
    if (typeof ThemeManager !== 'undefined') {
        ThemeManager.bindThemeButtons('#themeOptions', CURRENT_THEME, function (theme) {
            ThemeManager.persistTheme(theme, csrfToken, PREF_ENDPOINT);
        });
    }

    // Language select
    const langSelect = document.getElementById('languageSelect');
    if (langSelect) {
        langSelect.addEventListener('change', async function () {
            const value = langSelect.value;
            const result = await PreferencesClient.savePreference('language', value, csrfToken, PREF_ENDPOINT);
            if (result && result.success) {
                location.reload();
            } else {
                showMessage(result.message || 'Error');
            }
        });
    }

    // Timer buttons
    const timerBtns = document.querySelectorAll('#timerOptions .timer-btn');
    timerBtns.forEach(function (btn) {
        if (btn.getAttribute('data-minutes') === String(CURRENT_AUTO_LOCK)) {
            btn.classList.add('active');
        }
        btn.addEventListener('click', async function () {
            timerBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const minutes = btn.getAttribute('data-minutes');
            const result = await PreferencesClient.savePreference('auto_lock', minutes, csrfToken, PREF_ENDPOINT);
            if (result && result.success) {
                showMessage('Auto-lock updated');
            }
        });
    });

    // Generic preference toggles (any checkbox with data-pref-key)
    document.querySelectorAll('input[type="checkbox"][data-pref-key]').forEach(function (input) {
        input.addEventListener('change', async function () {
            const key = input.getAttribute('data-pref-key');
            const value = input.checked ? '1' : '0';
            const result = await PreferencesClient.savePreference(key, value, csrfToken, PREF_ENDPOINT);
            if (!result || !result.success) {
                input.checked = !input.checked;
                showMessage(result && result.message ? result.message : 'Could not save');
            }
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
        AutofillToggle.createAutofillToggle("autofillToggleSettings", "autofillSettingsMessage");
        initPreferenceControls();
    });
} else {
    AutofillToggle.createAutofillToggle("autofillToggleSettings", "autofillSettingsMessage");
    initPreferenceControls();
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

<script src="../assets/js/theme.js"></script>
<script src="../assets/js/preferences-client.js"></script>
<script src="../assets/js/clipboard.js"></script>
<script src="../assets/js/autofill-toggle.js"></script>
<script src="../assets/js/import-export.js"></script>

<!-- IMPORT CSV MODAL -->
<div id="importCsvModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Import from CSV</h2>
      <button class="btn-icon" onclick="closeImportCsvModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--fg-secondary);">
        The CSV file must have these columns in order:<br>
        <code style="background:var(--surface-secondary);padding:4px 8px;border-radius:4px;display:inline-block;margin-top:6px;font-size:12px;">service, username, password, url (opt.), notes (opt.)</code><br>
        The first row (header) is skipped.
      </p>
      <form id="importCsvForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <div style="margin-top:12px;">
          <label class="field-label">CSV File</label>
          <input type="file" name="csv_file" accept=".csv" required class="field-input" style="padding:8px;">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeImportCsvModal()">Cancel</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('importCsvForm').requestSubmit()">Import</button>
    </div>
  </div>
</div>

<!-- IMPORT JSON MODAL -->
<div id="importJsonModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Import from JSON</h2>
      <button class="btn-icon" onclick="closeImportJsonModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--fg-secondary);">
        The JSON file must be an array of objects with these fields:<br>
        <code style="background:var(--surface-secondary);padding:4px 8px;border-radius:4px;display:inline-block;margin-top:6px;font-size:12px;">service_name, username, password, url (opt.), notes (opt.)</code>
      </p>
      <form id="importJsonForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <div style="margin-top:12px;">
          <label class="field-label">JSON File</label>
          <input type="file" name="json_file" accept=".json" required class="field-input" style="padding:8px;">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeImportJsonModal()">Cancel</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('importJsonForm').requestSubmit()">Import</button>
    </div>
  </div>
</div>

<script>
function exportCredentials(format) {
    const form = ImportExport.buildExportForm(format, csrfToken, 'credential/export_credentials.php');
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function openImportCsvModal() { document.getElementById('importCsvModal').classList.remove('hidden'); }
function closeImportCsvModal() {
    document.getElementById('importCsvModal').classList.add('hidden');
    document.getElementById('importCsvForm').reset();
}

function openImportJsonModal() { document.getElementById('importJsonModal').classList.remove('hidden'); }
function closeImportJsonModal() {
    document.getElementById('importJsonModal').classList.add('hidden');
    document.getElementById('importJsonForm').reset();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('importCsvForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        try {
            const response = await fetch('credential/import_credentials.php', { method: 'POST', body: formData });
            const data = ImportExport.parseImportResponse(await response.text());
            showMessage(data.message || '');
            closeImportCsvModal();
            if (data.success && data.imported > 0) {
                setTimeout(function () { location.href = 'main.php'; }, 1500);
            }
        } catch { showMessage('Import error'); }
    });

    document.getElementById('importJsonForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const formData = new FormData(this);
        try {
            const response = await fetch('credential/import_credentials_json.php', { method: 'POST', body: formData });
            const data = ImportExport.parseImportResponse(await response.text());
            showMessage(data.message || '');
            closeImportJsonModal();
            if (data.success && data.imported > 0) {
                setTimeout(function () { location.href = 'main.php'; }, 1500);
            }
        } catch { showMessage('Import error'); }
    });

    // Navigate directly to integrations tab if hash is present
    if (window.location.hash === '#integrations') {
        const link = document.querySelector('.settings-nav-item[href="#integrations"]');
        if (link) showTab('integrations', link);
    }
});
</script>

</body>
</html>

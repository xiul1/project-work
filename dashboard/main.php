<?php
session_start();

if(!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";
require "../requirement/preferences.php";
require "../requirement/i18n.php";
require "../requirement/logger.php";

// Carica preferenze utente per allineare la sessione prima del timeout check
$userId = (int) $_SESSION["user_id"];
$prefs = getUserPreferences($userId);
$_SESSION["auto_lock_minutes"] = (int) $prefs[PREF_KEY_AUTO_LOCK];
$_SESSION["theme"] = $prefs[PREF_KEY_THEME];
$_SESSION["language"] = $prefs[PREF_KEY_LANGUAGE];

// Banner di sicurezza: tentativi falliti recenti, mostrato solo se l'utente
// ha attivato gli alert ed esiste un evento sospetto pendente in sessione.
$securityAlertCount = 0;
if ($prefs[PREF_KEY_SECURITY_ALERTS] === "1" && isset($_SESSION["security_alert_pending"])) {
    $securityAlertCount = (int) $_SESSION["security_alert_pending"];
    unset($_SESSION["security_alert_pending"]);
    // Audit: registra che il banner è stato effettivamente mostrato all'utente.
    logActivity($userId, "security_alert_shown", "Failed logins in 24h: $securityAlertCount");
}

// Controlla se la sessione è scaduta per inattività
checkSessionTimeout();

$csrfToken = getCsrfToken();

$stmt = $pdo->prepare("
SELECT * FROM credenziali
WHERE user_id = :uid
ORDER BY updated_at DESC, created_at DESC
");
$stmt->execute([":uid" => $_SESSION["user_id"]]);
$credentials = $stmt->fetchAll();

// Stale passwords (not updated in 6+ months)
$staleStmt = $pdo->prepare("SELECT COUNT(*) FROM credenziali WHERE user_id = :uid AND (updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) OR (updated_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)))");
$staleStmt->execute([":uid" => $_SESSION["user_id"]]);
$weakCount = (int) $staleStmt->fetchColumn();

// Favorites = 3 most recent credentials
$favStmt = $pdo->prepare("SELECT service_name, username FROM credenziali WHERE user_id = :uid ORDER BY updated_at DESC, created_at DESC LIMIT 3");
$favStmt->execute([":uid" => $_SESSION["user_id"]]);
$favorites = $favStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($prefs[PREF_KEY_LANGUAGE]); ?>" data-theme="<?php echo htmlspecialchars($prefs[PREF_KEY_THEME]); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyManager — Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
$username = $_SESSION["username"] ?? "User";
$avatarLetter = strtoupper(substr($username, 0, 1));
$totalCreds = count($credentials);
?>

<div id="globalMessage"></div>

<?php if ($securityAlertCount > 0): ?>
<div class="security-alert-banner" style="background:rgba(229,72,77,0.12);border:1px solid var(--danger);color:var(--danger);padding:10px 14px;margin:12px 24px 0;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px;">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  <span><?php echo htmlspecialchars(sprintf(__("dash.security_alert"), $securityAlertCount)); ?></span>
</div>
<?php endif; ?>

<div class="app-shell">

  <!-- Top Bar -->
  <header class="topbar">
    <a href="main.php" class="topbar-logo">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent)"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
      <span><span class="logo-key">Key</span>Manager</span>
    </a>
    <div class="topbar-search">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="searchInput" placeholder="Search credentials..." oninput="filterCredentials()">
    </div>
    <div class="topbar-actions">
      <a href="settings.php" class="btn-icon" title="Settings">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
      </a>
      <div class="avatar" title="<?php echo htmlspecialchars($username); ?>"><?php echo $avatarLetter; ?></div>
    </div>
  </header>

  <!-- Main content -->
  <div class="app-content">

    <div class="app-main">

      <!-- Quick Actions -->
      <div>
        <div class="quick-actions">
          <div class="action-card" onclick="openAddModal()">
            <div class="action-card-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </div>
            <div>
              <h3><?php echo htmlspecialchars(__("dash.add_credential")); ?></h3>
              <p><?php echo htmlspecialchars(__("dash.add_credential_desc")); ?></p>
            </div>
          </div>
          <div class="action-card" onclick="openGenerateModal()">
            <div class="action-card-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            </div>
            <div>
              <h3><?php echo htmlspecialchars(__("dash.generate_password")); ?></h3>
              <p><?php echo htmlspecialchars(__("dash.generate_password_desc")); ?></p>
            </div>
          </div>
          <div class="action-card" onclick="openSecurityAudit()">
            <div class="action-card-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect width="7" height="5" x="7" y="7" rx="1"/><rect width="7" height="5" x="10" y="12" rx="1"/></svg>
            </div>
            <div>
              <h3><?php echo htmlspecialchars(__("dash.security_audit")); ?></h3>
              <p><?php echo htmlspecialchars(__("dash.security_audit_desc")); ?></p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Credentials -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <span class="section-title" style="margin-bottom:0;"><?php echo htmlspecialchars(__("dash.recent_credentials")); ?></span>
          <button id="selectModeBtn" class="btn btn-secondary btn-sm" onclick="toggleSelectMode()"><?php echo htmlspecialchars(__("dash.select")); ?></button>
        </div>
        <div id="selectionBar" style="display:none;align-items:center;gap:10px;padding:8px 12px;margin-bottom:8px;background:var(--surface-secondary);border-radius:8px;border:1px solid var(--border);">
          <span id="selectionCount" style="font-size:13px;color:var(--fg-secondary);flex:1;">0 selected</span>
          <button id="bulkDeleteBtn" class="btn btn-danger btn-sm" onclick="bulkDeleteSelected()" disabled><?php echo htmlspecialchars(__("dash.delete_selected")); ?></button>
        </div>
        <div class="credential-list" id="credentialsTableBody">
          <?php foreach($credentials as $cred):
            $initial = strtoupper(substr($cred["service_name"], 0, 1));
            $colors = ['#4A9FD8','#30A46C','#E5484D','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#f97316'];
            $color = $colors[crc32($cred["service_name"]) % count($colors)];
          ?>
          <div class="credential-row"
            data-id="<?php echo $cred["credential_id"]; ?>"
            data-service="<?php echo htmlspecialchars($cred["service_name"], ENT_QUOTES); ?>"
            data-username="<?php echo htmlspecialchars($cred["username"], ENT_QUOTES); ?>"
            data-url="<?php echo htmlspecialchars($cred["url"] ?? "", ENT_QUOTES); ?>"
            data-notes="<?php echo htmlspecialchars($cred["notes"] ?? "", ENT_QUOTES); ?>"
          >
            <input type="checkbox" class="select-checkbox" data-id="<?php echo $cred["credential_id"]; ?>"
              style="display:none;width:16px;height:16px;flex-shrink:0;cursor:pointer;accent-color:var(--accent);"
              onclick="event.stopPropagation();updateSelectionCount()">
            <div class="cred-avatar" style="background:<?php echo $color; ?>1A;color:<?php echo $color; ?>;"><?php echo htmlspecialchars($initial); ?></div>
            <div class="cred-info">
              <div class="cred-service"><?php echo htmlspecialchars($cred["service_name"]); ?></div>
              <div class="cred-username"><?php echo htmlspecialchars($cred["username"]); ?></div>
            </div>
            <div class="cred-actions">
              <button class="btn-circle" onclick="copyUsernameFromRow(this.closest('.credential-row'))" title="Copy username">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
              </button>
              <button class="btn-circle" onclick="openCredentialModal(this.closest('.credential-row'), 'view')" title="More options">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($credentials)): ?>
          <div class="empty-credentials-msg" style="padding:32px;text-align:center;color:var(--fg-muted);font-size:13px;">
            No credentials yet. Add your first one above.
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Sidebar -->
    <aside class="app-sidebar">

      <!-- Vault Summary -->
      <div class="sidebar-card">
        <h3><?php echo htmlspecialchars(__("dash.vault_summary")); ?></h3>
        <div class="vault-stat-big">
          <span class="stat-label"><?php echo htmlspecialchars(__("dash.total_credentials")); ?></span>
          <span class="stat-val"><?php echo $totalCreds; ?></span>
        </div>
        <div class="vault-stat-big">
          <span class="stat-label"><?php echo htmlspecialchars(__("dash.weak_passwords")); ?></span>
          <span class="stat-val<?php echo $weakCount > 0 ? ' danger' : ''; ?>"><?php echo $weakCount; ?></span>
        </div>
        <div class="vault-stat-big">
          <span class="stat-label"><?php echo htmlspecialchars(__("dash.last_sync")); ?></span>
          <span class="stat-val muted"><?php echo htmlspecialchars(__("dash.just_now")); ?></span>
        </div>
      </div>

      <!-- Categories -->
      <div class="sidebar-card">
        <h3><?php echo htmlspecialchars(__("dash.categories")); ?></h3>
        <div class="sidebar-cat-row">
          <div class="cat-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-secondary)"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Logins
          </div>
          <span class="cat-count"><?php echo $totalCreds; ?></span>
        </div>
        <div class="sidebar-cat-row">
          <div class="cat-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-secondary)"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            Cards
          </div>
          <span class="cat-count">0</span>
        </div>
        <div class="sidebar-cat-row">
          <div class="cat-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-secondary)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Secure Notes
          </div>
          <span class="cat-count">0</span>
        </div>
        <div class="sidebar-cat-row">
          <div class="cat-left">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--fg-secondary)"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
            Identity
          </div>
          <span class="cat-count">0</span>
        </div>
      </div>

      <!-- Favorites -->
      <div class="sidebar-card">
        <h3><?php echo htmlspecialchars(__("dash.favorites")); ?></h3>
        <?php if (empty($favorites)): ?>
          <p style="font-size:12px;color:var(--fg-muted);">No credentials yet.</p>
        <?php else: foreach ($favorites as $fav): ?>
        <div class="sidebar-fav-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="var(--accent)" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <span class="fav-label"><?php echo htmlspecialchars($fav["service_name"]); ?></span>
          <span class="fav-user"><?php echo htmlspecialchars($fav["username"]); ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>

    </aside>

  </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2><?php echo htmlspecialchars(__("dash.new_credential")); ?></h2>
      <button class="btn-icon" onclick="closeAddModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="addCredentialForm" class="modal-body">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
      <div>
        <label class="field-label"><?php echo htmlspecialchars(__("dash.service")); ?></label>
        <input type="text" name="service_name" class="field-input" placeholder="e.g. Google, GitHub" required>
      </div>
      <div>
        <label class="field-label"><?php echo htmlspecialchars(__("dash.username")); ?> / Email</label>
        <input type="text" name="username" class="field-input" placeholder="your@email.com" required>
      </div>
      <div>
        <label class="field-label"><?php echo htmlspecialchars(__("dash.password")); ?></label>
        <div class="field-row">
          <input type="password" name="password" id="addPassword" class="field-input" required
            oninput="updateStrengthIndicator(this.value, 'addPasswordStrength')">
          <button type="button" class="btn btn-secondary btn-sm" onclick="fillGeneratedPassword('addPassword','addPasswordStrength')"><?php echo htmlspecialchars(__("dash.generate")); ?></button>
        </div>
        <span id="addPasswordStrength" style="font-size:12px;color:var(--fg-muted);margin-top:4px;display:block;"></span>
      </div>
      <div>
        <label class="field-label"><?php echo htmlspecialchars(__("dash.url")); ?></label>
        <input type="text" name="url" class="field-input" placeholder="https://...">
      </div>
      <div>
        <label class="field-label"><?php echo htmlspecialchars(__("dash.notes")); ?></label>
        <textarea name="notes" class="field-input"></textarea>
      </div>
    </form>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeAddModal()"><?php echo htmlspecialchars(__("dash.cancel")); ?></button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('addCredentialForm').requestSubmit()"><?php echo htmlspecialchars(__("dash.save")); ?></button>
    </div>
  </div>
</div>

<!-- CREDENTIAL DETAIL/EDIT MODAL -->
<div id="credentialModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2 id="modalTitle"><?php echo htmlspecialchars(__("dash.credential")); ?></h2>
      <button class="btn-icon" onclick="closeCredentialModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- View section -->
    <div id="viewSection" class="modal-body">
      <div class="detail-row">
        <span class="detail-label"><?php echo htmlspecialchars(__("dash.service")); ?></span>
        <span class="detail-value" id="modalServiceText"></span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><?php echo htmlspecialchars(__("dash.username")); ?></span>
        <span class="detail-value">
          <span id="modalUsernameText"></span>
          <button class="btn-icon" onclick="copyModalUsername()" title="Copy">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          </button>
        </span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><?php echo htmlspecialchars(__("dash.password")); ?></span>
        <span class="detail-value">
          <span id="modalPasswordText">••••••••</span>
          <button class="btn-icon" id="modalTogglePasswordBtn" onclick="toggleModalPassword()" title="Show">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button class="btn-icon" onclick="copyModalPassword()" title="Copy">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          </button>
        </span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><?php echo htmlspecialchars(__("dash.url")); ?></span>
        <span class="detail-value" id="modalUrlText"></span>
      </div>
      <div class="detail-row">
        <span class="detail-label"><?php echo htmlspecialchars(__("dash.notes")); ?></span>
        <span class="detail-value" id="modalNotesText" style="font-size:13px;color:var(--fg-secondary);"></span>
      </div>
      <input type="hidden" id="modalCredentialId">
    </div>

    <!-- Edit section -->
    <div id="editSection" class="modal-body hidden">
      <form id="editCredentialForm">
        <input type="hidden" name="id" id="editCredentialId">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <div>
          <label class="field-label"><?php echo htmlspecialchars(__("dash.service")); ?></label>
          <input type="text" name="service_name" id="editServiceName" class="field-input" required>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label"><?php echo htmlspecialchars(__("dash.username")); ?> / Email</label>
          <input type="text" name="username" id="editUsername" class="field-input" required>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label"><?php echo htmlspecialchars(__("dash.password")); ?> <span style="font-weight:400;color:var(--fg-muted)"><?php echo htmlspecialchars(__("dash.keep_blank")); ?></span></label>
          <div class="field-row">
            <input type="password" name="password" id="editPassword" class="field-input"
              oninput="updateStrengthIndicator(this.value, 'editPasswordStrength')">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fillGeneratedPassword('editPassword','editPasswordStrength')"><?php echo htmlspecialchars(__("dash.generate")); ?></button>
          </div>
          <span id="editPasswordStrength" style="font-size:12px;color:var(--fg-muted);margin-top:4px;display:block;"></span>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label"><?php echo htmlspecialchars(__("dash.url")); ?></label>
          <input type="text" name="url" id="editUrl" class="field-input">
        </div>
        <div style="margin-top:12px;">
          <label class="field-label"><?php echo htmlspecialchars(__("dash.notes")); ?></label>
          <textarea name="notes" id="editNotes" class="field-input"></textarea>
        </div>
      </form>
    </div>

    <div class="modal-footer" id="viewFooter">
      <form id="deleteCredentialForm" style="margin-right:auto;">
        <input type="hidden" name="id" id="deleteCredentialId">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <button type="submit" class="btn btn-danger btn-sm"><?php echo htmlspecialchars(__("dash.delete")); ?></button>
      </form>
      <button class="btn btn-secondary" onclick="closeCredentialModal()"><?php echo htmlspecialchars(__("dash.close")); ?></button>
      <button class="btn btn-primary" style="width:auto;" onclick="changeModalMode('edit')"><?php echo htmlspecialchars(__("dash.edit")); ?></button>
    </div>
    <div class="modal-footer hidden" id="editFooter">
      <button class="btn btn-secondary" onclick="changeModalMode('view')">← Back</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('editCredentialForm').requestSubmit()"><?php echo htmlspecialchars(__("dash.save_changes")); ?></button>
    </div>
  </div>
</div>

<!-- WEAK PASSWORD WARNING MODAL -->
<div id="weakPwdModal" class="modal-backdrop hidden" onclick="resolveWeakPwd(false)">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:420px;">
    <div class="modal-header">
      <h2 style="display:flex;align-items:center;gap:8px;color:var(--danger);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php echo htmlspecialchars(__("dash.weak_warning")); ?>
      </h2>
    </div>
    <div class="modal-body">
      <p style="font-size:14px;color:var(--fg-secondary);"><?php echo htmlspecialchars(__("dash.weak_warning_body")); ?></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="resolveWeakPwd(false)"><?php echo htmlspecialchars(__("dash.cancel")); ?></button>
      <button class="btn btn-danger" style="width:auto;" onclick="resolveWeakPwd(true)"><?php echo htmlspecialchars(__("dash.save_anyway")); ?></button>
    </div>
  </div>
</div>

<script src="../assets/js/clipboard.js"></script>
<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;
const CLIPBOARD_AUTO_CLEAR = <?php echo json_encode($prefs[PREF_KEY_CLIPBOARD_CLEAR] === "1"); ?>;
const WEAK_PWD_ALERTS = <?php echo json_encode($prefs[PREF_KEY_WEAK_PWD_ALERTS] === "1"); ?>;

// Promise-based weak password confirmation modal
let _weakPwdResolver = null;
function confirmWeakPassword() {
    return new Promise(function (resolve) {
        _weakPwdResolver = resolve;
        document.getElementById('weakPwdModal').classList.remove('hidden');
    });
}
function resolveWeakPwd(accepted) {
    document.getElementById('weakPwdModal').classList.add('hidden');
    if (_weakPwdResolver) {
        const r = _weakPwdResolver;
        _weakPwdResolver = null;
        r(accepted === true);
    }
}

/**
 * Calcola un punteggio 0-5 della robustezza della password.
 * Score <= 2 è considerato "debole" per il warning.
 */
function computePasswordScore(password) {
    if (!password) return 0;
    let score = 0;
    if (password.length >= 8)  score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
}

function showMessage(message) {
    const box = document.getElementById('globalMessage');
    box.textContent = message;
    box.classList.add('show');
    setTimeout(function () {
        box.classList.remove('show');
    }, 3000);
}

async function parseJsonResponse(response) {
    const raw = await response.text();

    try {
        return JSON.parse(raw);
    } catch (error) {
        return {
            success: false,
            message: 'Risposta non valida dal server'
        };
    }
}

// Variabili usate dalla finestra dettaglio/modifica
let currentId = null;
let currentPassword = '';
let isPasswordVisible = false;

// Apre la finestra della credenziale
function openCredentialModal(row, mode) {
    currentId = row.dataset.id;
    currentPassword = '';
    isPasswordVisible = false;

    document.getElementById('modalCredentialId').textContent = row.dataset.id;
    document.getElementById('modalServiceText').textContent = row.dataset.service || '';
    document.getElementById('modalUsernameText').textContent = row.dataset.username || '';
    document.getElementById('modalUrlText').textContent = row.dataset.url || '-';
    document.getElementById('modalNotesText').textContent = row.dataset.notes || 'Nessuna nota';
    document.getElementById('modalPasswordText').textContent = '••••••••';
    document.getElementById('editCredentialId').value = row.dataset.id;
    document.getElementById('editServiceName').value = row.dataset.service || '';
    document.getElementById('editUsername').value = row.dataset.username || '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editUrl').value = row.dataset.url || '';
    document.getElementById('editNotes').value = row.dataset.notes || '';

    document.getElementById('deleteCredentialId').value = row.dataset.id;
    document.getElementById('credentialModal').classList.remove('hidden');

    changeModalMode(mode);
}

function closeCredentialModal() {
    document.getElementById('credentialModal').classList.add('hidden');
    currentId = null;
    currentPassword = '';
    isPasswordVisible = false;
}

function changeModalMode(mode) {
    const viewSection = document.getElementById('viewSection');
    const editSection = document.getElementById('editSection');
    const viewFooter = document.getElementById('viewFooter');
    const editFooter = document.getElementById('editFooter');
    const title = document.getElementById('modalTitle');

    if (mode === 'edit') {
        viewSection.classList.add('hidden');
        editSection.classList.remove('hidden');
        viewFooter.classList.add('hidden');
        editFooter.classList.remove('hidden');
        title.textContent = 'Edit Credential';
    } else {
        viewSection.classList.remove('hidden');
        editSection.classList.add('hidden');
        viewFooter.classList.remove('hidden');
        editFooter.classList.add('hidden');
        title.textContent = 'Credential';
    }
}


// Mostra o nasconde la password
async function toggleModalPassword() {
    const passwordText = document.getElementById('modalPasswordText');
    const button = document.getElementById('modalTogglePasswordBtn');

    if (!currentId) {
        showMessage('Nessuna credenziale selezionata');
        return;
    }

    if (isPasswordVisible) {
        passwordText.textContent = '••••••••';
        isPasswordVisible = false;
        return;
    }

    try {
        if (!currentPassword) {
            const payload = new FormData();
            payload.append('id', currentId);
            payload.append('csrf_token', csrfToken);

            const response = await fetch('credential/get_password.php', {
                method: 'POST',
                body: payload
            });
            const data = await parseJsonResponse(response);

            if (!response.ok || !data.success) {
                showMessage(data.message || 'Errore nel recupero della password');
                return;
            }

            currentPassword = data.password;
        }

        passwordText.textContent = currentPassword;
        isPasswordVisible = true;
    } catch (error) {
        showMessage('Errore nella richiesta');
    }
}

// Copia la password
async function copyModalPassword() {
    if (!currentId) {
        showMessage('Nessuna credenziale selezionata');
        return;
    }

    try {
        if (!currentPassword) {
            const payload = new FormData();
            payload.append('id', currentId);
            payload.append('csrf_token', csrfToken);

            const response = await fetch('credential/get_password.php', {
                method: 'POST',
                body: payload
            });
            const data = await parseJsonResponse(response);

            if (!response.ok || !data.success) {
                showMessage(data.message || 'Errore nel recupero della password');
                return;
            }

            currentPassword = data.password;
        }

        const ok = await ClipboardManager.copyWithAutoClear(currentPassword, CLIPBOARD_AUTO_CLEAR);
        showMessage(ok ? 'Password copiata' : 'Errore durante la copia');
    } catch (error) {
        showMessage('Errore durante la copia');
    }
}

// Copia lo username negli appunti
async function copyModalUsername() {
    const usernameText = document.getElementById('modalUsernameText').textContent;

    if (!usernameText) {
        showMessage('Nessuno username da copiare');
        return;
    }

    try {
        await navigator.clipboard.writeText(usernameText);
        showMessage('Username copiato');
    } catch (error) {
        showMessage('Errore durante la copia');
    }
}

// Salva le modifiche della credenziale
async function updateCredential(event) {
    event.preventDefault();

    const form = document.getElementById('editCredentialForm');
    const formData = new FormData(form);

    if (WEAK_PWD_ALERTS) {
        const newPassword = formData.get('password') || '';
        // Solo se l'utente ha effettivamente inserito una nuova password (campo vuoto = mantieni)
        if (newPassword !== '' && computePasswordScore(newPassword) <= 2) {
            const confirmed = await confirmWeakPassword();
            if (!confirmed) return;
        }
    }

    try {
        const response = await fetch('credential/update_credential.php', {
            method: 'POST',
            body: formData
        });

        const data = await parseJsonResponse(response);

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Errore durante la modifica');
            return;
        }

        const row = document.querySelector('.credential-row[data-id="' + formData.get('id') + '"]');

        if (row) {
            row.dataset.service = formData.get('service_name') || '';
            row.dataset.username = formData.get('username') || '';
            row.dataset.url = formData.get('url') || '';
            row.dataset.notes = formData.get('notes') || '';

            const svcEl = row.querySelector('.cred-service');
            const userEl = row.querySelector('.cred-username');
            if (svcEl) svcEl.textContent = formData.get('service_name') || '';
            if (userEl) userEl.textContent = formData.get('username') || '';
        }

        currentPassword = '';
        isPasswordVisible = false;
        document.getElementById('editPassword').value = '';
        document.getElementById('modalServiceText').textContent = formData.get('service_name') || '';
        document.getElementById('modalUsernameText').textContent = formData.get('username') || '';
        document.getElementById('modalUrlText').textContent = formData.get('url') || '-';
        document.getElementById('modalNotesText').textContent = formData.get('notes') || 'No notes';
        document.getElementById('modalPasswordText').textContent = '••••••••';

        changeModalMode('view');
        showMessage(data.message || 'Credenziale aggiornata');
    } catch (error) {
        showMessage('Errore nella richiesta di modifica');
    }
}

// Elimina la credenziale selezionata
async function removeCredential(event) {
    event.preventDefault();

    if (!confirm('Vuoi davvero eliminare questa credenziale?')) {
        return;
    }

    const form = document.getElementById('deleteCredentialForm');
    const formData = new FormData(form);

    try {
        const response = await fetch('credential/delete_credential.php', {
            method: 'POST',
            body: formData
        });

        const data = await parseJsonResponse(response);

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Errore durante l\'eliminazione');
            return;
        }

        const row = document.querySelector('.credential-row[data-id="' + formData.get('id') + '"]');
        if (row) row.remove();

        closeCredentialModal();
        showMessage(data.message || 'Credential deleted');
    } catch (error) {
        showMessage('Errore nella richiesta di eliminazione');
    }
}

function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    document.getElementById('addCredentialForm').reset();
}

function openGenerateModal() {
    const pwd = generatePassword(16, true, true, true);
    ClipboardManager.copyWithAutoClear(pwd, CLIPBOARD_AUTO_CLEAR).then(function (ok) {
        showMessage(ok ? 'Generated: ' + pwd + ' (copied)' : 'Generated: ' + pwd);
    });
}

async function copyUsernameFromRow(row) {
    const username = row.dataset.username || '';
    if (!username) { showMessage('No username to copy'); return; }
    try {
        await navigator.clipboard.writeText(username);
        showMessage('Username copied');
    } catch { showMessage('Copy failed'); }
}

document.getElementById('editCredentialForm').addEventListener('submit', updateCredential);
document.getElementById('deleteCredentialForm').addEventListener('submit', removeCredential);

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

async function addCredential(event) {
    event.preventDefault();

    const form = document.getElementById('addCredentialForm');
    const formData = new FormData(form);

    if (WEAK_PWD_ALERTS) {
        const password = formData.get('password') || '';
        if (computePasswordScore(password) <= 2) {
            const confirmed = await confirmWeakPassword();
            if (!confirmed) return;
        }
    }

    try {
        const response = await fetch('credential/add_credential.php', { method: 'POST', body: formData });
        const data = await parseJsonResponse(response);

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Error adding credential');
            return;
        }

        const serviceName = formData.get('service_name') || '';
        const initial = serviceName.charAt(0).toUpperCase();
        const colors = ['#4A9FD8','#30A46C','#E5484D','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#f97316'];
        const colorIdx = serviceName.split('').reduce((a, c) => a + c.charCodeAt(0), 0) % colors.length;
        const color = colors[colorIdx];

        const list = document.getElementById('credentialsTableBody');
        const emptyMsg = list.querySelector('.empty-credentials-msg');
        if (emptyMsg) emptyMsg.remove();

        const newRow = document.createElement('div');
        newRow.className = 'credential-row';
        newRow.setAttribute('data-id', data.id);
        newRow.setAttribute('data-service', formData.get('service_name'));
        newRow.setAttribute('data-username', formData.get('username'));
        newRow.setAttribute('data-url', formData.get('url') || '');
        newRow.setAttribute('data-notes', formData.get('notes') || '');
        newRow.innerHTML = `
            <input type="checkbox" class="select-checkbox" data-id="${data.id}"
              style="display:${selectModeActive ? 'block' : 'none'};width:16px;height:16px;flex-shrink:0;cursor:pointer;accent-color:var(--accent);"
              onclick="event.stopPropagation();updateSelectionCount()">
            <div class="cred-avatar" style="background:${color}1A;color:${color};">${escapeHtml(initial)}</div>
            <div class="cred-info">
                <div class="cred-service">${escapeHtml(serviceName)}</div>
                <div class="cred-username">${escapeHtml(formData.get('username') || '')}</div>
            </div>
            <div class="cred-actions">
                <button class="btn-circle" onclick="copyUsernameFromRow(this.closest('.credential-row'))" title="Copy username">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                </button>
                <button class="btn-circle" onclick="openCredentialModal(this.closest('.credential-row'), 'view')" title="More options">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                </button>
            </div>`;

        list.prepend(newRow);
        form.reset();
        closeAddModal();
        showMessage(data.message || 'Credential added');
    } catch (error) {
        showMessage('Error adding credential');
    }
}

document.getElementById('addCredentialForm').addEventListener('submit', addCredential);
</script>

<script>
// =============================================
// GENERATORE DI PASSWORD
// =============================================

/**
 * Genera una password casuale con i parametri scelti.
 * @param {number} length - Lunghezza della password
 * @param {boolean} useUpper - Include lettere maiuscole
 * @param {boolean} useDigits - Include numeri
 * @param {boolean} useSymbols - Include simboli
 * @returns {string} La password generata
 */
function generatePassword(length, useUpper, useDigits, useSymbols) {
    let chars = 'abcdefghijklmnopqrstuvwxyz';
    if (useUpper)   chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (useDigits)  chars += '0123456789';
    if (useSymbols) chars += '!@#$%^&*()-_=+[]{}|;:,.<>?';

    let password = '';
    for (let i = 0; i < length; i++) {
        const index = Math.floor(Math.random() * chars.length);
        password += chars[index];
    }
    return password;
}

/**
 * Riempie il campo password del modal con una password generata
 * e aggiorna l'indicatore di forza.
 * @param {string} fieldId - ID del campo <input> da riempire
 * @param {string} strengthId - ID dell'elemento indicatore di forza
 */
function fillGeneratedPassword(fieldId, strengthId) {
    const length = 16;
    const password = generatePassword(length, true, true, true);
    document.getElementById(fieldId).value = password;
    updateStrengthIndicator(password, strengthId);
}

// =============================================
// INDICATORE DI FORZA DELLA PASSWORD
// =============================================

/**
 * Calcola e mostra la forza della password in un elemento HTML.
 * @param {string} password - La password da valutare
 * @param {string} indicatorId - ID dell'elemento dove mostrare la forza
 */
function updateStrengthIndicator(password, indicatorId) {
    const el = document.getElementById(indicatorId);
    if (!el) return;

    const result = evaluatePasswordStrength(password);
    el.textContent = result.label;
    el.style.color = result.color;
}

/**
 * Valuta la forza di una password su 4 livelli.
 * @param {string} password
 * @returns {{ label: string, color: string }}
 */
function evaluatePasswordStrength(password) {
    if (!password || password.length === 0) {
        return { label: '', color: '' };
    }

    let score = 0;

    if (password.length >= 8)  score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 1) return { label: 'Forza: Debole',      color: '#c00' };
    if (score === 2) return { label: 'Forza: Media',       color: '#e07000' };
    if (score === 3) return { label: 'Forza: Buona',       color: '#c8a000' };
    if (score === 4) return { label: 'Forza: Forte',       color: '#080' };
    return              { label: 'Forza: Molto forte',  color: '#005500' };
}

// =============================================
// ESPORTAZIONE CREDENZIALI
// =============================================

/**
 * Invia un form POST nascosto per scaricare il file di esportazione.
 * Il server restituisce direttamente il file CSV o JSON.
 * @param {string} format - 'csv' oppure 'json'
 */
function exportCredentials(format) {
    // Crea un form temporaneo e lo invia subito
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'credential/export_credentials.php';

    const tokenInput = document.createElement('input');
    tokenInput.type  = 'hidden';
    tokenInput.name  = 'csrf_token';
    tokenInput.value = csrfToken;
    form.appendChild(tokenInput);

    const formatInput = document.createElement('input');
    formatInput.type  = 'hidden';
    formatInput.name  = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function openSecurityAudit() {
    document.getElementById('securityAuditModal').classList.remove('hidden');
}
function closeSecurityAudit() {
    document.getElementById('securityAuditModal').classList.add('hidden');
}

function filterCredentials() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('#credentialsTableBody .credential-row').forEach(function(row) {
        const service = (row.dataset.service || '').toLowerCase();
        const username = (row.dataset.username || '').toLowerCase();
        const url = (row.dataset.url || '').toLowerCase();
        row.style.display = (service.includes(query) || username.includes(query) || url.includes(query)) ? '' : 'none';
    });
}
</script>

<!-- IMPORT MODAL -->
<div id="importModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>Import from CSV</h2>
      <button class="btn-icon" onclick="closeImportModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--fg-secondary);">
        The CSV file must have these columns in order:<br>
        <code style="background:var(--surface-secondary);padding:4px 8px;border-radius:4px;display:inline-block;margin-top:6px;font-size:12px;">service, username, password, url (opt.), notes (opt.)</code><br>
        The first row (header) is skipped.
      </p>
      <form id="importForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <div>
          <label class="field-label">CSV File</label>
          <input type="file" name="csv_file" accept=".csv" required class="field-input" style="padding:8px;">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('importForm').requestSubmit()">Import</button>
    </div>
  </div>
</div>

<script>
function openImportModal() { document.getElementById('importModal').classList.remove('hidden'); }
function closeImportModal() {
    document.getElementById('importModal').classList.add('hidden');
    document.getElementById('importForm').reset();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('importForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        try {
            const response = await fetch('credential/import_credentials.php', { method: 'POST', body: formData });
            const raw = await response.text();
            let data;
            try { data = JSON.parse(raw); } catch { data = { success: false, message: 'Invalid server response' }; }
            showMessage(data.message || '');
            closeImportModal();
            if (data.success && data.imported > 0) {
                setTimeout(function() { location.reload(); }, 1500);
            }
        } catch { showMessage('Import error'); }
    });
});

</script>

<script src="../assets/js/multi-select.js"></script>
<script>
let selectModeActive = false;

function toggleSelectMode() {
    selectModeActive = !selectModeActive;
    const container = document.getElementById('credentialsTableBody');
    const btn       = document.getElementById('selectModeBtn');
    const bar       = document.getElementById('selectionBar');
    const checkboxes = container.querySelectorAll('.select-checkbox');

    if (selectModeActive) {
        checkboxes.forEach(function(cb) { cb.style.display = 'block'; });
        btn.textContent = 'Cancel';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-danger');
        bar.style.display = 'flex';
    } else {
        checkboxes.forEach(function(cb) { cb.style.display = 'none'; cb.checked = false; });
        btn.textContent = 'Select';
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-secondary');
        bar.style.display = 'none';
        updateSelectionCount();
    }
}

function updateSelectionCount() {
    const container = document.getElementById('credentialsTableBody');
    const ids       = MultiSelect.getSelectedIds(container);
    document.getElementById('selectionCount').textContent = ids.length + ' selected';
    document.getElementById('bulkDeleteBtn').disabled = !MultiSelect.isValidSelectionForDelete(ids);
}

async function bulkDeleteSelected() {
    const container = document.getElementById('credentialsTableBody');
    const ids       = MultiSelect.getSelectedIds(container);

    if (!MultiSelect.isValidSelectionForDelete(ids)) {
        showMessage('Select at least one credential');
        return;
    }

    if (!confirm('Delete ' + ids.length + ' credential' + (ids.length > 1 ? 's' : '') + '?')) return;

    const payload = MultiSelect.buildBulkDeletePayload(ids, csrfToken);

    try {
        const response = await fetch('credential/bulk_delete_credentials.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        let data;
        try { data = JSON.parse(await response.text()); }
        catch { data = { success: false, message: 'Invalid server response' }; }

        if (!data.success) {
            showMessage(data.message || 'Delete error');
            return;
        }

        ids.forEach(function(id) {
            const row = document.querySelector('.credential-row[data-id="' + id + '"]');
            if (row) row.remove();
        });

        showMessage(data.message || 'Credentials deleted');
        toggleSelectMode();

        if (!document.querySelector('#credentialsTableBody .credential-row')) {
            document.getElementById('credentialsTableBody').innerHTML =
                '<div class="empty-credentials-msg" style="padding:32px;text-align:center;color:var(--fg-muted);font-size:13px;">No credentials yet. Add your first one above.</div>';
        }
    } catch {
        showMessage('Error during deletion');
    }
}
</script>

<!-- SECURITY AUDIT MODAL -->
<div id="securityAuditModal" class="modal-backdrop hidden" onclick="closeSecurityAudit()">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:440px;">
    <div class="modal-header">
      <h2>Security Audit</h2>
      <button class="btn-icon" onclick="closeSecurityAudit()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="vault-stat-row"><span>Total Credentials</span><span><?php echo $totalCreds; ?></span></div>
      <div class="vault-stat-row"><span>Stale Passwords (&gt;6 months)</span><span class="<?php echo $weakCount > 0 ? 'danger' : ''; ?>"><?php echo $weakCount; ?></span></div>
      <?php if ($weakCount > 0): ?>
      <p style="font-size:13px;color:var(--danger);margin-top:8px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php echo $weakCount; ?> password<?php echo $weakCount > 1 ? 's' : ''; ?> not updated in over 6 months.
      </p>
      <?php endif; ?>
      <p style="font-size:13px;color:var(--fg-secondary);margin-top:8px;">Keep your vault secure: use unique passwords for each service and update them regularly.</p>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <button class="btn btn-secondary btn-sm" onclick="exportCredentials('csv')">Export CSV</button>
        <button class="btn btn-secondary btn-sm" onclick="exportCredentials('json')">Export JSON</button>
        <button class="btn btn-secondary btn-sm" onclick="openImportModal();closeSecurityAudit();">Import CSV</button>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" style="width:auto;" onclick="closeSecurityAudit()">Close</button>
    </div>
  </div>
</div>

</body>
</html>

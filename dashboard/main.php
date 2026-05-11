<?php
session_start();

if(!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";

// Controlla se la sessione è scaduta per inattività
checkSessionTimeout();

$csrfToken = getCsrfToken();

$stmt = $pdo->prepare("
SELECT * FROM credenziali
WHERE user_id = :uid
ORDER BY updated_at DESC, created_at DESC
");

$stmt->execute([
":uid" => $_SESSION["user_id"]
]);

$credentials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="it">
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
              <h3>Add Credential</h3>
              <p>Store new login</p>
            </div>
          </div>
          <div class="action-card" onclick="openGenerateModal()">
            <div class="action-card-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            </div>
            <div>
              <h3>Generate Password</h3>
              <p>Create strong keys</p>
            </div>
          </div>
          <div class="action-card" onclick="exportCredentials('csv')">
            <div class="action-card-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </div>
            <div>
              <h3>Export</h3>
              <p>Download your vault</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Credentials -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <span class="section-title" style="margin-bottom:0;">Recent Credentials</span>
          <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary btn-sm" onclick="openImportModal()">Import CSV</button>
            <button class="btn btn-secondary btn-sm" onclick="exportCredentials('json')">Export JSON</button>
          </div>
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
            <div class="cred-avatar" style="background:<?php echo $color; ?>;"><?php echo htmlspecialchars($initial); ?></div>
            <div class="cred-info">
              <div class="cred-service"><?php echo htmlspecialchars($cred["service_name"]); ?></div>
              <div class="cred-username"><?php echo htmlspecialchars($cred["username"]); ?></div>
            </div>
            <div class="cred-actions">
              <button class="btn-icon" onclick="copyUsernameFromRow(this.closest('.credential-row'))" title="Copy username">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
              </button>
              <button class="btn-icon" onclick="openCredentialModal(this.closest('.credential-row'), 'view')" title="More options">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($credentials)): ?>
          <div style="padding:32px;text-align:center;color:var(--fg-muted);font-size:13px;">
            No credentials yet. Add your first one above.
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Vault Summary Sidebar -->
    <aside class="app-sidebar">
      <div class="sidebar-card">
        <h3>Vault Summary</h3>
        <div class="vault-stat-row">
          <span>Total Credentials</span>
          <span><?php echo $totalCreds; ?></span>
        </div>
        <div class="vault-stat-row">
          <span>Last Sync</span>
          <span style="font-size:12px;color:var(--fg-muted);">Just now</span>
        </div>

        <div class="sidebar-section-title" style="margin-top:16px;">Quick Links</div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <a href="activity_log.php" class="settings-nav-item" style="padding:6px 8px;font-size:12px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Activity Log
          </a>
          <a href="settings.php" class="settings-nav-item" style="padding:6px 8px;font-size:12px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            Settings
          </a>
          <a href="../auth/logout.php" class="settings-nav-item" style="padding:6px 8px;font-size:12px;color:var(--danger);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
          </a>
        </div>
      </div>

      <!-- Autofill toggle -->
      <div class="sidebar-card">
        <h3>Extension</h3>
        <div class="settings-row" style="padding:0;border:none;">
          <div class="settings-row-info">
            <h3 style="font-size:13px;">Autofill</h3>
            <p style="font-size:11px;" id="settingsMessage"></p>
          </div>
          <label class="toggle">
            <input type="checkbox" id="autofillToggle">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
    </aside>

  </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2>New Credential</h2>
      <button class="btn-icon" onclick="closeAddModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="addCredentialForm" class="modal-body">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
      <div>
        <label class="field-label">Service</label>
        <input type="text" name="service_name" class="field-input" placeholder="e.g. Google, GitHub" required>
      </div>
      <div>
        <label class="field-label">Username / Email</label>
        <input type="text" name="username" class="field-input" placeholder="your@email.com" required>
      </div>
      <div>
        <label class="field-label">Password</label>
        <div class="field-row">
          <input type="password" name="password" id="addPassword" class="field-input" required
            oninput="updateStrengthIndicator(this.value, 'addPasswordStrength')">
          <button type="button" class="btn btn-secondary btn-sm" onclick="fillGeneratedPassword('addPassword','addPasswordStrength')">Generate</button>
        </div>
        <span id="addPasswordStrength" style="font-size:12px;color:var(--fg-muted);margin-top:4px;display:block;"></span>
      </div>
      <div>
        <label class="field-label">URL (optional)</label>
        <input type="text" name="url" class="field-input" placeholder="https://...">
      </div>
      <div>
        <label class="field-label">Notes (optional)</label>
        <textarea name="notes" class="field-input"></textarea>
      </div>
    </form>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('addCredentialForm').requestSubmit()">Save</button>
    </div>
  </div>
</div>

<!-- CREDENTIAL DETAIL/EDIT MODAL -->
<div id="credentialModal" class="modal-backdrop hidden">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h2 id="modalTitle">Credential</h2>
      <button class="btn-icon" onclick="closeCredentialModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- View section -->
    <div id="viewSection" class="modal-body">
      <div class="detail-row">
        <span class="detail-label">Service</span>
        <span class="detail-value" id="modalServiceText"></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Username</span>
        <span class="detail-value">
          <span id="modalUsernameText"></span>
          <button class="btn-icon" onclick="copyModalUsername()" title="Copy">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          </button>
        </span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Password</span>
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
        <span class="detail-label">URL</span>
        <span class="detail-value" id="modalUrlText"></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Notes</span>
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
          <label class="field-label">Service</label>
          <input type="text" name="service_name" id="editServiceName" class="field-input" required>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label">Username / Email</label>
          <input type="text" name="username" id="editUsername" class="field-input" required>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label">Password <span style="font-weight:400;color:var(--fg-muted)">(leave blank to keep)</span></label>
          <div class="field-row">
            <input type="password" name="password" id="editPassword" class="field-input"
              oninput="updateStrengthIndicator(this.value, 'editPasswordStrength')">
            <button type="button" class="btn btn-secondary btn-sm" onclick="fillGeneratedPassword('editPassword','editPasswordStrength')">Generate</button>
          </div>
          <span id="editPasswordStrength" style="font-size:12px;color:var(--fg-muted);margin-top:4px;display:block;"></span>
        </div>
        <div style="margin-top:12px;">
          <label class="field-label">URL</label>
          <input type="text" name="url" id="editUrl" class="field-input">
        </div>
        <div style="margin-top:12px;">
          <label class="field-label">Notes</label>
          <textarea name="notes" id="editNotes" class="field-input"></textarea>
        </div>
      </form>
    </div>

    <div class="modal-footer" id="viewFooter">
      <form id="deleteCredentialForm" style="margin-right:auto;">
        <input type="hidden" name="id" id="deleteCredentialId">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
      </form>
      <button class="btn btn-secondary" onclick="closeCredentialModal()">Close</button>
      <button class="btn btn-primary" style="width:auto;" onclick="changeModalMode('edit')">Edit</button>
    </div>
    <div class="modal-footer hidden" id="editFooter">
      <button class="btn btn-secondary" onclick="changeModalMode('view')">← Back</button>
      <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('editCredentialForm').requestSubmit()">Save Changes</button>
    </div>
  </div>
</div>

<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;

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

        await navigator.clipboard.writeText(currentPassword);
        showMessage('Password copiata');
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
    navigator.clipboard.writeText(pwd).then(function() {
        showMessage('Generated: ' + pwd + ' (copied)');
    }).catch(function() {
        showMessage('Generated: ' + pwd);
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
        const emptyMsg = list.querySelector('div[style]');
        if (emptyMsg) emptyMsg.remove();

        const newRow = document.createElement('div');
        newRow.className = 'credential-row';
        newRow.setAttribute('data-id', data.id);
        newRow.setAttribute('data-service', formData.get('service_name'));
        newRow.setAttribute('data-username', formData.get('username'));
        newRow.setAttribute('data-url', formData.get('url') || '');
        newRow.setAttribute('data-notes', formData.get('notes') || '');
        newRow.innerHTML = `
            <div class="cred-avatar" style="background:${color};">${escapeHtml(initial)}</div>
            <div class="cred-info">
                <div class="cred-service">${escapeHtml(serviceName)}</div>
                <div class="cred-username">${escapeHtml(formData.get('username') || '')}</div>
            </div>
            <div class="cred-actions">
                <button class="btn-icon" onclick="copyUsernameFromRow(this.closest('.credential-row'))" title="Copy username">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                </button>
                <button class="btn-icon" onclick="openCredentialModal(this.closest('.credential-row'), 'view')" title="More options">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
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
// IMPORTAZIONE CREDENZIALI
// =============================================

function openImportModal() {
    document.getElementById('importModal').hidden = false;
}

function closeImportModal() {
    document.getElementById('importModal').hidden = true;
    document.getElementById('importForm').reset();
}

// Invia il file CSV al server tramite fetch e ricarica la pagina se l'importazione ha successo
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('importForm').addEventListener('submit', async function(event) {
        event.preventDefault();

        const formData = new FormData(this);

        try {
            const response = await fetch('credential/import_credentials.php', {
                method: 'POST',
                body: formData
            });

            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch {
                data = { success: false, message: 'Risposta non valida dal server' };
            }

            showMessage(data.message || '');
            closeImportModal();

            // Ricarica la pagina per mostrare le nuove credenziali
            if (data.success && data.imported > 0) {
                setTimeout(function() { location.reload(); }, 1500);
            }
        } catch (error) {
            showMessage('Errore durante l\'importazione');
        }
    });
});

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

// Extension autofill bridge
window.postMessage({ type: "km_get_setting", key: "km_autofill_enabled" }, window.location.origin);

window.addEventListener("message", function(event) {
    if (event.origin !== window.location.origin) return;
    if (event.data && event.data.type === "km_setting_value" && event.data.key === "km_autofill_enabled") {
        const toggle = document.getElementById("autofillToggle");
        if (toggle) toggle.checked = event.data.value;
    }
});

const autofillToggle = document.getElementById("autofillToggle");
if (autofillToggle) {
    autofillToggle.addEventListener("change", function() {
        const enabled = this.checked;
        window.postMessage({ type: "km_set_setting", key: "km_autofill_enabled", value: enabled }, window.location.origin);
        const msg = document.getElementById("settingsMessage");
        if (msg) {
            msg.textContent = enabled ? "Autofill enabled" : "Autofill disabled";
            setTimeout(function() { msg.textContent = ""; }, 2000);
        }
    });
}
</script>

</body>
</html>

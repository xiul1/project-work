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
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
</head>
<body>

<div id="globalMessage"></div>

<!-- Barra superiore con titolo, username e logout -->
<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #ccc; margin-bottom:12px;">
    <h1 style="margin:0;">KeyManager</h1>
    <div>
        Ciao, <strong><?php echo htmlspecialchars($_SESSION["username"] ?? "Utente"); ?></strong>
        &nbsp;|&nbsp;
        <a href="../auth/logout.php">Esci</a>
        &nbsp;|&nbsp;
        <a href="settings.php">Impostazioni</a>
        &nbsp;|&nbsp;
        <a href="activity_log.php">Log attività</a>
    </div>
</div>

<h2>Aggiungi credenziale</h2>
<button type="button" onclick="openAddModal()">Nuova credenziale</button>

<hr>

<h2>Le tue credenziali</h2>

<!-- Pulsanti di esportazione e importazione -->
<div style="margin-bottom:10px;">
    <button type="button" onclick="exportCredentials('csv')">Esporta CSV</button>
    <button type="button" onclick="exportCredentials('json')">Esporta JSON</button>
    <button type="button" onclick="openImportModal()">Importa CSV</button>
</div>

<!-- Campo di ricerca: filtra le righe della tabella in tempo reale -->
<input
    type="text"
    id="searchInput"
    placeholder="Cerca per servizio, username o URL..."
    oninput="filterCredentials()"
    style="width:100%; max-width:400px; padding:6px; margin-bottom:10px;"
>

<table border="1">

<tr>
<th>Servizio</th>
<th>Username</th>
<th>Password</th>
<th>Azioni</th>
</tr>

<tbody id="credentialsTableBody">
<?php foreach($credentials as $cred): ?>

<tr
    data-id="<?php echo $cred["credential_id"]; ?>"
    data-service="<?php echo htmlspecialchars($cred["service_name"], ENT_QUOTES); ?>"
    data-username="<?php echo htmlspecialchars($cred["username"], ENT_QUOTES); ?>"
    data-url="<?php echo htmlspecialchars($cred["url"] ?? "", ENT_QUOTES); ?>"
    data-notes="<?php echo htmlspecialchars($cred["notes"] ?? "", ENT_QUOTES); ?>"
>

<td><?php echo htmlspecialchars($cred["service_name"]); ?></td>
<td><?php echo htmlspecialchars($cred["username"]); ?></td>
<td>********</td>
<td>
    <button type="button" onclick="openCredentialModal(this.closest('tr'), 'view')">Dettaglio</button>
    <button type="button" onclick="openCredentialModal(this.closest('tr'), 'edit')">Modifica</button>
</td>

</tr>

<?php endforeach; ?>
</tbody>
</table>

<div id="addModal" hidden>
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4);" onclick="closeAddModal()"></div>
    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; width:90%; max-width:600px; border:1px solid #ccc; z-index:10;">

        <h2>Nuova credenziale</h2>

        <form id="addCredentialForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

        Servizio:<br>
        <input type="text" name="service_name" required><br><br>

        Username:<br>
        <input type="text" name="username" required><br><br>

        Password:<br>
        <input type="password" name="password" id="addPassword" required
               oninput="updateStrengthIndicator(this.value, 'addPasswordStrength')">
        <button type="button" onclick="fillGeneratedPassword('addPassword', 'addPasswordStrength')">Genera</button>
        <span id="addPasswordStrength" style="font-size:0.85em; margin-left:6px;"></span><br><br>

        URL:<br>
        <input type="text" name="url"><br><br>

        Note:<br>
        <textarea name="notes"></textarea><br><br>

        <button type="submit">Salva</button>

        </form>

        <br>
        <button type="button" onclick="closeAddModal()">Chiudi</button>
    </div>
</div>

<div id="credentialModal" hidden>
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4);" onclick="closeCredentialModal()"></div>
    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; width:90%; max-width:600px; border:1px solid #ccc; z-index:10; max-height:90vh; overflow:auto;">
        <h2 id="modalTitle">Dettaglio credenziale</h2>

        <div id="viewSection">
            <p><strong>ID:</strong> <span id="modalCredentialId"></span></p>
            <p><strong>Servizio:</strong> <span id="modalServiceText"></span></p>
            <p>
                <strong>Username:</strong>
                <span id="modalUsernameText"></span>
                <button type="button" onclick="copyModalUsername()">Copia username</button>
            </p>
            <p>
                <strong>Password:</strong>
                <span id="modalPasswordText">********</span>
                <button type="button" id="modalTogglePasswordBtn" onclick="toggleModalPassword()">Mostra password</button>
                <button type="button" onclick="copyModalPassword()">Copia password</button>
            </p>
            <p><strong>URL:</strong> <span id="modalUrlText"></span></p>
            <p><strong>Note:</strong><br><span id="modalNotesText"></span></p>

            <p>
                <button type="button" onclick="changeModalMode('edit')">Vai a modifica</button>
            </p>
        </div>

        <div id="editSection" hidden>
            <form id="editCredentialForm">
                <input type="hidden" name="id" id="editCredentialId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

                Servizio:<br>
                <input type="text" name="service_name" id="editServiceName" required><br><br>

                Username:<br>
                <input type="text" name="username" id="editUsername" required><br><br>

                Password (lascia vuoto per non cambiarla):<br>
                <input type="password" name="password" id="editPassword"
                       oninput="updateStrengthIndicator(this.value, 'editPasswordStrength')">
                <button type="button" onclick="fillGeneratedPassword('editPassword', 'editPasswordStrength')">Genera</button>
                <span id="editPasswordStrength" style="font-size:0.85em; margin-left:6px;"></span><br><br>

                URL:<br>
                <input type="text" name="url" id="editUrl"><br><br>

                Note:<br>
                <textarea name="notes" id="editNotes"></textarea><br><br>

                <button type="submit">Salva modifiche</button>
            </form>

            <p>
                <button type="button" onclick="changeModalMode('view')">Torna al dettaglio</button>
            </p>
        </div>

        <hr>

        <h3>Elimina credenziale</h3>
        <form id="deleteCredentialForm">
            <input type="hidden" name="id" id="deleteCredentialId">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
            <button type="submit">Elimina credenziale</button>
        </form>

        <br>
        <button type="button" onclick="closeCredentialModal()">Chiudi</button>
    </div>
</div>

<script>
const csrfToken = <?php echo json_encode($csrfToken); ?>;

// Mostra un messaggio semplice in alto nella pagina
function showMessage(message) {
    const box = document.getElementById('globalMessage');
    box.textContent = message;

    setTimeout(function () {
        box.textContent = '';
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
    document.getElementById('modalPasswordText').textContent = '********';
    document.getElementById('modalTogglePasswordBtn').textContent = 'Mostra password';

    document.getElementById('editCredentialId').value = row.dataset.id;
    document.getElementById('editServiceName').value = row.dataset.service || '';
    document.getElementById('editUsername').value = row.dataset.username || '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editUrl').value = row.dataset.url || '';
    document.getElementById('editNotes').value = row.dataset.notes || '';

    document.getElementById('deleteCredentialId').value = row.dataset.id;
    document.getElementById('credentialModal').hidden = false;

    changeModalMode(mode);
}

// Chiude la finestra della credenziale
function closeCredentialModal() {
    document.getElementById('credentialModal').hidden = true;
    currentId = null;
    currentPassword = '';
    isPasswordVisible = false;
}

// Cambia schermata tra dettaglio e modifica
function changeModalMode(mode) {
    const viewSection = document.getElementById('viewSection');
    const editSection = document.getElementById('editSection');
    const title = document.getElementById('modalTitle');

    if (mode === 'edit') {
        viewSection.hidden = true;
        editSection.hidden = false;
        title.textContent = 'Modifica credenziale';
    } else {
        viewSection.hidden = false;
        editSection.hidden = true;
        title.textContent = 'Dettaglio credenziale';
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
        passwordText.textContent = '********';
        button.textContent = 'Mostra password';
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
        button.textContent = 'Nascondi password';
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

        const row = document.querySelector('tr[data-id="' + formData.get('id') + '"]');

        if (row) {
            row.dataset.service = formData.get('service_name') || '';
            row.dataset.username = formData.get('username') || '';
            row.dataset.url = formData.get('url') || '';
            row.dataset.notes = formData.get('notes') || '';

            const cells = row.querySelectorAll('td');
            if (cells[0]) cells[0].textContent = formData.get('service_name') || '';
            if (cells[1]) cells[1].textContent = formData.get('username') || '';
        }

        currentPassword = '';
        isPasswordVisible = false;
        document.getElementById('editPassword').value = '';
        document.getElementById('modalServiceText').textContent = formData.get('service_name') || '';
        document.getElementById('modalUsernameText').textContent = formData.get('username') || '';
        document.getElementById('modalUrlText').textContent = formData.get('url') || '-';
        document.getElementById('modalNotesText').textContent = formData.get('notes') || 'Nessuna nota';
        document.getElementById('modalPasswordText').textContent = '********';
        document.getElementById('modalTogglePasswordBtn').textContent = 'Mostra password';

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

        const row = document.querySelector('tr[data-id="' + formData.get('id') + '"]');
        if (row) {
            row.remove();
        }

        closeCredentialModal();
        showMessage(data.message || 'Credenziale eliminata');
    } catch (error) {
        showMessage('Errore nella richiesta di eliminazione');
    }
}

// Apre e chiude la finestra per aggiungere una credenziale
function openAddModal() {
    document.getElementById('addModal').hidden = false;
}

function closeAddModal() {
    document.getElementById('addModal').hidden = true;
}

// Collega i form agli eventi giusti
document.getElementById('editCredentialForm').addEventListener('submit', updateCredential);
document.getElementById('deleteCredentialForm').addEventListener('submit', removeCredential);
</script>

<script>
// Evita problemi con caratteri speciali quando aggiungo una nuova riga
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Aggiunge una nuova credenziale senza ricaricare la pagina
async function addCredential(event) {
    event.preventDefault();

    const form = document.getElementById('addCredentialForm');
    const formData = new FormData(form);

    try {
        const response = await fetch('credential/add_credential.php', {
            method: 'POST',
            body: formData
        });

        const data = await parseJsonResponse(response);

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Errore durante l\'aggiunta');
            return;
        }

        const tableBody = document.getElementById('credentialsTableBody');
        const newRow = document.createElement('tr');

        newRow.setAttribute('data-id', data.id);
        newRow.setAttribute('data-service', formData.get('service_name'));
        newRow.setAttribute('data-username', formData.get('username'));
        newRow.setAttribute('data-url', formData.get('url') || '');
        newRow.setAttribute('data-notes', formData.get('notes') || '');

        newRow.innerHTML = `
            <td>${escapeHtml(formData.get('service_name') || '')}</td>
            <td>${escapeHtml(formData.get('username') || '')}</td>
            <td>********</td>
            <td>
                <button type="button" onclick="openCredentialModal(this.closest('tr'), 'view')">Dettaglio</button>
                <button type="button" onclick="openCredentialModal(this.closest('tr'), 'edit')">Modifica</button>
            </td>
        `;

        tableBody.prepend(newRow);
        form.reset();
        closeAddModal();
        showMessage(data.message || 'Credenziale aggiunta');
    } catch (error) {
        showMessage('Errore nella richiesta di aggiunta');
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

// Filtra le righe della tabella in base al testo cercato
function filterCredentials() {
    const query = document.getElementById('searchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#credentialsTableBody tr');

    rows.forEach(function(row) {
        // Cerca nei dati del servizio, username e URL
        const service = (row.dataset.service || '').toLowerCase();
        const username = (row.dataset.username || '').toLowerCase();
        const url = (row.dataset.url || '').toLowerCase();

        const matches = service.includes(query) || username.includes(query) || url.includes(query);
        row.style.display = matches ? '' : 'none';
    });
}
</script>

<!-- Modal importazione CSV -->
<div id="importModal" hidden>
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4);" onclick="closeImportModal()"></div>
    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; width:90%; max-width:500px; border:1px solid #ccc; z-index:10;">
        <h2>Importa credenziali da CSV</h2>

        <p>Il file CSV deve avere le seguenti colonne nell'ordine:</p>
        <code>servizio, username, password, url (opz.), note (opz.)</code>
        <p>La prima riga viene considerata l'intestazione e viene saltata.</p>

        <form id="importForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

            File CSV:<br>
            <input type="file" name="csv_file" accept=".csv" required><br><br>

            <button type="submit">Importa</button>
        </form>

        <br>
        <button type="button" onclick="closeImportModal()">Chiudi</button>
    </div>
</div>

<!-- ===========================
     SEZIONE IMPOSTAZIONI ESTENSIONE
     Permette di abilitare/disabilitare l'autofill direttamente dalla dashboard.
     Comunica con il content script tramite postMessage (sicuro: stessa origine).
=========================== -->
<hr>
<h2>Impostazioni estensione</h2>
<p>
  <label>
    <input type="checkbox" id="autofillToggle">
    Abilita autofill automatico
  </label>
</p>
<p id="settingsMessage" style="color: green;"></p>

<script>
// Chiede al content script l'impostazione corrente di autofill
window.postMessage({ type: "km_get_setting", key: "km_autofill_enabled" }, window.location.origin);

// Ascolta la risposta del content script con il valore attuale
window.addEventListener("message", function(event) {
    // Accetta solo messaggi dalla stessa origine (sicurezza)
    if (event.origin !== window.location.origin) return;

    // Risposta con il valore dell'impostazione
    if (event.data && event.data.type === "km_setting_value" && event.data.key === "km_autofill_enabled") {
        document.getElementById("autofillToggle").checked = event.data.value;
    }
});

// Salva l'impostazione quando l'utente cambia il toggle
document.getElementById("autofillToggle").addEventListener("change", function() {
    const enabled = this.checked;

    // Invia al content script il nuovo valore da salvare in chrome.storage
    window.postMessage({ type: "km_set_setting", key: "km_autofill_enabled", value: enabled }, window.location.origin);

    // Conferma visiva
    const msg = document.getElementById("settingsMessage");
    msg.textContent = enabled ? "Autofill abilitato." : "Autofill disabilitato.";
    setTimeout(function() { msg.textContent = ""; }, 2000);
});
</script>

</body>
</html>

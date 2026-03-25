<?php
session_start();

if(!isset($_SESSION["user_id"])) {
    header("Location: ../index/user/login.php");
    exit();
}

require "../requirement/pdo.php";
require "../requirement/security.php";

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

<h1>Dashboard</h1>

<h2>Aggiungi credenziale</h2>
<button type="button" onclick="openAddModal()">Nuova credenziale</button>

<hr>

<h2>Le tue credenziali</h2>

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
        <input type="password" name="password" required><br><br>

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
            <p><strong>Username:</strong> <span id="modalUsernameText"></span></p>
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
                <input type="password" name="password" id="editPassword"><br><br>

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

</body>
</html>

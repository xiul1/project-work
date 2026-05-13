const VALID_EXPORT_FORMATS = ['csv', 'json'];

function buildExportForm(format, csrfToken, actionUrl) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = actionUrl;

    const tokenInput = document.createElement('input');
    tokenInput.type = 'hidden';
    tokenInput.name = 'csrf_token';
    tokenInput.value = csrfToken;
    form.appendChild(tokenInput);

    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'format';
    formatInput.value = format;
    form.appendChild(formatInput);

    return form;
}

function parseImportResponse(raw) {
    try {
        return JSON.parse(raw);
    } catch (_) {
        return { success: false, message: 'Invalid server response' };
    }
}

function validateExportFormat(format) {
    return VALID_EXPORT_FORMATS.includes(format);
}

function exportCredentials(format, csrfToken) {
    if (!validateExportFormat(format)) return;
    const form = buildExportForm(format, csrfToken, 'credential/export_credentials.php');
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

if (typeof module !== 'undefined') {
    module.exports = { buildExportForm, parseImportResponse, validateExportFormat };
} else {
    window.ImportExport = { buildExportForm, parseImportResponse, validateExportFormat, exportCredentials };
}

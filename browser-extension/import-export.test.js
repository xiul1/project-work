/**
 * @jest-environment jsdom
 */
const { buildExportForm, parseImportResponse, validateExportFormat } = require('../assets/js/import-export');

describe('buildExportForm', () => {
    it('creates a POST form targeting the export endpoint', () => {
        const form = buildExportForm('json', 'token123', 'credential/export_credentials.php');
        expect(form.tagName).toBe('FORM');
        expect(form.method).toBe('post');
        expect(form.action).toContain('export_credentials.php');
    });

    it('includes csrf_token hidden input with correct value', () => {
        const form = buildExportForm('csv', 'mytoken', 'credential/export_credentials.php');
        const tokenInput = form.querySelector('input[name="csrf_token"]');
        expect(tokenInput).not.toBeNull();
        expect(tokenInput.type).toBe('hidden');
        expect(tokenInput.value).toBe('mytoken');
    });

    it('includes format hidden input set to json', () => {
        const form = buildExportForm('json', 'token', 'credential/export_credentials.php');
        const formatInput = form.querySelector('input[name="format"]');
        expect(formatInput).not.toBeNull();
        expect(formatInput.type).toBe('hidden');
        expect(formatInput.value).toBe('json');
    });

    it('includes format hidden input set to csv', () => {
        const form = buildExportForm('csv', 'token', 'credential/export_credentials.php');
        const formatInput = form.querySelector('input[name="format"]');
        expect(formatInput.value).toBe('csv');
    });

    it('uses the provided action URL', () => {
        const form = buildExportForm('json', 'token', 'https://example.com/export.php');
        expect(form.action).toBe('https://example.com/export.php');
    });
});

describe('parseImportResponse', () => {
    it('parses valid JSON success response', () => {
        const result = parseImportResponse('{"success":true,"message":"Imported 5","imported":5}');
        expect(result.success).toBe(true);
        expect(result.message).toBe('Imported 5');
        expect(result.imported).toBe(5);
    });

    it('parses valid JSON failure response', () => {
        const result = parseImportResponse('{"success":false,"message":"File too large"}');
        expect(result.success).toBe(false);
        expect(result.message).toBe('File too large');
    });

    it('returns error object for invalid JSON', () => {
        const result = parseImportResponse('not-json');
        expect(result.success).toBe(false);
        expect(result.message).toBe('Invalid server response');
    });

    it('returns error object for empty string', () => {
        const result = parseImportResponse('');
        expect(result.success).toBe(false);
        expect(result.message).toBe('Invalid server response');
    });

    it('returns error object for HTML error page', () => {
        const result = parseImportResponse('<html><body>500 error</body></html>');
        expect(result.success).toBe(false);
        expect(result.message).toBe('Invalid server response');
    });
});

describe('validateExportFormat', () => {
    it('accepts csv', () => {
        expect(validateExportFormat('csv')).toBe(true);
    });

    it('accepts json', () => {
        expect(validateExportFormat('json')).toBe(true);
    });

    it('rejects xml', () => {
        expect(validateExportFormat('xml')).toBe(false);
    });

    it('rejects empty string', () => {
        expect(validateExportFormat('')).toBe(false);
    });

    it('rejects null', () => {
        expect(validateExportFormat(null)).toBe(false);
    });

    it('rejects undefined', () => {
        expect(validateExportFormat(undefined)).toBe(false);
    });
});

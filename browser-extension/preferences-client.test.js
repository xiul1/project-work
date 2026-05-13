/**
 * @jest-environment jsdom
 */

const PreferencesClient = require('../assets/js/preferences-client');

beforeEach(() => {
    global.fetch = jest.fn();
});

describe('savePreference', () => {
    it('POSTs key, value and csrf_token to the endpoint', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true }) });
        await PreferencesClient.savePreference('theme', 'dark', 'csrf-1', '/pref');

        expect(global.fetch).toHaveBeenCalledWith('/pref', expect.objectContaining({ method: 'POST' }));
        const body = global.fetch.mock.calls[0][1].body;
        expect(body.get('key')).toBe('theme');
        expect(body.get('value')).toBe('dark');
        expect(body.get('csrf_token')).toBe('csrf-1');
    });

    it('normalizes boolean true to "1"', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true }) });
        await PreferencesClient.savePreference('clipboard_clear', true, 'c', '/x');
        const body = global.fetch.mock.calls[0][1].body;
        expect(body.get('value')).toBe('1');
    });

    it('normalizes boolean false to "0"', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true }) });
        await PreferencesClient.savePreference('clipboard_clear', false, 'c', '/x');
        const body = global.fetch.mock.calls[0][1].body;
        expect(body.get('value')).toBe('0');
    });

    it('coerces numbers to strings', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true }) });
        await PreferencesClient.savePreference('auto_lock', 30, 'c', '/x');
        const body = global.fetch.mock.calls[0][1].body;
        expect(body.get('value')).toBe('30');
    });

    it('returns parsed JSON response on success', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true, message: 'ok' }) });
        const result = await PreferencesClient.savePreference('theme', 'light', 'c', '/x');
        expect(result).toEqual({ success: true, message: 'ok' });
    });

    it('returns failure object on network error', async () => {
        global.fetch.mockRejectedValue(new Error('offline'));
        const result = await PreferencesClient.savePreference('theme', 'light', 'c', '/x');
        expect(result.success).toBe(false);
        expect(result.message).toBe('Network error');
    });

    it('returns failure object when response has no JSON body', async () => {
        global.fetch.mockResolvedValue({ json: async () => null });
        const result = await PreferencesClient.savePreference('theme', 'light', 'c', '/x');
        expect(result.success).toBe(false);
    });
});

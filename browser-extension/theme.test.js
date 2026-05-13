/**
 * @jest-environment jsdom
 */

const ThemeManager = require('../assets/js/theme');

beforeEach(() => {
    localStorage.clear();
    document.documentElement.removeAttribute('data-theme');
    document.body.innerHTML = '';
    global.fetch = jest.fn();
});

describe('isValidTheme', () => {
    it('accepts light, dark, system', () => {
        expect(ThemeManager.isValidTheme('light')).toBe(true);
        expect(ThemeManager.isValidTheme('dark')).toBe(true);
        expect(ThemeManager.isValidTheme('system')).toBe(true);
    });

    it('rejects unknown themes and falsy values', () => {
        expect(ThemeManager.isValidTheme('blue')).toBe(false);
        expect(ThemeManager.isValidTheme('')).toBe(false);
        expect(ThemeManager.isValidTheme(null)).toBe(false);
        expect(ThemeManager.isValidTheme(undefined)).toBe(false);
    });
});

describe('applyTheme', () => {
    it('sets data-theme attribute on root element', () => {
        ThemeManager.applyTheme('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });

    it('persists theme to localStorage', () => {
        ThemeManager.applyTheme('system');
        expect(localStorage.getItem('km_theme')).toBe('system');
    });

    it('falls back to light for invalid theme', () => {
        ThemeManager.applyTheme('blue');
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    });
});

describe('readLocalTheme', () => {
    it('returns light by default', () => {
        expect(ThemeManager.readLocalTheme()).toBe('light');
    });

    it('returns stored theme when valid', () => {
        localStorage.setItem('km_theme', 'dark');
        expect(ThemeManager.readLocalTheme()).toBe('dark');
    });

    it('returns light when stored value is invalid', () => {
        localStorage.setItem('km_theme', 'garbage');
        expect(ThemeManager.readLocalTheme()).toBe('light');
    });
});

describe('persistTheme', () => {
    it('returns false for invalid theme without making request', async () => {
        const ok = await ThemeManager.persistTheme('blue', 'csrf', '/x');
        expect(ok).toBe(false);
        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('calls fetch with theme key/value and csrf token', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: true }) });
        const ok = await ThemeManager.persistTheme('dark', 'csrf-123', '/endpoint');
        expect(ok).toBe(true);
        const call = global.fetch.mock.calls[0];
        expect(call[0]).toBe('/endpoint');
        const body = call[1].body;
        expect(body.get('key')).toBe('theme');
        expect(body.get('value')).toBe('dark');
        expect(body.get('csrf_token')).toBe('csrf-123');
    });

    it('returns false when server reports failure', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ success: false }) });
        const ok = await ThemeManager.persistTheme('dark', 'c', '/x');
        expect(ok).toBe(false);
    });

    it('returns false on network error', async () => {
        global.fetch.mockRejectedValue(new Error('network down'));
        const ok = await ThemeManager.persistTheme('dark', 'c', '/x');
        expect(ok).toBe(false);
    });
});

describe('bindThemeButtons', () => {
    function setupDOM() {
        document.body.innerHTML = `
            <div id="themeOptions">
                <button class="theme-btn" data-theme="light">Light</button>
                <button class="theme-btn" data-theme="dark">Dark</button>
                <button class="theme-btn" data-theme="system">System</button>
            </div>
        `;
    }

    function btn(theme) {
        return document.querySelector('#themeOptions [data-theme="' + theme + '"]');
    }

    it('marks the button matching currentTheme as active', () => {
        setupDOM();
        ThemeManager.bindThemeButtons('#themeOptions', 'dark', () => {});
        expect(btn('dark').classList.contains('active')).toBe(true);
        expect(btn('light').classList.contains('active')).toBe(false);
    });

    it('switches active class and applies theme on click', () => {
        setupDOM();
        const callback = jest.fn();
        ThemeManager.bindThemeButtons('#themeOptions', 'light', callback);
        btn('dark').click();
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(btn('dark').classList.contains('active')).toBe(true);
        expect(btn('light').classList.contains('active')).toBe(false);
        expect(callback).toHaveBeenCalledWith('dark');
    });

    it('does not throw when onChange is not a function', () => {
        setupDOM();
        ThemeManager.bindThemeButtons('#themeOptions', 'light', null);
        expect(() => btn('dark').click()).not.toThrow();
    });
});

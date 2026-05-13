/**
 * Gestione del tema (light/dark/system).
 *
 * Il tema viene applicato impostando l'attributo data-theme su <html>.
 * La persistenza avviene lato server via preferences endpoint;
 * un fallback in localStorage evita flash di tema sbagliato al caricamento.
 */

(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.ThemeManager = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    const THEME_KEY = 'km_theme';
    const VALID_THEMES = ['light', 'dark', 'system'];

    /**
     * @param {string} theme
     * @returns {boolean}
     */
    function isValidTheme(theme) {
        return VALID_THEMES.indexOf(theme) !== -1;
    }

    /**
     * Applica il tema all'elemento root (html).
     * @param {string} theme
     */
    function applyTheme(theme) {
        if (!isValidTheme(theme)) {
            theme = 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch (e) {
            // localStorage non disponibile (private mode, ecc.) - ignora
        }
    }

    /**
     * Restituisce il tema salvato localmente, oppure 'light' come default.
     * @returns {string}
     */
    function readLocalTheme() {
        try {
            const stored = localStorage.getItem(THEME_KEY);
            if (isValidTheme(stored)) return stored;
        } catch (e) {
            // ignore
        }
        return 'light';
    }

    /**
     * Salva il tema sul server.
     * @param {string} theme
     * @param {string} csrfToken
     * @param {string} endpoint
     * @returns {Promise<boolean>}
     */
    async function persistTheme(theme, csrfToken, endpoint) {
        if (!isValidTheme(theme)) return false;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('key', 'theme');
        formData.append('value', theme);
        try {
            const response = await fetch(endpoint, { method: 'POST', body: formData });
            const data = await response.json();
            return data && data.success === true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Wire-up dei pulsanti tema in settings.
     * @param {string} containerSelector - selettore della lista di .theme-btn
     * @param {string} currentTheme - tema attualmente attivo
     * @param {function(string):void} onChange - callback chiamato quando il tema cambia
     */
    function bindThemeButtons(containerSelector, currentTheme, onChange) {
        const buttons = document.querySelectorAll(containerSelector + ' .theme-btn');
        buttons.forEach(function (btn) {
            const theme = btn.getAttribute('data-theme');
            if (theme === currentTheme) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                applyTheme(theme);
                if (typeof onChange === 'function') onChange(theme);
            });
        });
    }

    return {
        THEME_KEY: THEME_KEY,
        VALID_THEMES: VALID_THEMES,
        isValidTheme: isValidTheme,
        applyTheme: applyTheme,
        readLocalTheme: readLocalTheme,
        persistTheme: persistTheme,
        bindThemeButtons: bindThemeButtons,
    };
}));

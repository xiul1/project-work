/**
 * Client JS per il salvataggio delle preferenze utente.
 *
 * Espone savePreference(key, value, csrfToken, endpoint) che ritorna
 * una Promise risolta con la risposta JSON del server.
 */

(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.PreferencesClient = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    /**
     * Salva una preferenza chiamando l'endpoint server.
     * @param {string} key
     * @param {string|number|boolean} value
     * @param {string} csrfToken
     * @param {string} endpoint
     * @returns {Promise<{success:boolean,message?:string}>}
     */
    async function savePreference(key, value, csrfToken, endpoint) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('key', key);
        // Normalizziamo i boolean a '0'/'1' (allineato con la validazione PHP)
        if (typeof value === 'boolean') {
            formData.append('value', value ? '1' : '0');
        } else {
            formData.append('value', String(value));
        }
        try {
            const response = await fetch(endpoint, { method: 'POST', body: formData });
            const data = await response.json();
            return data || { success: false };
        } catch (e) {
            return { success: false, message: 'Network error' };
        }
    }

    return {
        savePreference: savePreference,
    };
}));

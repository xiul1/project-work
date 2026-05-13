/**
 * Helper per copia clipboard con auto-clear opzionale.
 *
 * Quando enabled è true, dopo delayMs il contenuto della clipboard viene
 * sovrascritto con stringa vuota. Utile per le password copiate.
 */

(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.ClipboardManager = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    const DEFAULT_DELAY_MS = 30000;

    /**
     * Copia testo nella clipboard. Se enabled, programma il clear automatico.
     *
     * @param {string} text - il contenuto da copiare
     * @param {boolean} enabled - se true, esegue clear dopo delayMs
     * @param {number} [delayMs=30000]
     * @returns {Promise<boolean>} true se la copia è riuscita
     */
    async function copyWithAutoClear(text, enabled, delayMs) {
        const delay = typeof delayMs === 'number' && delayMs > 0 ? delayMs : DEFAULT_DELAY_MS;

        if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            return false;
        }

        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            return false;
        }

        if (enabled) {
            setTimeout(function () {
                try {
                    navigator.clipboard.writeText('').catch(function () { /* silent */ });
                } catch (e) {
                    // ignore
                }
            }, delay);
        }

        return true;
    }

    return {
        DEFAULT_DELAY_MS: DEFAULT_DELAY_MS,
        copyWithAutoClear: copyWithAutoClear,
    };
}));

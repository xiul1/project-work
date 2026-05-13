const AUTOFILL_KEY = 'km_autofill_enabled';

function readAutofillState() {
    try {
        const val = localStorage.getItem(AUTOFILL_KEY);
        return val === null ? true : val !== 'false';
    } catch (e) {
        return true;
    }
}

function writeAutofillState(enabled) {
    try {
        localStorage.setItem(AUTOFILL_KEY, String(enabled));
    } catch (e) {
        // storage unavailable
    }
}

function createAutofillToggle(toggleId, messageId) {
    const toggle = document.getElementById(toggleId);
    if (!toggle) return;

    toggle.checked = readAutofillState();

    const messageListener = function (event) {
        if (event.origin !== window.location.origin) return;
        if (!event.data || event.data.type !== 'km_setting_value') return;
        if (event.data.key !== AUTOFILL_KEY) return;

        const enabled = event.data.value === undefined ? true : Boolean(event.data.value);
        toggle.checked = enabled;
        writeAutofillState(enabled);
        window.removeEventListener('message', messageListener);
    };
    window.addEventListener('message', messageListener);

    window.postMessage({ type: 'km_get_setting', key: AUTOFILL_KEY }, window.location.origin);

    toggle.addEventListener('change', function () {
        const enabled = this.checked;
        writeAutofillState(enabled);
        window.postMessage({ type: 'km_set_setting', key: AUTOFILL_KEY, value: enabled }, window.location.origin);

        const msg = document.getElementById(messageId);
        if (msg) {
            msg.textContent = enabled ? 'Autofill enabled' : 'Autofill disabled';
            setTimeout(function () { msg.textContent = ''; }, 2000);
        }
    });
}

if (typeof module !== 'undefined') {
    module.exports = { readAutofillState, writeAutofillState, createAutofillToggle };
} else {
    window.AutofillToggle = { readAutofillState, writeAutofillState, createAutofillToggle };
}

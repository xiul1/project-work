/**
 * @jest-environment jsdom
 */

const { readAutofillState, writeAutofillState, createAutofillToggle } = require('../assets/js/autofill-toggle');

const AUTOFILL_KEY = 'km_autofill_enabled';

beforeEach(() => {
    localStorage.clear();
    jest.clearAllMocks();
    window.postMessage = jest.fn();
    document.body.innerHTML = '';
});

describe('readAutofillState', () => {
    it('returns true by default when localStorage has no value', () => {
        expect(readAutofillState()).toBe(true);
    });

    it('returns true when "true" is stored', () => {
        localStorage.setItem(AUTOFILL_KEY, 'true');
        expect(readAutofillState()).toBe(true);
    });

    it('returns false when "false" is stored', () => {
        localStorage.setItem(AUTOFILL_KEY, 'false');
        expect(readAutofillState()).toBe(false);
    });
});

describe('writeAutofillState', () => {
    it('writes "true" string for enabled=true', () => {
        writeAutofillState(true);
        expect(localStorage.getItem(AUTOFILL_KEY)).toBe('true');
    });

    it('writes "false" string for enabled=false', () => {
        writeAutofillState(false);
        expect(localStorage.getItem(AUTOFILL_KEY)).toBe('false');
    });
});

describe('createAutofillToggle', () => {
    function setupDOM() {
        document.body.innerHTML = `
            <label><input type="checkbox" id="testToggle"><span></span></label>
            <p id="testMessage"></p>
        `;
        return {
            toggle: document.getElementById('testToggle'),
            message: document.getElementById('testMessage'),
        };
    }

    it('sets toggle checked from localStorage on init', () => {
        localStorage.setItem(AUTOFILL_KEY, 'false');
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');
        expect(toggle.checked).toBe(false);
    });

    it('defaults toggle to checked when localStorage is empty', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');
        expect(toggle.checked).toBe(true);
    });

    it('sends km_get_setting postMessage on init', () => {
        setupDOM();
        createAutofillToggle('testToggle', 'testMessage');
        expect(window.postMessage).toHaveBeenCalledWith(
            { type: 'km_get_setting', key: AUTOFILL_KEY },
            window.location.origin
        );
    });

    it('updates toggle and localStorage when km_setting_value message arrives', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        window.dispatchEvent(new MessageEvent('message', {
            data: { type: 'km_setting_value', key: AUTOFILL_KEY, value: false },
            origin: window.location.origin,
        }));

        expect(toggle.checked).toBe(false);
        expect(localStorage.getItem(AUTOFILL_KEY)).toBe('false');
    });

    it('defaults to true when km_setting_value has undefined value', () => {
        const { toggle } = setupDOM();
        toggle.checked = false;
        createAutofillToggle('testToggle', 'testMessage');

        window.dispatchEvent(new MessageEvent('message', {
            data: { type: 'km_setting_value', key: AUTOFILL_KEY, value: undefined },
            origin: window.location.origin,
        }));

        expect(toggle.checked).toBe(true);
    });

    it('ignores messages from different origins', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');
        const before = toggle.checked;

        window.dispatchEvent(new MessageEvent('message', {
            data: { type: 'km_setting_value', key: AUTOFILL_KEY, value: !before },
            origin: 'https://evil.com',
        }));

        expect(toggle.checked).toBe(before);
    });

    it('ignores messages with wrong type', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        window.dispatchEvent(new MessageEvent('message', {
            data: { type: 'km_get_setting', key: AUTOFILL_KEY, value: false },
            origin: window.location.origin,
        }));

        expect(toggle.checked).toBe(true);
    });

    it('ignores messages with wrong key', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        window.dispatchEvent(new MessageEvent('message', {
            data: { type: 'km_setting_value', key: 'other_key', value: false },
            origin: window.location.origin,
        }));

        expect(toggle.checked).toBe(true);
    });

    it('saves to localStorage and sends postMessage on toggle change', () => {
        const { toggle } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        toggle.checked = false;
        toggle.dispatchEvent(new Event('change'));

        expect(localStorage.getItem(AUTOFILL_KEY)).toBe('false');
        expect(window.postMessage).toHaveBeenCalledWith(
            { type: 'km_set_setting', key: AUTOFILL_KEY, value: false },
            window.location.origin
        );
    });

    it('shows "Autofill disabled" feedback when toggled off', () => {
        const { toggle, message } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        toggle.checked = false;
        toggle.dispatchEvent(new Event('change'));

        expect(message.textContent).toBe('Autofill disabled');
    });

    it('shows "Autofill enabled" feedback when toggled on', () => {
        localStorage.setItem(AUTOFILL_KEY, 'false');
        const { toggle, message } = setupDOM();
        createAutofillToggle('testToggle', 'testMessage');

        toggle.checked = true;
        toggle.dispatchEvent(new Event('change'));

        expect(message.textContent).toBe('Autofill enabled');
    });

    it('does not throw when toggle element is not found', () => {
        expect(() => createAutofillToggle('nonexistent', 'nonexistent')).not.toThrow();
    });
});

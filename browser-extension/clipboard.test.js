/**
 * @jest-environment jsdom
 */

const ClipboardManager = require('../assets/js/clipboard');

beforeEach(() => {
    jest.useFakeTimers();
    Object.defineProperty(global.navigator, 'clipboard', {
        value: { writeText: jest.fn().mockResolvedValue(undefined) },
        configurable: true,
        writable: true,
    });
});

afterEach(() => {
    jest.useRealTimers();
});

describe('copyWithAutoClear', () => {
    it('writes the text to clipboard', async () => {
        await ClipboardManager.copyWithAutoClear('hunter2', false);
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('hunter2');
    });

    it('returns true on successful copy', async () => {
        const ok = await ClipboardManager.copyWithAutoClear('s3cret', false);
        expect(ok).toBe(true);
    });

    it('does not schedule clear when enabled is false', async () => {
        await ClipboardManager.copyWithAutoClear('x', false);
        navigator.clipboard.writeText.mockClear();
        jest.advanceTimersByTime(60000);
        expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
    });

    it('clears clipboard after default delay when enabled', async () => {
        await ClipboardManager.copyWithAutoClear('p4ss', true);
        navigator.clipboard.writeText.mockClear();
        jest.advanceTimersByTime(ClipboardManager.DEFAULT_DELAY_MS);
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('');
    });

    it('respects custom delay', async () => {
        await ClipboardManager.copyWithAutoClear('p4ss', true, 5000);
        navigator.clipboard.writeText.mockClear();
        jest.advanceTimersByTime(4999);
        expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
        jest.advanceTimersByTime(1);
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('');
    });

    it('uses default delay when delayMs is invalid', async () => {
        await ClipboardManager.copyWithAutoClear('p4ss', true, -10);
        navigator.clipboard.writeText.mockClear();
        jest.advanceTimersByTime(ClipboardManager.DEFAULT_DELAY_MS);
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('');
    });

    it('returns false when clipboard API is unavailable', async () => {
        Object.defineProperty(global.navigator, 'clipboard', {
            value: undefined,
            configurable: true,
            writable: true,
        });
        const ok = await ClipboardManager.copyWithAutoClear('x', true);
        expect(ok).toBe(false);
    });

    it('returns false when writeText rejects', async () => {
        navigator.clipboard.writeText.mockRejectedValueOnce(new Error('denied'));
        const ok = await ClipboardManager.copyWithAutoClear('x', false);
        expect(ok).toBe(false);
    });

    it('swallows errors from clear attempt silently', async () => {
        await ClipboardManager.copyWithAutoClear('x', true, 100);
        navigator.clipboard.writeText.mockRejectedValueOnce(new Error('boom'));
        expect(() => jest.advanceTimersByTime(100)).not.toThrow();
    });
});

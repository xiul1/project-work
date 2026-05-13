/**
 * @jest-environment jsdom
 */
const { getSelectedIds, isValidSelectionForDelete, buildBulkDeletePayload } = require('../assets/js/multi-select');

// Helper: build a fake credential list container with checkboxes
function makeContainer(rows) {
    const div = document.createElement('div');
    rows.forEach(({ id, checked }) => {
        const row = document.createElement('div');
        row.className = 'credential-row';
        row.dataset.id = id;

        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.className = 'select-checkbox';
        cb.dataset.id = id;
        cb.checked = checked;
        row.appendChild(cb);
        div.appendChild(row);
    });
    return div;
}

describe('getSelectedIds', () => {
    it('returns empty array when no checkboxes are checked', () => {
        const container = makeContainer([
            { id: '1', checked: false },
            { id: '2', checked: false },
        ]);
        expect(getSelectedIds(container)).toEqual([]);
    });

    it('returns ID of single checked checkbox', () => {
        const container = makeContainer([
            { id: '42', checked: true },
            { id: '99', checked: false },
        ]);
        expect(getSelectedIds(container)).toEqual(['42']);
    });

    it('returns all IDs when multiple checkboxes are checked', () => {
        const container = makeContainer([
            { id: '1', checked: true },
            { id: '2', checked: true },
            { id: '3', checked: false },
            { id: '4', checked: true },
        ]);
        expect(getSelectedIds(container)).toEqual(['1', '2', '4']);
    });

    it('returns empty array for empty container', () => {
        const container = document.createElement('div');
        expect(getSelectedIds(container)).toEqual([]);
    });

    it('returns empty array for null container', () => {
        expect(getSelectedIds(null)).toEqual([]);
    });
});

describe('isValidSelectionForDelete', () => {
    it('returns true for array with one element', () => {
        expect(isValidSelectionForDelete(['5'])).toBe(true);
    });

    it('returns true for array with multiple elements', () => {
        expect(isValidSelectionForDelete(['1', '2', '3'])).toBe(true);
    });

    it('returns false for empty array', () => {
        expect(isValidSelectionForDelete([])).toBe(false);
    });

    it('returns false for null', () => {
        expect(isValidSelectionForDelete(null)).toBe(false);
    });

    it('returns false for undefined', () => {
        expect(isValidSelectionForDelete(undefined)).toBe(false);
    });
});

describe('buildBulkDeletePayload', () => {
    it('includes ids array in payload', () => {
        const payload = buildBulkDeletePayload(['1', '2', '3'], 'tok');
        expect(payload.ids).toEqual(['1', '2', '3']);
    });

    it('includes csrf_token in payload', () => {
        const payload = buildBulkDeletePayload(['1'], 'mytoken');
        expect(payload.csrf_token).toBe('mytoken');
    });

    it('returns object with exactly ids and csrf_token keys', () => {
        const payload = buildBulkDeletePayload(['7'], 'abc');
        expect(Object.keys(payload).sort()).toEqual(['csrf_token', 'ids']);
    });

    it('preserves all provided IDs', () => {
        const ids = ['10', '20', '30', '40'];
        const payload = buildBulkDeletePayload(ids, 'tok');
        expect(payload.ids).toHaveLength(4);
        expect(payload.ids).toContain('30');
    });
});

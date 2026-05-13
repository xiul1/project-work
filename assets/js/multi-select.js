function getSelectedIds(container) {
    if (!container) return [];
    const checkboxes = container.querySelectorAll('.select-checkbox:checked');
    return Array.from(checkboxes).map(function (cb) { return cb.dataset.id; });
}

function isValidSelectionForDelete(ids) {
    return Array.isArray(ids) && ids.length > 0;
}

function buildBulkDeletePayload(ids, csrfToken) {
    return { ids: ids, csrf_token: csrfToken };
}

if (typeof module !== 'undefined') {
    module.exports = { getSelectedIds, isValidSelectionForDelete, buildBulkDeletePayload };
} else {
    window.MultiSelect = { getSelectedIds, isValidSelectionForDelete, buildBulkDeletePayload };
}

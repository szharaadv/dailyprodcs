/**
 * Submit guard: every fillable field in the checksheet must be filled before a
 * sheet can be submitted. Returns true when complete; otherwise highlights the
 * first empty field, scrolls to it, warns, and returns false so the caller can
 * abort the submit.
 *
 * Scope: visible, enabled, non-readonly inputs/selects/textareas inside the
 * `.checksheet-card`. To exempt a field, give it class="optional" or a
 * data-optional attribute.
 */
function checksheetFirstEmpty(root) {
    root = root || document.querySelector('.checksheet-card') || document;
    const skipTypes = ['hidden', 'button', 'submit', 'reset', 'file', 'checkbox', 'radio'];
    const els = root.querySelectorAll('input, select, textarea');
    for (const el of els) {
        if (el.disabled || el.readOnly) continue;
        if (skipTypes.includes((el.type || '').toLowerCase())) continue;
        if (el.classList.contains('optional') || el.hasAttribute('data-optional')) continue;
        if (el.offsetParent === null) continue; // not visible (collapsed/hidden)
        if ((el.value ?? '').trim() === '') return el;
    }
    return null;
}

function checksheetComplete(root) {
    const el = checksheetFirstEmpty(root);
    if (!el) return true;
    el.classList.add('field-error');
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    try { el.focus({ preventScroll: true }); } catch (e) {}
    el.addEventListener('input', function clear() { el.classList.remove('field-error'); el.removeEventListener('input', clear); });
    el.addEventListener('change', function clear() { el.classList.remove('field-error'); el.removeEventListener('change', clear); });
    alert('Masih ada kolom yang belum terisi. Lengkapi semua kolom dulu sebelum submit.');
    return false;
}
window.checksheetComplete = checksheetComplete;

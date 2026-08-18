const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const targetInput = document.getElementById('f_target');
const tbody = document.getElementById('fopump-reject-tbody');
const statusLabel = document.getElementById('fopump-reject-status-label');

let currentHeaderId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
let lines = []; // [{model, quantity, remarks}]
const modelOptions = (typeof MODEL_NAMES !== 'undefined' ? MODEL_NAMES : []).map(n => ({ value: n, label: n }));

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

function render() {
    if (!lines.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty">No rows yet — click "+ Add Row" to start.</td></tr>';
        return;
    }
    tbody.innerHTML = lines.map((l, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td><input type="text" class="fopump-check-input" data-idx="${idx}" data-field="model" value="${escapeHtml(l.model ?? '')}"></td>
            <td><input type="number" min="0" class="fopump-check-input" data-idx="${idx}" data-field="quantity" value="${escapeHtml(l.quantity ?? '')}"></td>
            <td><input type="text" class="fopump-check-input" data-idx="${idx}" data-field="remarks" value="${escapeHtml(l.remarks ?? '')}"></td>
            <td><button type="button" class="sample-remove-btn row-remove-btn" data-remove-idx="${idx}" title="Remove this row">&times;</button></td>
        </tr>
    `).join('');
    tbody.querySelectorAll('[data-field="model"]').forEach((el) => turnIntoCombo(el, modelOptions, { allowCustom: true }));
}

tbody.addEventListener('input', (e) => {
    if (!e.target.matches('.fopump-check-input')) return;
    const idx = parseInt(e.target.dataset.idx, 10);
    lines[idx][e.target.dataset.field] = e.target.value;
});

tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-remove-btn');
    if (!btn) return;
    const idx = parseInt(btn.dataset.removeIdx, 10);
    lines.splice(idx, 1);
    render();
});

document.getElementById('btn-add-row').addEventListener('click', () => {
    lines.push({ model: '', quantity: '', remarks: '' });
    render();
});

async function loadContext() {
    statusLabel.textContent = 'Loading...';
    const res = await fetch(`ajax/get_fopump_reject_context.php?department_id=${DEPARTMENT_ID}&month=${monthSelect.value}&year=${yearSelect.value}`);
    const data = await res.json();

    const header = data.header;
    currentHeaderId = header ? header.id : null;
    targetInput.value = header?.target ?? '';

    lines = (data.lines || []).map(l => ({ model: l.model ?? '', quantity: l.quantity ?? '', remarks: l.remarks ?? '' }));
    if (!lines.length) lines.push({ model: '', quantity: '', remarks: '' });

    render();

    statusLabel.textContent = header
        ? (header.status === 'draft' ? 'Editing a saved draft for this month.' : 'This month already has a submitted report — saving will update it.')
        : 'New entry for this month.';
}

monthSelect.addEventListener('change', loadContext);
yearSelect.addEventListener('change', loadContext);

function buildPayload(status) {
    return {
        header_id: currentHeaderId,
        status,
        department_id: DEPARTMENT_ID,
        month: monthSelect.value,
        year: yearSelect.value,
        target: targetInput.value,
        lines,
    };
}

async function saveChecksheet(status) {
    statusLabel.textContent = 'Saving...';
    const res = await fetch('ajax/save_fopump_reject.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload(status)),
    });
    const data = await res.json();

    if (!data.success) {
        statusLabel.textContent = '';
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    if (status === 'draft') {
        currentHeaderId = data.header_id;
        alert('Saved as draft. You can continue it later from the My Drafts menu.');
        statusLabel.textContent = 'Editing a saved draft for this month.';
    } else {
        alert('Checksheet submitted successfully.');
        window.location.href = 'view_fopump_reject_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadContext();

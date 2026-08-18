const tbody = document.getElementById('fopump-test-tbody');
const fopCodeEl = document.getElementById('f_fop_code');
const stdCcSecEl = document.getElementById('f_standard_cc_sec');
const rpmStdEl = document.getElementById('f_rpm_std');
const masterTestEl = document.getElementById('f_master_test');

let currentModel = null;
let rows = []; // [{rpm, cc_sec, shim}]
let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftRows = typeof DRAFT_ROWS !== 'undefined' ? DRAFT_ROWS : [];

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

function defaultRow() {
    return {
        rpm: currentModel?.rpm ?? '',
        cc_sec: currentModel?.standard_cc_sec ?? '',
        shim: currentModel?.default_shim ?? '',
    };
}

function render() {
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty">No rows yet — click "+ Add Row" to start.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((r, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td><input type="text" class="fopump-check-input" data-idx="${idx}" data-field="rpm" value="${escapeHtml(r.rpm ?? '')}"></td>
            <td><input type="text" class="fopump-check-input" data-idx="${idx}" data-field="cc_sec" value="${escapeHtml(r.cc_sec ?? '')}"></td>
            <td><input type="text" class="fopump-check-input" data-idx="${idx}" data-field="shim" value="${escapeHtml(r.shim ?? '')}"></td>
            <td><button type="button" class="sample-remove-btn row-remove-btn" data-remove-idx="${idx}" title="Remove this row">&times;</button></td>
        </tr>
    `).join('');
}

tbody.addEventListener('input', (e) => {
    if (!e.target.matches('.fopump-check-input')) return;
    const idx = parseInt(e.target.dataset.idx, 10);
    const field = e.target.dataset.field;
    rows[idx][field] = e.target.value;
});

tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.row-remove-btn');
    if (!btn) return;
    const idx = parseInt(btn.dataset.removeIdx, 10);
    rows.splice(idx, 1);
    render();
});

document.getElementById('btn-add-row').addEventListener('click', () => {
    rows.push(defaultRow());
    render();
});

async function loadModel() {
    const modelId = modelResolver.getValue();
    if (!modelId) {
        currentModel = null;
        fopCodeEl.textContent = stdCcSecEl.textContent = rpmStdEl.textContent = masterTestEl.textContent = '-';
        return;
    }
    const res = await fetch(`ajax/get_fopump_test_model.php?model_id=${modelId}`);
    const data = await res.json();
    currentModel = data.model || null;
    fopCodeEl.textContent = currentModel?.fop_code || '-';
    stdCcSecEl.textContent = currentModel?.standard_cc_sec || '-';
    rpmStdEl.textContent = currentModel?.rpm || '-';
    masterTestEl.textContent = currentModel?.master_test || '-';

    if (currentDraftId && draftRows.length && rows.length === 0) {
        rows = draftRows.map(r => ({ rpm: r.rpm ?? '', cc_sec: r.cc_sec ?? '', shim: r.shim ?? '' }));
    } else if (!rows.length) {
        rows.push(defaultRow());
    }

    render();
}

const modelOptions = (typeof MODELS !== 'undefined' ? MODELS : []).map(m => ({ value: m.id, label: m.name }));
const modelResolver = turnIntoCombo(document.getElementById('f_model'), modelOptions, {
    allowCustom: false,
    onSelect: () => {
        rows = [];
        loadModel();
    },
});

function buildPayload(status) {
    return {
        header_id: currentDraftId,
        status,
        tanggal: document.getElementById('f_tanggal').value,
        department_id: DEPARTMENT_ID,
        model_id: modelResolver.getValue(),
        oil_pressure: document.getElementById('f_oil_pressure').value,
        oil_temp: document.getElementById('f_oil_temp').value,
        room_temp: document.getElementById('f_room_temp').value,
        start_test_time: document.getElementById('f_start_test_time').value,
        checker_id: document.getElementById('f_checker').value,
        foreman_id: document.getElementById('f_foreman').value,
        supervisor_id: document.getElementById('f_supervisor').value,
        rows,
    };
}

async function saveChecksheet(status) {
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_fopump_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!data.success) {
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    if (status === 'draft') {
        currentDraftId = data.header_id;
        alert('Saved as draft. You can continue it later from the My Drafts menu.');
    } else {
        alert('Checksheet submitted successfully.');
        window.location.href = 'view_fopump_test_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadModel();

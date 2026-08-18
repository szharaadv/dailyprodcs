const tbody = document.getElementById('fopump-test-tbody');
const fopCodeEl = document.getElementById('f_fop_code');
const stdCcSecEl = document.getElementById('f_standard_cc_sec');
const rpmStdEl = document.getElementById('f_rpm_std');
const masterTestEl = document.getElementById('f_master_test');
const statusLabel = document.getElementById('fopump-test-status-label');
const destinationEl = document.getElementById('f_destination');
const oilPressureEl = document.getElementById('f_oil_pressure');
const oilTempEl = document.getElementById('f_oil_temp');
const roomTempEl = document.getElementById('f_room_temp');
const startTestTimeEl = document.getElementById('f_start_test_time');
const checkerEl = document.getElementById('f_checker');
const foremanEl = document.getElementById('f_foreman');
const supervisorEl = document.getElementById('f_supervisor');

let currentModel = null;
let currentHeaderId = null;
let rows = []; // [{rpm, cc_sec, shim}]

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
    currentHeaderId = null;
    rows = [];
    if (!modelId) {
        currentModel = null;
        fopCodeEl.textContent = stdCcSecEl.textContent = rpmStdEl.textContent = masterTestEl.textContent = '-';
        statusLabel.textContent = '';
        render();
        return;
    }

    statusLabel.textContent = 'Loading...';
    const res = await fetch(`ajax/get_fopump_test_model.php?model_id=${modelId}`);
    const data = await res.json();
    currentModel = data.model || null;
    fopCodeEl.textContent = currentModel?.fop_code || '-';
    stdCcSecEl.textContent = currentModel?.standard_cc_sec || '-';
    rpmStdEl.textContent = currentModel?.rpm || '-';
    masterTestEl.textContent = currentModel?.master_test || '-';

    const header = data.header;
    currentHeaderId = header ? header.id : null;
    destinationEl.value = header?.destination ?? 'local';
    oilPressureEl.value = header?.oil_pressure ?? '';
    oilTempEl.value = header?.oil_temp ?? '';
    roomTempEl.value = header?.room_temp ?? '';
    startTestTimeEl.value = header?.start_test_time ?? '';
    checkerEl.value = header?.checker_id ?? '';
    foremanEl.value = header?.foreman_id ?? '';
    supervisorEl.value = header?.supervisor_id ?? '';

    rows = (data.rows && data.rows.length) ? data.rows.map(r => ({ rpm: r.rpm ?? '', cc_sec: r.cc_sec ?? '', shim: r.shim ?? '' })) : [defaultRow()];
    render();

    statusLabel.textContent = header
        ? (header.status === 'draft' ? 'Editing a saved draft for this model.' : 'This model already has a submitted record — saving will update it.')
        : 'New record for this model.';
}

const modelOptions = (typeof MODELS !== 'undefined' ? MODELS : []).map(m => ({ value: m.id, label: m.name }));
const modelResolver = turnIntoCombo(document.getElementById('f_model'), modelOptions, {
    allowCustom: false,
    onSelect: loadModel,
});

function buildPayload(status) {
    return {
        header_id: currentHeaderId,
        status,
        department_id: DEPARTMENT_ID,
        model_id: modelResolver.getValue(),
        destination: destinationEl.value,
        oil_pressure: oilPressureEl.value,
        oil_temp: oilTempEl.value,
        room_temp: roomTempEl.value,
        start_test_time: startTestTimeEl.value,
        checker_id: checkerEl.value,
        foreman_id: foremanEl.value,
        supervisor_id: supervisorEl.value,
        rows,
    };
}

async function saveChecksheet(status) {
    statusLabel.textContent = 'Saving...';
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_fopump_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!data.success) {
        statusLabel.textContent = '';
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    currentHeaderId = data.header_id;
    if (status === 'draft') {
        statusLabel.textContent = 'Editing a saved draft for this model.';
        alert('Saved as draft.');
    } else {
        statusLabel.textContent = 'This model already has a submitted record — saving will update it.';
        alert('Checksheet submitted successfully.');
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadModel();

const tanggalInput = document.getElementById('f_tanggal');
const employeeInput = document.getElementById('f_employee');
const workingTimeInput = document.getElementById('f_working_time');
const shiftInput = document.getElementById('f_shift');
const operatorSelect = document.getElementById('f_operator');
const foremanSelect = document.getElementById('f_foreman');
const supervisorSelect = document.getElementById('f_supervisor');
const tbody = document.getElementById('fopump-tbody');
const tfoot = document.getElementById('fopump-tfoot');
const statusLabel = document.getElementById('fopump-status-label');

let currentHeaderId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
let priorAccum = { production: 0, assembly: 0, export: 0 };
let rowCount = 9;
const modelOptions = (typeof MODEL_NAMES !== 'undefined' ? MODEL_NAMES : []).map(n => ({ value: n, label: n }));

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function rowHtml(no, l) {
    l = l || {};
    return `<tr>
        <td class="fopump-no">${no}</td>
        <td><input type="text" class="fopump-model" data-cat="production" data-field="model" data-no="${no}" value="${escapeHtml(l.production_model ?? '')}"></td>
        <td><input type="number" class="fopump-qty" min="0" data-cat="production" data-field="qty" data-no="${no}" value="${escapeHtml(l.production_qty ?? '')}"></td>
        <td><input type="text" class="fopump-model" data-cat="assembly" data-field="model" data-no="${no}" value="${escapeHtml(l.assembly_model ?? '')}"></td>
        <td><input type="number" class="fopump-qty" min="0" data-cat="assembly" data-field="qty" data-no="${no}" value="${escapeHtml(l.assembly_qty ?? '')}"></td>
        <td><input type="text" class="fopump-model" data-cat="export" data-field="model" data-no="${no}" value="${escapeHtml(l.export_model ?? '')}"></td>
        <td><input type="number" class="fopump-qty" min="0" data-cat="export" data-field="qty" data-no="${no}" value="${escapeHtml(l.export_qty ?? '')}"></td>
    </tr>`;
}

function renderRows(lines) {
    const byNo = {};
    (lines || []).forEach((l) => { byNo[l.line_no] = l; });

    // Always show at least 9 rows, but grow to fit any saved data that
    // already goes past row 9 (e.g. a report edited on a bigger screen).
    const maxSavedNo = Math.max(0, ...Object.keys(byNo).map(Number));
    rowCount = Math.max(9, maxSavedNo);

    let html = '';
    for (let no = 1; no <= rowCount; no++) html += rowHtml(no, byNo[no]);
    tbody.innerHTML = html;
    tbody.querySelectorAll('.fopump-model').forEach((el) => turnIntoCombo(el, modelOptions, { allowCustom: true }));
}

function addRow() {
    rowCount += 1;
    tbody.insertAdjacentHTML('beforeend', rowHtml(rowCount, null));
    const newRow = tbody.lastElementChild;
    newRow.querySelectorAll('.fopump-model').forEach((el) => turnIntoCombo(el, modelOptions, { allowCustom: true }));
}

function renderFoot(header) {
    const conv = {
        production: header?.convert_production ?? '',
        assembly: header?.convert_assembly ?? '',
        export: header?.convert_export ?? '',
    };
    tfoot.innerHTML = `
        <tr class="fopump-total-row">
            <td>Total</td>
            <td></td><td class="fopump-total" data-cat="production">0</td>
            <td></td><td class="fopump-total" data-cat="assembly">0</td>
            <td></td><td class="fopump-total" data-cat="export">0</td>
        </tr>
        <tr class="fopump-convert-row">
            <td>Convert</td>
            <td></td><td><input type="number" class="fopump-convert" data-cat="production" value="${escapeHtml(conv.production)}"></td>
            <td></td><td><input type="number" class="fopump-convert" data-cat="assembly" value="${escapeHtml(conv.assembly)}"></td>
            <td></td><td><input type="number" class="fopump-convert" data-cat="export" value="${escapeHtml(conv.export)}"></td>
        </tr>
        <tr class="fopump-accum-row">
            <td>Acumulation</td>
            <td></td><td class="fopump-accum" data-cat="production">0</td>
            <td></td><td class="fopump-accum" data-cat="assembly">0</td>
            <td></td><td class="fopump-accum" data-cat="export">0</td>
        </tr>`;
}

function sumCat(cat) {
    let total = 0;
    tbody.querySelectorAll(`.fopump-qty[data-cat="${cat}"]`).forEach((el) => {
        const v = parseInt(el.value, 10);
        if (!isNaN(v)) total += v;
    });
    return total;
}

function updateTotals() {
    ['production', 'assembly', 'export'].forEach((cat) => {
        const total = sumCat(cat);
        tfoot.querySelector(`.fopump-total[data-cat="${cat}"]`).textContent = total;
        tfoot.querySelector(`.fopump-accum[data-cat="${cat}"]`).textContent = total + (priorAccum[cat] || 0);
    });
}

async function loadContext() {
    const tanggal = tanggalInput.value;
    if (!tanggal) return;
    statusLabel.textContent = 'Loading...';

    const res = await fetch(`ajax/get_fopump_context.php?department_id=${DEPARTMENT_ID}&tanggal=${tanggal}`);
    const data = await res.json();

    priorAccum = data.prior_accum || { production: 0, assembly: 0, export: 0 };
    const header = data.header;
    currentHeaderId = header ? header.id : null;

    employeeInput.value = header?.employee_count ?? '';
    workingTimeInput.value = header?.working_minutes ?? '';
    shiftInput.value = header?.shift_label ?? '';
    operatorSelect.value = header?.operator_id ?? '';
    foremanSelect.value = header?.foreman_id ?? '';
    supervisorSelect.value = header?.supervisor_id ?? '';

    renderRows(data.lines);
    renderFoot(header);
    updateTotals();

    statusLabel.textContent = header
        ? (header.status === 'draft' ? 'Editing a saved draft for this date.' : 'This date already has a submitted report — saving will update it.')
        : 'New entry for this date.';
}

tbody.addEventListener('input', (e) => {
    if (e.target.matches('.fopump-qty')) updateTotals();
});

tanggalInput.addEventListener('change', loadContext);

function buildPayload(status) {
    const lines = [];
    for (let no = 1; no <= rowCount; no++) {
        lines.push({
            line_no: no,
            production_model: tbody.querySelector(`[data-cat="production"][data-field="model"][data-no="${no}"]`).value.trim(),
            production_qty: tbody.querySelector(`[data-cat="production"][data-field="qty"][data-no="${no}"]`).value,
            assembly_model: tbody.querySelector(`[data-cat="assembly"][data-field="model"][data-no="${no}"]`).value.trim(),
            assembly_qty: tbody.querySelector(`[data-cat="assembly"][data-field="qty"][data-no="${no}"]`).value,
            export_model: tbody.querySelector(`[data-cat="export"][data-field="model"][data-no="${no}"]`).value.trim(),
            export_qty: tbody.querySelector(`[data-cat="export"][data-field="qty"][data-no="${no}"]`).value,
        });
    }
    return {
        header_id: currentHeaderId,
        status,
        tanggal: tanggalInput.value,
        department_id: DEPARTMENT_ID,
        employee_count: employeeInput.value,
        working_minutes: workingTimeInput.value,
        shift_label: shiftInput.value,
        operator_id: operatorSelect.value,
        foreman_id: foremanSelect.value,
        supervisor_id: supervisorSelect.value,
        convert_production: tfoot.querySelector('.fopump-convert[data-cat="production"]').value,
        convert_assembly: tfoot.querySelector('.fopump-convert[data-cat="assembly"]').value,
        convert_export: tfoot.querySelector('.fopump-convert[data-cat="export"]').value,
        lines,
    };
}

async function save(status) {
    statusLabel.textContent = 'Saving...';
    const res = await fetch('ajax/save_fopump.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload(status)),
    });
    const data = await res.json();
    if (data.error) {
        statusLabel.textContent = '';
        alert('Failed to save: ' + data.error);
        return;
    }
    currentHeaderId = data.header_id;
    window.location.href = `view_fopump_checksheets.php?saved=1`;
}

document.getElementById('btn-add-row').addEventListener('click', addRow);

document.getElementById('btn-draft').addEventListener('click', () => save('draft'));
document.getElementById('btn-submit').addEventListener('click', () => {
    if (confirm('Submit this FO Pump daily report?')) save('submitted');
});

loadContext();

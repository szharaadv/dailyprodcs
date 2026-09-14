const headRow = document.getElementById('fopump-check-head-row');
const tbody = document.getElementById('fopump-check-tbody');
const fopCodeEl = document.getElementById('f_fop_code');
const partNoEl = document.getElementById('f_part_no');
const statusLabel = document.getElementById('fopump-check-status-label');
const prodDateCodeEl = document.getElementById('f_prod_date_code');
const stepperEl = document.getElementById('signoff-stepper');
const badgeEl = document.getElementById('signoff-badge');

function fmtSignTime(at) {
    if (!at) return '';
    const d = new Date(at.replace(' ', 'T'));
    if (isNaN(d)) return '';
    const p = n => String(n).padStart(2, '0');
    return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

/** First unsigned step in order — the one whose "turn" it is. */
function nextRole(h) {
    if (!h || !h.checker_at) return 'checker';
    if (!h.foreman_at) return 'foreman';
    if (!h.supervisor_at) return 'supervisor';
    return null;
}

/** Overall status label + css class. */
function overallStatus(h) {
    if (!h) return { label: 'Belum diisi', cls: 'empty' };
    if (h.supervisor_at) return { label: 'Selesai', cls: 'done' };
    if (h.foreman_at) return { label: 'Menunggu Supervisor', cls: 'waiting' };
    if (h.checker_at) return { label: 'Menunggu Foreman', cls: 'waiting' };
    return { label: 'Belum diisi', cls: 'empty' };
}

// Build the sign-off stepper: Checker → Foreman → Supervisor, each showing the
// signer + time when done, "Giliran Anda" on the viewer's own pending line.
function renderSignoff(header) {
    if (!stepperEl || !badgeEl) return; // sign-off stepper hidden on this page
    const steps = [
        { role: 'checker', label: 'Checker', name: header && header.checker_name, at: header && header.checker_at },
        { role: 'foreman', label: 'Foreman', name: header && header.foreman_name, at: header && header.foreman_at },
        { role: 'supervisor', label: 'Supervisor', name: header && header.supervisor_name, at: header && header.supervisor_at },
    ];
    const nr = nextRole(header);
    stepperEl.innerHTML = steps.map((s, i) => {
        const signed = !!s.at;
        let cls = 'signoff-step', dot = String(i + 1), body;
        if (signed) {
            cls += ' done'; dot = '&#10003;';
            body = `<div class="signoff-name">${esc(s.name || '—')}</div><div class="signoff-time">${esc(fmtSignTime(s.at))}</div>`;
        } else if (CURRENT_USER.role === s.role) {
            cls += ' you';
            body = `<div class="signoff-hint">Giliran Anda — isi saat Submit</div>`;
        } else {
            if (nr === s.role) cls += ' next';
            body = `<div class="signoff-name" style="color:#9aa1ab;">belum</div>`;
        }
        return `<div class="${cls}"><span class="signoff-dot">${dot}</span><div class="signoff-role">${esc(s.label)}</div>${body}</div>`;
    }).join('');

    const ov = overallStatus(header);
    badgeEl.textContent = ov.label;
    badgeEl.className = 'signoff-badge ' + ov.cls;
}

let currentItems = [];
let currentModel = null;
let currentHeaderId = null;
let samples = []; // [{sample_no}]
let values = {};  // { [itemId]: [actual_result, ...] } indexed same as samples

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

function nextSampleNo() {
    if (!samples.length) return '1';
    const last = parseFloat(samples[samples.length - 1].sample_no);
    return isNaN(last) ? String(samples.length + 1) : String(last + 10);
}

/** Adds a new sample column. Cells start empty so the checker fills them in. */
function addSampleColumn(sampleNo) {
    samples.push({ sample_no: sampleNo });
    const idx = samples.length - 1;
    currentItems.forEach(item => {
        if (!values[item.id]) values[item.id] = [];
        values[item.id][idx] = '';
    });
}

function render() {
    const colCount = 2 + samples.length;

    headRow.innerHTML = `
        <th class="fopump-check-item-col">Checking Item</th>
        <th class="fopump-check-std-col">Standard</th>
        ${samples.map((s, idx) => `
            <th class="fopump-check-sample-col">
                <input type="text" class="sample-no-input" data-sample-idx="${idx}" value="${escapeHtml(s.sample_no)}" placeholder="No.">
                <button type="button" class="sample-remove-btn" data-remove-idx="${idx}" title="Remove this sample">&times;</button>
            </th>
        `).join('')}
    `;

    if (!currentItems.length) {
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="empty">No checklist items set up for this model yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = currentItems.map(item => {
        const rowValues = values[item.id] || [];
        return `<tr>
            <td class="fopump-check-item-cell">${escapeHtml(item.checking_item)}</td>
            <td>${escapeHtml(item.standard ?? '-')}</td>
            ${samples.map((s, idx) => {
                const val = rowValues[idx] ?? '';
                if (item.result_type === 'boolean') {
                    return `<td><select class="fopump-check-input ${val === 'FALSE' ? 'val-ng' : ''}" data-item-id="${item.id}" data-sample-idx="${idx}">
                        <option value="" ${val === '' ? 'selected' : ''}>—</option>
                        <option value="TRUE" ${val === 'TRUE' ? 'selected' : ''}>TRUE</option>
                        <option value="FALSE" ${val === 'FALSE' ? 'selected' : ''}>FALSE</option>
                    </select></td>`;
                }
                return `<td><input type="text" class="fopump-check-input" data-item-id="${item.id}" data-sample-idx="${idx}" value="${escapeHtml(val)}"></td>`;
            }).join('')}
        </tr>`;
    }).join('');
}

function applyValue(el) {
    const itemId = el.dataset.itemId;
    const idx = parseInt(el.dataset.sampleIdx, 10);
    if (!values[itemId]) values[itemId] = [];
    values[itemId][idx] = el.value;
    if (el.tagName === 'SELECT') el.classList.toggle('val-ng', el.value === 'FALSE');
}

tbody.addEventListener('input', (e) => {
    if (!e.target.matches('.fopump-check-input')) return;
    applyValue(e.target);
});

tbody.addEventListener('change', (e) => {
    if (!e.target.matches('select.fopump-check-input')) return;
    applyValue(e.target);
});

headRow.addEventListener('input', (e) => {
    if (!e.target.matches('.sample-no-input')) return;
    const idx = parseInt(e.target.dataset.sampleIdx, 10);
    samples[idx].sample_no = e.target.value;
});

headRow.addEventListener('click', (e) => {
    const btn = e.target.closest('.sample-remove-btn');
    if (!btn) return;
    const idx = parseInt(btn.dataset.removeIdx, 10);
    samples.splice(idx, 1);
    currentItems.forEach(item => {
        if (values[item.id]) values[item.id].splice(idx, 1);
    });
    render();
});

document.getElementById('btn-add-sample').addEventListener('click', () => {
    addSampleColumn(nextSampleNo());
    render();
});

async function loadItems() {
    tbody.innerHTML = '<tr><td colspan="2" class="empty">Loading data...</td></tr>';
    const modelId = modelEl.value;
    currentHeaderId = null;
    samples = [];
    values = {};
    if (!modelId) {
        currentItems = [];
        currentModel = null;
        fopCodeEl.textContent = '-';
        partNoEl.textContent = '-';
        statusLabel.textContent = '';
        render();
        return;
    }

    statusLabel.textContent = 'Loading...';
    const res = await fetch(`ajax/get_fopump_check_items.php?model_id=${modelId}`);
    const data = await res.json();
    currentItems = data.items || [];
    currentModel = data.model || null;
    fopCodeEl.textContent = currentModel?.fop_code || '-';
    partNoEl.textContent = currentModel?.part_no || '-';

    const header = data.header;
    currentHeaderId = header ? header.id : null;
    prodDateCodeEl.value = header ? (header.prod_date_code ?? '') : '';
    renderSignoff(header);

    const loadedSamples = data.samples || [];
    if (loadedSamples.length) {
        samples = loadedSamples.map(s => ({ sample_no: s.sample_no }));
        currentItems.forEach(item => {
            const savedForItem = (data.values || {})[item.id] || {};
            values[item.id] = loadedSamples.map(s => savedForItem[s.id] ?? '');
        });
    } else {
        addSampleColumn('1');
    }

    render();

    // Clear, trace-friendly status line: where this record stands now.
    if (!header) {
        statusLabel.textContent = 'Record baru untuk model ini.';
    } else if (header.status === 'draft') {
        statusLabel.textContent = 'Masih draft untuk model ini.';
    } else {
        const nr = nextRole(header);
        const nrLabel = { checker: 'Checker', foreman: 'Foreman', supervisor: 'Supervisor' }[nr];
        statusLabel.textContent = nr ? ('Sudah disubmit — menunggu tanda tangan ' + nrLabel + '.') : 'Sign-off lengkap. Selesai ✓';
    }
}

const modelEl = document.getElementById('f_model');
modelEl.addEventListener('change', loadItems);

function buildPayload(status) {
    const rows = currentItems.map(item => ({
        checklist_item_id: item.id,
        actuals: samples.map((s, idx) => (values[item.id] || [])[idx] ?? ''),
    }));

    // Note: checker/foreman/supervisor identity is NOT sent from the client —
    // the server stamps the signature line for whoever is logged in, by role.
    return {
        header_id: currentHeaderId,
        status,
        department_id: DEPARTMENT_ID,
        model_id: modelEl.value,
        prod_date_code: prodDateCodeEl.value,
        samples: samples.map(s => s.sample_no),
        rows,
    };
}

async function saveChecksheet(status, silent = false) {
    if (!silent) statusLabel.textContent = 'Saving...';
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_fopump_check.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
    });
    const data = await res.json();

    if (!data.success) {
        if (silent) return false;
        statusLabel.textContent = '';
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    currentHeaderId = data.header_id;
    if (silent) return true; // autosave: don't alert or reload (would wipe in-progress edits)
    alert(status === 'draft'
        ? 'Tersimpan sebagai draft.'
        : 'Tersimpan. Tanda tangan Anda tercatat — lihat progres sign-off di atas.');
    // Reload so the sign-off stepper shows your just-saved signature + time.
    loadItems();
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => {
    if (window.checksheetComplete && !checksheetComplete()) return;
    saveChecksheet('submitted');
});
// No autosave here: this sheet updates one record per model in place, so
// switching models while browsing would keep creating empty draft records.
// Use the explicit "Save as Draft" button instead.

loadItems();

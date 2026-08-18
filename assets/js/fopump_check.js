const headRow = document.getElementById('fopump-check-head-row');
const tbody = document.getElementById('fopump-check-tbody');
const fopCodeEl = document.getElementById('f_fop_code');
const partNoEl = document.getElementById('f_part_no');

let currentItems = [];
let currentModel = null;
let samples = []; // [{sample_no}]
let values = {};  // { [itemId]: [actual_result, ...] } indexed same as samples
let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftSamples = typeof DRAFT_SAMPLES !== 'undefined' ? DRAFT_SAMPLES : [];
const draftValues = typeof DRAFT_VALUES !== 'undefined' ? DRAFT_VALUES : {};

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

/** The conforming default an item's actual result should start at. */
function defaultValueFor(item) {
    if (item.expected_value !== null && item.expected_value !== undefined && item.expected_value !== '') {
        return item.expected_value;
    }
    return item.result_type === 'boolean' ? 'TRUE' : '';
}

/** Adds a new sample column, pre-filling every item's cell with its conforming default. */
function addSampleColumn(sampleNo) {
    samples.push({ sample_no: sampleNo });
    const idx = samples.length - 1;
    currentItems.forEach(item => {
        if (!values[item.id]) values[item.id] = [];
        values[item.id][idx] = defaultValueFor(item);
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
                        <option value="TRUE" ${val !== 'FALSE' ? 'selected' : ''}>TRUE</option>
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
    const modelId = modelResolver.getValue();
    if (!modelId) {
        currentItems = [];
        currentModel = null;
        fopCodeEl.textContent = '-';
        partNoEl.textContent = '-';
        render();
        return;
    }
    const res = await fetch(`ajax/get_fopump_check_items.php?model_id=${modelId}`);
    const data = await res.json();
    currentItems = data.items || [];
    currentModel = data.model || null;
    fopCodeEl.textContent = currentModel?.fop_code || '-';
    partNoEl.textContent = currentModel?.part_no || '-';

    // Preserve loaded-draft values on the first load only.
    if (currentDraftId && draftSamples.length && Object.keys(values).length === 0) {
        samples = draftSamples.map(s => ({ sample_no: s.sample_no }));
        currentItems.forEach(item => {
            const savedForItem = draftValues[item.id] || {};
            values[item.id] = draftSamples.map(s => savedForItem[s.id] ?? '');
        });
    } else if (!samples.length) {
        addSampleColumn('1');
    }

    render();
}

const modelOptions = (typeof MODELS !== 'undefined' ? MODELS : []).map(m => ({ value: m.id, label: m.name }));
const modelResolver = turnIntoCombo(document.getElementById('f_model'), modelOptions, {
    allowCustom: false,
    onSelect: () => {
        values = {};
        samples = [];
        loadItems();
    },
});

function buildPayload(status) {
    const rows = currentItems.map(item => ({
        checklist_item_id: item.id,
        actuals: samples.map((s, idx) => (values[item.id] || [])[idx] ?? ''),
    }));

    return {
        header_id: currentDraftId,
        status,
        tanggal: document.getElementById('f_tanggal').value,
        department_id: DEPARTMENT_ID,
        model_id: modelResolver.getValue(),
        prod_date_code: document.getElementById('f_prod_date_code').value,
        checker_id: document.getElementById('f_checker').value,
        foreman_id: document.getElementById('f_foreman').value,
        supervisor_id: document.getElementById('f_supervisor').value,
        samples: samples.map(s => s.sample_no),
        rows,
    };
}

async function saveChecksheet(status) {
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_fopump_check.php', {
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
        window.location.href = 'view_fopump_check_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadItems();

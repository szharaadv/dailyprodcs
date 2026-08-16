const conditionSelect = document.getElementById('f_condition');
const tbody = document.getElementById('checklist-tbody');
let currentItems = [];
let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftValues = typeof DRAFT_VALUES !== 'undefined' ? DRAFT_VALUES : {};

function renderRows(items) {
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty">No checklist items set up for this condition yet.</td></tr>';
        updateProgress();
        return;
    }

    tbody.innerHTML = items.map(item => {
        const saved = draftValues[item.id] || null;

        const actualCell = item.actual_input_type === 'select'
            ? buildSelect(`actual-select`, item.id, 'actual', item.actual_options, saved?.actual)
            : `<input type="text" class="actual-input" data-item-id="${item.id}" data-field="actual" value="${escapeHtml(saved?.actual ?? '')}">`;

        const categoryCell = item.category_options
            ? buildCategoryCell(item.id, item.category_options, saved?.category)
            : '<span>-</span>';

        return `<tr>
            <td>${conditionSelect.options[conditionSelect.selectedIndex]?.text ?? ''}</td>
            <td>${escapeHtml(item.checking_item)}</td>
            <td>${escapeHtml(item.metode_pengecekan)}</td>
            <td>${escapeHtml(item.standard_min ?? '-')}</td>
            <td>${escapeHtml(item.standard_max ?? '-')}</td>
            <td>${escapeHtml(item.tank_tube ?? '-')}</td>
            <td>${escapeHtml(item.satuan ?? '-')}</td>
            <td>${actualCell}</td>
            <td>${categoryCell}</td>
        </tr>`;
    }).join('');

    updateProgress();
}

function buildSelect(cssClass, itemId, field, optionsCsv, selectedValue) {
    const options = optionsCsv.split(',').map(o => o.trim()).filter(Boolean);
    const opts = ['<option value="">-</option>']
        .concat(options.map(o => `<option value="${escapeHtml(o)}" ${o === selectedValue ? 'selected' : ''}>${escapeHtml(o)}</option>`))
        .join('');
    return `<select class="${cssClass}" data-item-id="${itemId}" data-field="${field}">${opts}</select>`;
}

function buildCategoryCell(itemId, optionsCsv, selectedValue) {
    const options = optionsCsv.split(',').map(o => o.trim()).filter(Boolean);
    const normalized = options.map(o => o.toUpperCase()).sort().join(',');

    if (normalized === 'NG,OK') {
        const okVal = options.find(o => o.toUpperCase() === 'OK') || 'OK';
        const ngVal = options.find(o => o.toUpperCase() === 'NG') || 'NG';
        const isOk = selectedValue === okVal;
        const isNg = selectedValue === ngVal;
        return `<div class="cat-toggle" data-item-id="${itemId}">
            <input type="hidden" class="category-value" data-item-id="${itemId}" data-field="category" value="${escapeHtml(selectedValue ?? '')}">
            <span class="cat-btn cat-ok ${isOk ? 'active' : ''}" data-value="${escapeHtml(okVal)}">OK</span>
            <span class="cat-btn cat-ng ${isNg ? 'active' : ''}" data-value="${escapeHtml(ngVal)}">NG</span>
        </div>`;
    }

    return buildSelect('category-select', itemId, 'category', optionsCsv, selectedValue);
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

function updateProgress() {
    const bar = document.getElementById('progress-bar-fill');
    const label = document.getElementById('progress-label');
    if (!bar || !label) return;

    const total = currentItems.length;
    const filled = currentItems.filter(item => {
        const el = document.querySelector(`[data-item-id="${item.id}"][data-field="actual"]`);
        return el && el.value.trim() !== '';
    }).length;

    label.textContent = total ? `${filled} of ${total} items filled` : 'No items to fill';
    bar.style.width = total ? `${Math.round((filled / total) * 100)}%` : '0%';
}

tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.cat-btn');
    if (!btn) return;
    const wrapper = btn.closest('.cat-toggle');
    const hidden = wrapper.querySelector('.category-value');
    hidden.value = btn.dataset.value;
    wrapper.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
});

// ---- Auto OK/NG from Standard vs Actual Result ----
function toNum(v) {
    if (v === null || v === undefined) return null;
    const n = parseFloat(String(v).trim().replace(',', '.'));
    return isNaN(n) ? null : n;
}

/** Returns 'OK' | 'NG' | null (null = no standard / empty actual → leave manual). */
function autoVerdict(item, actualRaw) {
    const actual = (actualRaw ?? '').toString().trim();
    if (actual === '') return null;

    const minRaw = (item.standard_min ?? '').toString().trim();
    const maxRaw = (item.standard_max ?? '').toString().trim();
    const hasMin = minRaw !== '' && minRaw !== '-';
    const hasMax = maxRaw !== '' && maxRaw !== '-';
    if (!hasMin && !hasMax) return null; // no standard → don't auto-decide

    const min = toNum(minRaw), max = toNum(maxRaw), val = toNum(actual);

    // Numeric range check.
    if (val !== null && (min !== null || max !== null)) {
        if (min !== null && val < min) return 'NG';
        if (max !== null && val > max) return 'NG';
        return 'OK';
    }

    // Non-numeric expected value (e.g. "Tidak Bocor"): must match exactly.
    const expected = hasMin ? minRaw : maxRaw;
    return actual.toLowerCase() === expected.toLowerCase() ? 'OK' : 'NG';
}

/** Apply the auto verdict to an item's OK/NG toggle + actual-field styling. */
function applyAutoCategory(itemId) {
    const item = currentItems.find(i => String(i.id) === String(itemId));
    if (!item) return;
    const actualEl = document.querySelector(`[data-item-id="${itemId}"][data-field="actual"]`);
    const verdict = autoVerdict(item, actualEl ? actualEl.value : '');

    if (actualEl) {
        actualEl.classList.toggle('val-ng', verdict === 'NG');
        actualEl.classList.toggle('val-ok', verdict === 'OK');
    }

    const toggle = document.querySelector(`.cat-toggle[data-item-id="${itemId}"]`);
    const hidden = document.querySelector(`.category-value[data-item-id="${itemId}"]`);
    if (!toggle || !hidden) return; // only auto-fill OK/NG toggles

    const okBtn = toggle.querySelector('.cat-ok');
    const ngBtn = toggle.querySelector('.cat-btn.cat-ng');
    toggle.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    if (verdict === 'OK' && okBtn) { hidden.value = okBtn.dataset.value; okBtn.classList.add('active'); }
    else if (verdict === 'NG' && ngBtn) { hidden.value = ngBtn.dataset.value; ngBtn.classList.add('active'); }
    else { hidden.value = ''; }
}

tbody.addEventListener('input', (e) => {
    if (e.target.matches('.actual-input')) { applyAutoCategory(e.target.dataset.itemId); updateProgress(); }
});
tbody.addEventListener('change', (e) => {
    if (e.target.matches('.actual-select')) { applyAutoCategory(e.target.dataset.itemId); updateProgress(); }
});

async function loadItems() {
    tbody.innerHTML = '<tr><td colspan="9" class="empty">Loading data...</td></tr>';
    const conditionId = conditionSelect.value;
    if (!conditionId) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty">No conditions set up for this department yet.</td></tr>';
        currentItems = [];
        updateProgress();
        return;
    }
    const res = await fetch(`ajax/get_items.php?condition_id=${conditionId}`);
    const data = await res.json();
    currentItems = data.items || [];
    renderRows(currentItems);
}

conditionSelect.addEventListener('change', loadItems);

function buildPayload(status) {
    const rows = currentItems.map(item => {
        const actualEl = document.querySelector(`[data-item-id="${item.id}"][data-field="actual"]`);
        const categoryEl = document.querySelector(`[data-item-id="${item.id}"][data-field="category"]`);
        return {
            checklist_item_id: item.id,
            actual_result: actualEl ? actualEl.value : null,
            category: categoryEl ? categoryEl.value : null,
        };
    });

    return {
        header_id: currentDraftId,
        status,
        tanggal: document.getElementById('f_tanggal').value,
        department_id: DEPARTMENT_ID,
        condition_id: conditionSelect.value,
        checker_id: document.getElementById('f_checker').value,
        jam: document.getElementById('f_jam').value,
        shift_id: document.getElementById('f_shift').value,
        rows,
    };
}

async function saveChecksheet(status) {
    const payload = buildPayload(status);

    const res = await fetch('ajax/save_checksheet.php', {
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
        window.location.href = 'view_checksheets.php';
    }
}

document.getElementById('btn-draft').addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => saveChecksheet('submitted'));

loadItems();

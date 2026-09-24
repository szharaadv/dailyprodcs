const tbody = document.getElementById('assy-tbody');
let currentItems = [];
let currentDraftId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
const draftValues = typeof DRAFT_VALUES !== 'undefined' ? DRAFT_VALUES : {};

// Revision mode: opened on a submitted record from assy_engine_revision.php.
// Only the record's own items are shown, each with an × to drop it; on save,
// dropped items are removed and the rest updated (see buildPayload / save).
const reviseMode = typeof REVISE_MODE !== 'undefined' && REVISE_MODE;
const reviseItemIds = (typeof REVISE_ITEM_IDS !== 'undefined' ? REVISE_ITEM_IDS : []).map(Number);
const removedIds = new Set();
const colspan = reviseMode ? 7 : 6;
const removeCell = (id) => reviseMode
    ? `<td class="assy-remove-cell"><button type="button" class="assy-remove-btn" data-remove-id="${id}" title="Hapus item">&times;</button></td>`
    : '';

function renderRows(items) {
    if (!items.length) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty">No checklist items set up for this model yet.</td></tr>`;
        return;
    }

    tbody.innerHTML = items.map(item => {
        const saved = draftValues[item.id] || null;
        const removed = removedIds.has(Number(item.id)) ? ' assy-row-removed' : '';
        // Blocked = checking item tidak berlaku untuk model/varian ini.
        // Kolom isian dikunci (readonly, tidak bisa diisi), otomatis N/A.
        if (Number(item.blocked) === 1) {
            return `<tr class="row-blocked${removed}">
                <td>${escapeHtml(item.checking_item)}</td>
                <td>${escapeHtml(item.standard ?? '-')}</td>
                <td>${escapeHtml(item.standard_min ?? '-')}</td>
                <td>${escapeHtml(item.standard_max ?? '-')}</td>
                <td><input type="text" class="actual-input blocked-input" data-item-id="${item.id}" data-field="actual" value="" placeholder="N/A" readonly tabindex="-1"></td>
                <td><input type="text" class="actual-input blocked-input" data-item-id="${item.id}" data-field="consumable" value="" placeholder="N/A" readonly tabindex="-1"></td>
                ${removeCell(item.id)}
            </tr>`;
        }
        return `<tr class="${removed.trim()}">
            <td>${escapeHtml(item.checking_item)}</td>
            <td>${escapeHtml(item.standard ?? '-')}</td>
            <td>${escapeHtml(item.standard_min ?? '-')}</td>
            <td>${escapeHtml(item.standard_max ?? '-')}</td>
            <td><input type="text" class="actual-input" data-item-id="${item.id}" data-field="actual" value="${escapeHtml(saved?.actual ?? '')}"></td>
            <td><input type="text" class="actual-input" data-optional data-item-id="${item.id}" data-field="consumable" value="${escapeHtml(saved?.consumable ?? '')}"></td>
            ${removeCell(item.id)}
        </tr>`;
    }).join('');

    // Highlight any out-of-standard actual results (also for loaded drafts).
    items.forEach(item => evaluateActual(item.id));
}

// Toggle drop/undrop of a checking item (revision mode only).
if (reviseMode) {
    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.assy-remove-btn');
        if (!btn) return;
        const id = Number(btn.dataset.removeId);
        const tr = btn.closest('tr');
        if (removedIds.has(id)) {
            removedIds.delete(id);
            tr.classList.remove('assy-row-removed');
            btn.innerHTML = '&times;';
            btn.title = 'Hapus item';
        } else {
            removedIds.add(id);
            tr.classList.add('assy-row-removed');
            btn.innerHTML = '&#8635;';
            btn.title = 'Batal hapus';
        }
    });
}

// ---- Standard vs Actual Result check (visual red/green only) ----
function toNum(v) {
    if (v === null || v === undefined) return null;
    const n = parseFloat(String(v).trim().replace(',', '.'));
    return isNaN(n) ? null : n;
}

/** 'OK' | 'NG' | null (null = no standard / empty → no colour). */
function actualVerdict(item, actualRaw) {
    const actual = (actualRaw ?? '').toString().trim();
    // Empty or a bare "-" means the item does not apply / nothing to measure —
    // treat as neutral (no colour), never NG.
    if (actual === '' || actual === '-') return null;
    const minRaw = (item.standard_min ?? '').toString().trim();
    const maxRaw = (item.standard_max ?? '').toString().trim();
    const hasMin = minRaw !== '' && minRaw !== '-';
    const hasMax = maxRaw !== '' && maxRaw !== '-';
    if (!hasMin && !hasMax) return null;
    const min = toNum(minRaw), max = toNum(maxRaw), val = toNum(actual);
    if (val !== null && (min !== null || max !== null)) {
        if (min !== null && val < min) return 'NG';
        if (max !== null && val > max) return 'NG';
        return 'OK';
    }
    const expected = hasMin ? minRaw : maxRaw;
    return actual.toLowerCase() === expected.toLowerCase() ? 'OK' : 'NG';
}

function evaluateActual(itemId) {
    const item = currentItems.find(i => String(i.id) === String(itemId));
    const el = document.querySelector(`.actual-input[data-item-id="${itemId}"][data-field="actual"]`);
    if (!item || !el) return;
    if (Number(item.blocked) === 1) return; // blocked item: no OK/NG
    const verdict = actualVerdict(item, el.value);
    el.classList.toggle('val-ng', verdict === 'NG');
    el.classList.toggle('val-ok', verdict === 'OK');
}

tbody.addEventListener('input', (e) => {
    if (e.target.matches('.actual-input[data-field="actual"]')) evaluateActual(e.target.dataset.itemId);
});

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

async function loadItems() {
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty">Loading data...</td></tr>`;
    const modelId = modelResolver.getValue();
    if (!modelId) {
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty">No models set up for this department yet.</td></tr>`;
        currentItems = [];
        return;
    }
    const res = await fetch(`ajax/get_assy_items.php?model_id=${modelId}`);
    const data = await res.json();
    currentItems = data.items || [];
    // Revision: show only the items this record actually has (dropped-in-an-
    // earlier-revision items stay gone).
    if (reviseMode && reviseItemIds.length) {
        const keep = new Set(reviseItemIds);
        currentItems = currentItems.filter(it => keep.has(Number(it.id)));
    }
    renderRows(currentItems);
}

const modelOptions = (typeof MODELS !== 'undefined' ? MODELS : []).map(m => ({ value: m.id, label: m.name }));
const modelResolver = turnIntoCombo(document.getElementById('f_model'), modelOptions, { allowCustom: false, onSelect: loadItems });

function buildPayload(status) {
    const rows = currentItems
        // Revision: dropped (×) items are excluded, so they're removed on save.
        .filter(item => !removedIds.has(Number(item.id)))
        .map(item => {
            // Blocked items are N/A — always saved empty, never the operator's input.
            if (Number(item.blocked) === 1) {
                return { checklist_item_id: item.id, actual_result: null, consumable_item: null };
            }
            const actualEl = document.querySelector(`[data-item-id="${item.id}"][data-field="actual"]`);
            const consumableEl = document.querySelector(`[data-item-id="${item.id}"][data-field="consumable"]`);
            return {
                checklist_item_id: item.id,
                actual_result: actualEl ? actualEl.value : null,
                consumable_item: consumableEl ? consumableEl.value : null,
            };
        });

    return {
        header_id: currentDraftId,
        status,
        revise: reviseMode,
        tanggal: document.getElementById('f_tanggal').value,
        department_id: DEPARTMENT_ID,
        model_id: modelResolver.getValue(),
        checker_id: document.getElementById('f_checker').value,
        mark_crank_shaft: document.getElementById('f_mark_crank_shaft').value,
        mark_conrod: document.getElementById('f_mark_conrod').value,
        mark_fo_pump: document.getElementById('f_mark_fo_pump').value,
        no_cyl_block: document.getElementById('f_no_cyl_block').value,
        no_engine: document.getElementById('f_no_engine').value,
        detail_model: document.getElementById('f_detail_model').value,
        rows,
    };
}

async function saveChecksheet(status, silent = false) {
    const payload = buildPayload(status);

    // Never let a background autosave persist an untouched form as a draft —
    // require an actual result, a consumable, or an engine-number field.
    if (silent) {
        const hasContent =
            payload.rows.some(r =>
                (r.actual_result != null && String(r.actual_result).trim() !== '') ||
                (r.consumable_item != null && String(r.consumable_item).trim() !== '')) ||
            [payload.mark_crank_shaft, payload.mark_conrod, payload.mark_fo_pump,
             payload.no_cyl_block, payload.no_engine, payload.detail_model]
                .some(v => v != null && String(v).trim() !== '');
        if (!hasContent) return false;
    }

    const res = await fetch('ajax/save_assy_checksheet.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
    });
    const data = await res.json();

    if (!data.success) {
        if (silent) return false;
        alert('Failed to save: ' + (data.error || 'unknown error'));
        return;
    }

    if (status === 'draft') {
        currentDraftId = data.header_id;
        if (silent) return true;
        alert('Saved as draft. You can continue it later from the My Drafts menu.');
    } else {
        if (window.stopAutosaveDraft) stopAutosaveDraft();
        alert(reviseMode ? 'Revisi tersimpan.' : 'Checksheet submitted successfully.');
        window.location.href = reviseMode ? 'assy_engine_revision.php' : 'view_assy_checksheets.php';
    }
}

document.getElementById('btn-draft')?.addEventListener('click', () => saveChecksheet('draft'));
document.getElementById('btn-submit').addEventListener('click', () => {
    if (window.checksheetComplete && !checksheetComplete()) return;
    if (reviseMode && !confirm('Simpan revisi? Item yang ditandai × akan dihapus dari checksheet.')) return;
    if (window.stopAutosaveDraft) stopAutosaveDraft(); // no draft save may race the submit
    saveChecksheet('submitted');
});
// Enter anywhere on the sheet triggers Submit — except inside the Model combo,
// where Enter picks the highlighted option.
document.querySelector('.checksheet-card')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || e.isComposing || e.defaultPrevented) return;
    const t = e.target;
    if (!t || (t.tagName !== 'INPUT' && t.tagName !== 'SELECT')) return;
    if (t.classList.contains('combo-input')) return; // Model combo: Enter selects
    e.preventDefault();
    document.getElementById('btn-submit').click();
});

// No autosave in revision mode — a revision is an explicit, deliberate save.
if (!reviseMode && window.initAutosaveDraft) initAutosaveDraft({ save: () => saveChecksheet('draft', true) });

loadItems();

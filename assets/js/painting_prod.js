const hariInput = document.getElementById('f_hari');
const tanggalInput = document.getElementById('f_tanggal');
const pekerjaSelect = document.getElementById('f_pekerja');
const shiftSelect = document.getElementById('f_shift');
const tbody = document.getElementById('pprod-tbody');
const tfoot = document.getElementById('pprod-tfoot');
const statusLabel = document.getElementById('pprod-status-label');

// The five painting quantity buckets — each gets its own Total + Acumulation,
// exactly like FO Pump's three production categories.
const CATS = ['cb', 'fot', 'fw', 'part', 'others'];
const CAT_LABELS = { cb: 'CB', fot: 'FOT', fw: 'FW', part: 'PART', others: 'OTHERS' };
// AKUMULASI is a single grand total that EXCLUDES the OTHERS column.
const ACCUM_CATS = CATS.filter((c) => c !== 'others');
const HARI_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

let currentHeaderId = typeof DRAFT_ID !== 'undefined' ? DRAFT_ID : null;
let priorAccum = Object.fromEntries(CATS.map((c) => [c, 0]));
let rowCount = 9;
const modelOptions = (typeof MODEL_NAMES !== 'undefined' ? MODEL_NAMES : []).map((n) => ({ value: n, label: n }));

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function setHari(dateStr) {
    if (!hariInput) return;
    // Parse as local date (avoid the UTC shift a bare Date(string) can cause).
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dateStr || '');
    hariInput.value = m ? HARI_NAMES[new Date(+m[1], +m[2] - 1, +m[3]).getDay()] : '';
}

function qtyCell(cat, no, val) {
    return `<td><input type="number" class="fopump-qty pprod-qty" min="0" data-cat="${cat}" data-no="${no}" value="${escapeHtml(val ?? '')}"></td>`;
}

function rowHtml(no, l) {
    l = l || {};
    return `<tr>
        <td class="fopump-no">${no}</td>
        <td><input type="text" class="fopump-model pprod-model" data-field="model" data-no="${no}" value="${escapeHtml(l.model ?? '')}"></td>
        ${CATS.map((c) => qtyCell(c, no, l[c])).join('')}
        <td><input type="text" class="pprod-note" data-field="keterangan" data-no="${no}" value="${escapeHtml(l.keterangan ?? '')}"></td>
    </tr>`;
}

function renderRows(lines) {
    const byNo = {};
    (lines || []).forEach((l) => { byNo[l.line_no] = l; });

    // Always show at least 9 rows, but grow to fit any saved data past row 9.
    const maxSavedNo = Math.max(0, ...Object.keys(byNo).map(Number));
    rowCount = Math.max(9, maxSavedNo);

    let html = '';
    for (let no = 1; no <= rowCount; no++) html += rowHtml(no, byNo[no]);
    tbody.innerHTML = html;
    tbody.querySelectorAll('.pprod-model').forEach((el) => turnIntoCombo(el, modelOptions, { allowCustom: true }));
}

function addRow() {
    rowCount += 1;
    tbody.insertAdjacentHTML('beforeend', rowHtml(rowCount, null));
    const newRow = tbody.lastElementChild;
    newRow.querySelectorAll('.pprod-model').forEach((el) => turnIntoCombo(el, modelOptions, { allowCustom: true }));
}

function renderFoot() {
    const totalCells = CATS.map((c) => `<td class="fopump-total" data-cat="${c}">0</td>`).join('');
    // AKUMULASI is a single merged cell spanning CB..PART: those columns hold the
    // same figure by design, so it shows that one column's month-to-date running
    // total (accumulated across days), not their sum. OTHERS gets its own empty cell.
    tfoot.innerHTML = `
        <tr class="fopump-total-row">
            <td colspan="2">TOTAL</td>
            ${totalCells}
            <td></td>
        </tr>
        <tr class="fopump-accum-row">
            <td colspan="2">AKUMULASI</td>
            <td class="fopump-accum" colspan="${ACCUM_CATS.length}">0</td>
            <td></td>
            <td></td>
        </tr>`;
}

function sumCat(cat) {
    let total = 0;
    tbody.querySelectorAll(`.pprod-qty[data-cat="${cat}"]`).forEach((el) => {
        const v = parseInt(el.value, 10);
        if (!isNaN(v)) total += v;
    });
    return total;
}

function updateTotals() {
    CATS.forEach((cat) => {
        tfoot.querySelector(`.fopump-total[data-cat="${cat}"]`).textContent = sumCat(cat);
    });
    // CB/FOT/FW/PART carry the same figure by design (one production counted per
    // stage), so AKUMULASI is NOT their sum — it's a single column's month-to-date
    // running total that grows day by day (like FO Pump). OTHERS is not included.
    // It's only shown once all four columns have been filled for the day.
    const ref = ACCUM_CATS[0]; // 'cb' — representative column
    const allFilled = ACCUM_CATS.every((cat) => sumCat(cat) > 0);
    const accum = sumCat(ref) + (priorAccum[ref] || 0);
    const accumCell = tfoot.querySelector('.fopump-accum');
    if (accumCell) accumCell.textContent = allFilled ? accum : '';
}

async function loadContext() {
    const tanggal = tanggalInput.value;
    setHari(tanggal);
    if (!tanggal) return;
    statusLabel.textContent = 'Loading...';

    const res = await fetch(`ajax/get_painting_prod_context.php?department_id=${DEPARTMENT_ID}&tanggal=${tanggal}`);
    const data = await res.json();

    priorAccum = data.prior_accum || Object.fromEntries(CATS.map((c) => [c, 0]));
    const header = data.header;
    currentHeaderId = header ? header.id : null;

    pekerjaSelect.value = header?.checker_id ?? '';
    shiftSelect.value = header?.shift_id ?? '';

    renderRows(data.lines);
    renderFoot();
    updateTotals();

    statusLabel.textContent = header
        ? (header.status === 'draft' ? 'Editing a saved draft for this date.' : 'This date already has a submitted report — saving will update it.')
        : 'New entry for this date.';
}

tbody.addEventListener('input', (e) => {
    const t = e.target;
    if (!t.matches('.pprod-qty')) return;
    // CB/FOT/FW/PART mirror the same figure, so typing CB auto-fills the other
    // three in that row (they stay editable if a row genuinely needs to differ).
    if (t.dataset.cat === 'cb') {
        const no = t.dataset.no;
        ['fot', 'fw', 'part'].forEach((cat) => {
            const cell = tbody.querySelector(`.pprod-qty[data-cat="${cat}"][data-no="${no}"]`);
            if (cell) cell.value = t.value;
        });
    }
    updateTotals();
});

tanggalInput.addEventListener('change', loadContext);

function buildPayload(status) {
    const lines = [];
    for (let no = 1; no <= rowCount; no++) {
        const line = {
            line_no: no,
            model: tbody.querySelector(`[data-field="model"][data-no="${no}"]`).value.trim(),
            keterangan: tbody.querySelector(`[data-field="keterangan"][data-no="${no}"]`).value.trim(),
        };
        CATS.forEach((c) => {
            line[c] = tbody.querySelector(`.pprod-qty[data-cat="${c}"][data-no="${no}"]`).value;
        });
        lines.push(line);
    }
    return {
        header_id: currentHeaderId,
        status,
        tanggal: tanggalInput.value,
        department_id: DEPARTMENT_ID,
        checker_id: pekerjaSelect.value,
        shift_id: shiftSelect.value,
        lines,
    };
}

// Is there actually anything worth saving? A report only counts as "filled"
// once at least one line has a model, a quantity, or a remark. Just opening the
// form and clicking around (or picking a worker/shift) must never create a
// blank draft — so autosave is skipped until real line data exists.
function hasLineContent(payload) {
    return payload.lines.some((l) =>
        (l.model && l.model !== '') ||
        (l.keterangan && l.keterangan !== '') ||
        CATS.some((c) => String(l[c] ?? '').trim() !== '')
    );
}

async function save(status, silent = false) {
    const payload = buildPayload(status);
    // Never let a background autosave persist an empty form as a draft.
    if (silent && !hasLineContent(payload)) return false;
    if (!silent) statusLabel.textContent = 'Saving...';
    const res = await fetch('ajax/save_painting_prod.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
    });
    const data = await res.json();
    if (data.error) {
        if (silent) return false;
        statusLabel.textContent = '';
        alert('Failed to save: ' + data.error);
        return;
    }
    currentHeaderId = data.header_id;
    if (silent) return true; // autosave: stay on the page, no redirect
    // Record is finalised — stop autosave so the pagehide flush on this redirect
    // can't re-save (and, for Admin, downgrade) it back to a draft.
    if (window.stopAutosaveDraft) stopAutosaveDraft();
    window.location.href = `view_painting_prod_checksheets.php?saved=1`;
}

document.getElementById('btn-add-row').addEventListener('click', addRow);

document.getElementById('btn-draft').addEventListener('click', () => save('draft'));
document.getElementById('btn-submit').addEventListener('click', () => {
    if (window.checksheetComplete && !checksheetComplete()) return;
    if (confirm('Submit this Painting daily report?')) save('submitted');
});
if (window.initAutosaveDraft) initAutosaveDraft({ save: () => save('draft', true) });

loadContext();

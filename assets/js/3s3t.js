const lineInput = document.getElementById('f_line');
const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const operatorSelect = document.getElementById('f_operator');
const tbody = document.getElementById('threes3t-tbody');

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function peopleOptions(selected, list) {
    let html = '<option value="">-</option>';
    for (const p of (list || PEOPLE)) {
        html += `<option value="${p.id}" ${String(p.id) === String(selected ?? '') ? 'selected' : ''}>${escapeHtml(p.name)}</option>`;
    }
    return html;
}

let currentUnlocked = false;

/** Only the current week of the current month is ever editable — unless this
 * record has an Admin-approved edit-request unlock, in which case every
 * week is open. */
function isEditableWeek(weekNum) {
    if (currentUnlocked) return true;
    return Number(monthSelect.value) === CURRENT_MONTH
        && Number(yearSelect.value) === CURRENT_YEAR
        && weekNum === CURRENT_WEEK;
}
function isEditablePeriod() {
    if (currentUnlocked) return true;
    return Number(monthSelect.value) === CURRENT_MONTH && Number(yearSelect.value) === CURRENT_YEAR;
}

/** Short one-line preview of a (possibly long) remark for the grid cell. */
function remarksButton(itemId, itemLabel, remarks) {
    const text = (remarks ?? '').trim();
    const preview = text ? text : '+ Add note';
    const cls = text ? 'has-note' : 'empty';
    const dis = isEditablePeriod() ? '' : 'disabled';
    return `<button type="button" class="t3-remarks-btn ${cls}" data-item-id="${itemId}" data-item-label="${escapeHtml(itemLabel)}" title="${escapeHtml(text)}" ${dis}>${escapeHtml(preview)}</button>`;
}

function weekToggle(itemId, week, value) {
    const blocked = !isEditableWeek(parseInt(week.replace('week', ''), 10));
    return `<div class="cat-toggle t3-toggle ${blocked ? 'cal-blocked' : ''}" data-item-id="${itemId}" data-week="${week}">
        <span class="cat-btn cat-ok ${value === 'OK' ? 'active' : ''}" data-value="OK">OK</span>
        <span class="cat-btn cat-ng ${value === 'NG' ? 'active' : ''}" data-value="NG">NG</span>
    </div>`;
}

/** Each category only appears on the first item of its group in the data
 * (continuation rows have category === null/empty) — compute how many rows
 * that first cell should span so the category merges visually instead of
 * repeating an empty cell per row. */
function categorySpans(items) {
    const spans = new Array(items.length).fill(0);
    let groupStart = 0;
    for (let i = 1; i <= items.length; i++) {
        if (i === items.length || (items[i].category ?? '') !== '') {
            spans[groupStart] = i - groupStart;
            groupStart = i;
        }
    }
    return spans;
}

function renderRows(items, details) {
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="empty">No 3S-3T items set up yet.</td></tr>';
        return;
    }
    const spans = categorySpans(items);
    let html = '';
    let no = 1;
    items.forEach((item, idx) => {
        const d = details[item.id] || {};
        const categoryCell = spans[idx] > 0
            ? `<td class="t3-category-cell" rowspan="${spans[idx]}">${escapeHtml(item.category ?? '')}</td>`
            : '';
        html += `<tr>
            <td>${no++}</td>
            ${categoryCell}
            <td class="t3-item-cell">${escapeHtml(item.item_pemeriksaan)}</td>
            <td class="t3-standard-cell">${escapeHtml(item.standar_kriteria ?? '-')}</td>
            <td>${weekToggle(item.id, 'week1', d.week1)}</td>
            <td>${weekToggle(item.id, 'week2', d.week2)}</td>
            <td>${weekToggle(item.id, 'week3', d.week3)}</td>
            <td>${weekToggle(item.id, 'week4', d.week4)}</td>
            <td>${weekToggle(item.id, 'week5', d.week5)}</td>
            <td>${remarksButton(item.id, item.item_pemeriksaan, d.remarks)}</td>
            <td><select class="t3-pic-select" data-item-id="${item.id}" ${isEditablePeriod() ? '' : 'disabled'}>${peopleOptions(d.pic_id, PIC_PEOPLE)}</select></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

async function loadMonth() {
    const line = lineInput.value.trim();
    tbody.innerHTML = '<tr><td colspan="11" class="empty">Loading data...</td></tr>';

    const res = await fetch(`ajax/get_3s3t_month.php?department_id=${DEPARTMENT_ID}&line=${encodeURIComponent(line)}&month=${monthSelect.value}&year=${yearSelect.value}`);
    const data = await res.json();
    currentUnlocked = !!data.unlocked;

    renderRows(data.items || [], data.details || {});

    const header = data.header;
    operatorSelect.value = header?.operator_id ?? '';
    operatorSelect.disabled = !isEditablePeriod();

    const banner = document.getElementById('unlock-banner');
    if (banner) banner.style.display = currentUnlocked ? '' : 'none';
}

async function saveCell(itemId, field, value) {
    if (!lineInput.value.trim()) {
        alert('Type a Line name first so this checksheet can be saved.');
        loadMonth();
        return;
    }
    await fetch('ajax/save_3s3t.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            line: lineInput.value.trim(),
            month: monthSelect.value,
            year: yearSelect.value,
            item_id: itemId,
            cell_field: field,
            value,
        }),
    });
}

async function saveHeaderField(field, value) {
    if (!lineInput.value.trim()) {
        alert('Type a Line name first so this checksheet can be saved.');
        return;
    }
    await fetch('ajax/save_3s3t.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            line: lineInput.value.trim(),
            month: monthSelect.value,
            year: yearSelect.value,
            field,
            value,
        }),
    });
}

tbody.addEventListener('click', (e) => {
    const remarksBtn = e.target.closest('.t3-remarks-btn');
    if (remarksBtn) {
        if (remarksBtn.disabled) return;
        openRemarksModal(remarksBtn.dataset.itemId, remarksBtn.dataset.itemLabel);
        return;
    }

    const btn = e.target.closest('.cat-btn');
    if (!btn) return;
    const wrapper = btn.closest('.t3-toggle');
    if (wrapper.classList.contains('cal-blocked')) return;
    const itemId = wrapper.dataset.itemId;
    const week = wrapper.dataset.week;
    const value = btn.dataset.value;

    wrapper.querySelectorAll('.cat-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    saveCell(itemId, week, value);
});

tbody.addEventListener('change', (e) => {
    if (e.target.matches('.t3-pic-select')) {
        saveCell(e.target.dataset.itemId, 'pic_id', e.target.value);
    }
});

// ---- Remarks / Tindakan Perbaikan modal (roomier input + past-months history) ----
const remarksModal = document.getElementById('t3-remarks-modal');
const remarksModalTitle = document.getElementById('t3-remarks-modal-title');
const remarksTextarea = document.getElementById('t3-remarks-textarea');
const remarksHistoryList = document.getElementById('t3-remarks-history-list');
let remarksModalItemId = null;

function closeRemarksModal() {
    remarksModal.style.display = 'none';
    remarksModalItemId = null;
}

async function openRemarksModal(itemId, itemLabel) {
    remarksModalItemId = itemId;
    remarksModalTitle.textContent = itemLabel || 'Keterangan / Tindakan Perbaikan';
    const btn = tbody.querySelector(`.t3-remarks-btn[data-item-id="${itemId}"]`);
    remarksTextarea.value = btn?.classList.contains('has-note') ? (btn.getAttribute('title') || '') : '';
    remarksModal.style.display = 'flex';
    remarksTextarea.focus();

    remarksHistoryList.innerHTML = 'Loading...';
    const line = lineInput.value.trim();
    const res = await fetch(`ajax/get_3s3t_remarks_history.php?department_id=${DEPARTMENT_ID}&line=${encodeURIComponent(line)}&item_id=${itemId}`);
    const data = await res.json();
    const history = data.history || [];
    const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    remarksHistoryList.innerHTML = history.length
        ? history.map(h => `<div class="t3-remarks-history-row">
              <div class="t3-remarks-history-period">${monthNames[h.month]} ${h.year}</div>
              <div class="t3-remarks-history-text">${escapeHtml(h.remarks)}</div>
          </div>`).join('')
        : '<div class="t3-remarks-history-empty">No previous notes for this item yet.</div>';
}

document.getElementById('t3-remarks-modal-close').addEventListener('click', (e) => { e.preventDefault(); closeRemarksModal(); });
document.getElementById('t3-remarks-cancel').addEventListener('click', () => closeRemarksModal());
remarksModal.addEventListener('click', (e) => { if (e.target === remarksModal) closeRemarksModal(); });

document.getElementById('t3-remarks-save').addEventListener('click', async () => {
    if (!remarksModalItemId) return;
    const value = remarksTextarea.value.trim();
    await saveCell(remarksModalItemId, 'remarks', value);
    const btn = tbody.querySelector(`.t3-remarks-btn[data-item-id="${remarksModalItemId}"]`);
    if (btn) {
        btn.textContent = value || '+ Add note';
        btn.title = value;
        btn.classList.toggle('has-note', !!value);
        btn.classList.toggle('empty', !value);
    }
    closeRemarksModal();
});

lineInput.addEventListener('change', loadMonth);
monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

operatorSelect.addEventListener('change', () => saveHeaderField('operator_id', operatorSelect.value));

loadMonth();

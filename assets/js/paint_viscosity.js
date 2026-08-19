const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const foremanSelect = document.getElementById('f_foreman');
const supervisorSelect = document.getElementById('f_supervisor');
const notesEl = document.getElementById('f_notes');
const tableHeadRow = document.getElementById('viscosity-table-head');
const tbody = document.getElementById('viscosity-tbody');

const FIXED_HEAD_HTML = `
    <th class="visc-process-col">Process Name</th>
    <th class="visc-product-col">Product Name</th>
    <th class="visc-maker-col">Maker/Brand</th>
    <th class="visc-standard-col">Viscosity Standard</th>
`;

let currentItems = [];

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function daysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
}

function toNum(v) {
    const n = parseFloat(String(v ?? '').trim().replace(',', '.'));
    return isNaN(n) ? null : n;
}

function verdictClass(value, min, max) {
    if (value === '' || value === null || value === undefined) return '';
    const v = toNum(value);
    if (v === null || min === null || max === null) return '';
    return (v < min || v > max) ? 'temp-ng' : 'temp-ok';
}

function standardLabel(item) {
    const min = item.standard_min ?? '';
    const max = item.standard_max ?? '';
    const unit = item.standard_unit ?? '';
    if (min === '' && max === '') return '-';
    return `${min} ~ ${max} ${unit}`.trim();
}

function renderHead(days, month, year, holidays) {
    let html = FIXED_HEAD_HTML;
    for (let d = 1; d <= days; d++) {
        const { cls, title } = getDayInfo(d, month, year, holidays, TODAY);
        html += `<th class="${cls}" ${title ? `title="${escapeHtml(title)}"` : ''}>${d}</th>`;
    }
    tableHeadRow.innerHTML = html;
}

function renderRows(items, details, day1Total, month, year, holidays) {
    if (!items.length) {
        tbody.innerHTML = '<tr><td class="empty">No viscosity items set up yet.</td></tr>';
        return;
    }
    const blockedDays = new Set();
    for (let day = 1; day <= day1Total; day++) {
        if (getDayInfo(day, month, year, holidays, TODAY).blocked) blockedDays.add(day);
    }

    let html = '';
    for (const item of items) {
        const min = toNum(item.standard_min);
        const max = toNum(item.standard_max);
        html += `<tr>
            <td class="visc-process-cell">${escapeHtml(item.process_name ?? '')}</td>
            <td class="visc-product-cell">${escapeHtml(item.product_name)}</td>
            <td>${escapeHtml(item.maker_brand ?? '-')}</td>
            <td class="visc-standard-cell">${escapeHtml(standardLabel(item))}</td>`;
        for (let day = 1; day <= day1Total; day++) {
            const value = details[`${item.id}_${day}`] ?? '';
            const cls = verdictClass(value, min, max);
            const dis = blockedDays.has(day) ? 'disabled' : '';
            html += `<td><input type="text" inputmode="decimal" class="temp-input ${cls}" data-item-id="${item.id}" data-day="${day}" value="${escapeHtml(value)}" ${dis}></td>`;
        }
        html += '</tr>';
    }

    tbody.innerHTML = html;
}

async function loadMonth() {
    const month = monthSelect.value;
    const year = yearSelect.value;

    tbody.innerHTML = '<tr><td class="empty">Loading data...</td></tr>';

    const [res, holidays] = await Promise.all([
        fetch(`ajax/get_paint_viscosity_month.php?department_id=${DEPARTMENT_ID}&month=${month}&year=${year}`),
        fetchHolidays(year),
    ]);
    const data = await res.json();
    currentItems = data.items || [];

    const days = daysInMonth(Number(month), Number(year));
    renderHead(days, Number(month), Number(year), holidays);
    renderRows(currentItems, data.details || {}, days, Number(month), Number(year), holidays);

    const header = data.header;
    foremanSelect.value = header?.foreman_id ?? '';
    supervisorSelect.value = header?.supervisor_id ?? '';
    notesEl.value = header?.notes ?? '';
}

async function saveResult(itemId, day, value) {
    await fetch('ajax/save_paint_viscosity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            month: monthSelect.value,
            year: yearSelect.value,
            item_id: itemId,
            day,
            actual_result: value,
        }),
    });
}

async function saveHeaderField(field, value) {
    await fetch('ajax/save_paint_viscosity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            month: monthSelect.value,
            year: yearSelect.value,
            field,
            value,
        }),
    });
}

function applyVerdict(el) {
    const item = currentItems.find(i => String(i.id) === String(el.dataset.itemId));
    const min = item ? toNum(item.standard_min) : null;
    const max = item ? toNum(item.standard_max) : null;
    el.classList.remove('temp-ok', 'temp-ng');
    const cls = verdictClass(el.value, min, max);
    if (cls) el.classList.add(cls);
}

tbody.addEventListener('input', (e) => {
    if (!e.target.matches('.temp-input')) return;
    applyVerdict(e.target);
});

tbody.addEventListener('change', (e) => {
    if (!e.target.matches('.temp-input')) return;
    applyVerdict(e.target);
    saveResult(e.target.dataset.itemId, e.target.dataset.day, e.target.value.trim());
});

monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

foremanSelect.addEventListener('change', () => saveHeaderField('foreman_id', foremanSelect.value));
supervisorSelect.addEventListener('change', () => saveHeaderField('supervisor_id', supervisorSelect.value));
notesEl.addEventListener('change', () => saveHeaderField('notes', notesEl.value));

loadMonth();

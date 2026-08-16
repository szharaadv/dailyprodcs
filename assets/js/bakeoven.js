const ovenSelect = document.getElementById('f_oven');
const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const standardEl = document.getElementById('f_standard');
const asstForemanSelect = document.getElementById('f_asst_foreman');
const foremanSelect = document.getElementById('f_foreman');
const supervisorSelect = document.getElementById('f_supervisor');
const notesEl = document.getElementById('f_notes');
const tableHead = document.getElementById('bakeoven-table-head');
const tbody = document.getElementById('bakeoven-tbody');

let currentTimes = [];

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

function renderHead(days) {
    let html = '<th class="bo-corner-cell"><span class="bo-corner-text">Waktu<br>Pengecekan</span></th>';
    for (let d = 1; d <= days; d++) html += `<th>${d}</th>`;
    tableHead.innerHTML = html;
}

function renderRows(times, details, paraf, day1Total, min, max) {
    if (!times.length) {
        tbody.innerHTML = '<tr><td class="empty">No checking times set up for this oven yet.</td></tr>';
        return;
    }
    let html = '';
    for (const t of times) {
        html += `<tr><td class="row-label">${escapeHtml(t.time_label)}</td>`;
        for (let day = 1; day <= day1Total; day++) {
            const value = details[`${t.id}_${day}`] ?? '';
            const cls = verdictClass(value, min, max);
            html += `<td><input type="text" inputmode="decimal" class="temp-input ${cls}" data-time-id="${t.id}" data-day="${day}" value="${escapeHtml(value)}"></td>`;
        }
        html += '</tr>';
    }

    html += '<tr><td class="row-label">PARAF</td>';
    for (let day = 1; day <= day1Total; day++) {
        const selectedUser = paraf[day] ?? '';
        html += `<td><select class="paraf-select" data-day="${day}"><option value="">-</option>`;
        for (const p of PEOPLE) {
            html += `<option value="${p.id}" ${String(p.id) === String(selectedUser) ? 'selected' : ''}>${escapeHtml(p.name.split(' ')[0])}</option>`;
        }
        html += '</select></td>';
    }
    html += '</tr>';

    tbody.innerHTML = html;
}

async function loadMonth() {
    const ovenId = ovenSelect.value;
    const month = monthSelect.value;
    const year = yearSelect.value;

    tbody.innerHTML = '<tr><td class="empty">Loading data...</td></tr>';
    if (!ovenId) {
        tableHead.innerHTML = '<th class="bo-corner-cell"><span class="bo-corner-text">Waktu<br>Pengecekan</span></th>';
        tbody.innerHTML = '<tr><td class="empty">No ovens set up for this department yet.</td></tr>';
        currentTimes = [];
        return;
    }

    const oven = STANDARDS[ovenId];
    standardEl.textContent = oven ? `${oven.standard_min}°C ~ ${oven.standard_max}°C` : '-';
    const min = oven ? toNum(oven.standard_min) : null;
    const max = oven ? toNum(oven.standard_max) : null;

    const res = await fetch(`ajax/get_bakeoven_month.php?bakeoven_id=${ovenId}&month=${month}&year=${year}`);
    const data = await res.json();
    currentTimes = data.times || [];

    const days = daysInMonth(Number(month), Number(year));
    renderHead(days);
    renderRows(currentTimes, data.details || {}, data.paraf || {}, days, min, max);

    const header = data.header;
    asstForemanSelect.value = header?.asst_foreman_id ?? '';
    foremanSelect.value = header?.foreman_id ?? '';
    supervisorSelect.value = header?.supervisor_id ?? '';
    notesEl.value = header?.notes ?? '';
}

async function saveTemp(timeId, day, value) {
    await fetch('ajax/save_bakeoven.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bakeoven_id: ovenSelect.value,
            month: monthSelect.value,
            year: yearSelect.value,
            time_id: timeId,
            day,
            actual_temp: value,
        }),
    });
}

async function saveParaf(day, userId) {
    await fetch('ajax/save_bakeoven.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bakeoven_id: ovenSelect.value,
            month: monthSelect.value,
            year: yearSelect.value,
            paraf_day: day,
            user_id: userId,
        }),
    });
}

async function saveHeaderField(field, value) {
    await fetch('ajax/save_bakeoven.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bakeoven_id: ovenSelect.value,
            month: monthSelect.value,
            year: yearSelect.value,
            field,
            value,
        }),
    });
}

function applyVerdict(el) {
    const oven = STANDARDS[ovenSelect.value];
    const min = oven ? toNum(oven.standard_min) : null;
    const max = oven ? toNum(oven.standard_max) : null;
    el.classList.remove('temp-ok', 'temp-ng');
    const cls = verdictClass(el.value, min, max);
    if (cls) el.classList.add(cls);
}

// Save as soon as 3 digits are typed — no need to click away first.
tbody.addEventListener('input', (e) => {
    if (!e.target.matches('.temp-input')) return;
    applyVerdict(e.target);
    const digits = e.target.value.trim().replace(/[^0-9]/g, '');
    if (digits.length >= 3) {
        saveTemp(e.target.dataset.timeId, e.target.dataset.day, e.target.value.trim());
    }
});

// Still save on blur/change — covers shorter values and clearing the field.
tbody.addEventListener('change', (e) => {
    if (e.target.matches('.temp-input')) {
        applyVerdict(e.target);
        saveTemp(e.target.dataset.timeId, e.target.dataset.day, e.target.value.trim());
    } else if (e.target.matches('.paraf-select')) {
        saveParaf(e.target.dataset.day, e.target.value);
    }
});

ovenSelect.addEventListener('change', loadMonth);
monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);

asstForemanSelect.addEventListener('change', () => saveHeaderField('asst_foreman_id', asstForemanSelect.value));
foremanSelect.addEventListener('change', () => saveHeaderField('foreman_id', foremanSelect.value));
supervisorSelect.addEventListener('change', () => saveHeaderField('supervisor_id', supervisorSelect.value));
notesEl.addEventListener('change', () => saveHeaderField('notes', notesEl.value));

loadMonth();

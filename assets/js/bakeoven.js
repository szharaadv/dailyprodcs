const ovenSelect = document.getElementById('f_oven');
const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const standardEl = document.getElementById('f_standard');
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

function renderHead(days, month, year, holidays) {
    let html = '<th class="bo-corner-cell"><span class="bo-corner-text">Waktu<br>Pengecekan</span></th>';
    for (let d = 1; d <= days; d++) {
        const { cls, title } = getDayInfo(d, month, year, holidays, null);
        html += `<th class="${cls}" ${title ? `title="${escapeHtml(title)}"` : ''}>${d}</th>`;
    }
    tableHead.innerHTML = html;
}

function renderRows(times, details, paraf, day1Total, min, max, month, year, holidays, unlocked) {
    if (!times.length) {
        tbody.innerHTML = '<tr><td class="empty">No checking times set up for this oven yet.</td></tr>';
        return;
    }
    // Two kinds of empty cell: an "off" day (holiday/weekend — no check expected)
    // and a "missed" day (a past working day left blank). Tag each so they can be
    // told apart at a glance instead of both just looking grey.
    const holidayBlockedDays = new Set();
    const pastWorkday = new Set();
    for (let day = 1; day <= day1Total; day++) {
        if (getDayInfo(day, month, year, holidays, null).blocked) { holidayBlockedDays.add(day); continue; }
        const ds = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        if (TODAY && ds < TODAY) pastWorkday.add(day);
    }

    let html = '';
    for (const t of times) {
        html += `<tr><td class="row-label">${escapeHtml(t.time_label)}</td>`;
        for (let day = 1; day <= day1Total; day++) {
            const value = details[`${t.id}_${day}`] ?? '';
            const cls = verdictClass(value, min, max);
            const writable = !holidayBlockedDays.has(day) && isCellWritable(value === "", day, month, year, TODAY, unlocked);
            const dis = writable ? '' : 'disabled';
            const tdCls = holidayBlockedDays.has(day) ? 'cal-off' : (value === '' && pastWorkday.has(day) ? 'cal-missed' : '');
            html += `<td class="${tdCls}"><input type="text" inputmode="decimal" class="temp-input ${cls}" data-time-id="${t.id}" data-day="${day}" value="${escapeHtml(value)}" ${dis}></td>`;
        }
        html += '</tr>';
    }

    // A real logged-in user (Operator/Foreman/…) stamps their own account with
    // one click. Admin has no personal account, so falls back to a picker.
    const canStamp = !!CURRENT_USER.id;
    html += '<tr><td class="row-label">PARAF</td>';
    for (let day = 1; day <= day1Total; day++) {
        const pf = paraf[day];
        const filled = !!(pf && pf.id);
        const firstName = filled ? (String(pf.name ?? '').split(' ')[0] || '—') : '';
        const writable = !holidayBlockedDays.has(day) && isCellWritable(!filled, day, month, year, TODAY, unlocked);
        const tdCls = holidayBlockedDays.has(day) ? 'cal-off' : (!filled && pastWorkday.has(day) ? 'cal-missed' : '');
        if (!writable) {
            html += `<td class="${tdCls}"><span class="paraf-cell paraf-ro${filled ? ' filled' : ''}">${filled ? escapeHtml(firstName) : '–'}</span></td>`;
        } else if (canStamp) {
            const inner = filled ? escapeHtml(firstName) : '<span class="paraf-plus">+</span>';
            html += `<td class="${tdCls}"><button type="button" class="paraf-cell${filled ? ' filled' : ''}" data-day="${day}" data-filled="${filled ? '1' : '0'}">${inner}</button></td>`;
        } else {
            const selId = filled ? String(pf.id) : '';
            let opts = '<option value="">-</option>';
            for (const p of PEOPLE) {
                opts += `<option value="${p.id}" ${String(p.id) === selId ? 'selected' : ''}>${escapeHtml(String(p.name).split(' ')[0])}</option>`;
            }
            html += `<td class="${tdCls}"><select class="paraf-select" data-day="${day}">${opts}</select></td>`;
        }
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

    const [res, holidays] = await Promise.all([
        fetch(`ajax/get_bakeoven_month.php?bakeoven_id=${ovenId}&month=${month}&year=${year}`),
        fetchHolidays(year),
    ]);
    const data = await res.json();
    currentTimes = data.times || [];
    const unlocked = !!data.unlocked;

    const days = daysInMonth(Number(month), Number(year));
    renderHead(days, Number(month), Number(year), holidays);
    renderRows(currentTimes, data.details || {}, data.paraf || {}, days, min, max, Number(month), Number(year), holidays, unlocked);

    const header = data.header;
    foremanSelect.value = header?.foreman_id ?? '';
    supervisorSelect.value = header?.supervisor_id ?? '';
    notesEl.value = header?.notes ?? '';

    const banner = document.getElementById('unlock-banner');
    if (banner) banner.style.display = unlocked ? '' : 'none';
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

// Paraf is stamped with the logged-in account: click an empty cell to sign it
// with your own name, click a signed cell to clear it. Only writable cells
// (today, or an approved edit unlock) render as buttons.
tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('button.paraf-cell');
    if (!btn) return;
    const day = btn.dataset.day;
    if (btn.dataset.filled === '1') {
        saveParaf(day, '');
        btn.dataset.filled = '0';
        btn.classList.remove('filled');
        btn.innerHTML = '<span class="paraf-plus">+</span>';
    } else {
        if (!CURRENT_USER.id) return;
        saveParaf(day, CURRENT_USER.id);
        btn.dataset.filled = '1';
        btn.classList.add('filled');
        btn.textContent = String(CURRENT_USER.name || '').split(' ')[0] || '—';
    }
});

ovenSelect.addEventListener('change', loadMonth);
monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

foremanSelect.addEventListener('change', () => saveHeaderField('foreman_id', foremanSelect.value));
supervisorSelect.addEventListener('change', () => saveHeaderField('supervisor_id', supervisorSelect.value));
notesEl.addEventListener('change', () => saveHeaderField('notes', notesEl.value));

loadMonth();

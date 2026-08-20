const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const tbody = document.getElementById('washing-tbody');

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function daysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
}

function peopleOptions(selected) {
    let html = '<option value="">-</option>';
    for (const p of PEOPLE) {
        html += `<option value="${p.id}" ${String(p.id) === String(selected ?? '') ? 'selected' : ''}>${escapeHtml(p.name)}</option>`;
    }
    return html;
}

function renderRows(days, rows, month, year, holidays, unlocked) {
    let html = '';
    for (let day = 1; day <= days; day++) {
        const r = rows[day] || {};
        const { cls, title, blocked: holidayBlocked } = getDayInfo(day, month, year, holidays, null);
        const w = (val) => !holidayBlocked && isCellWritable(!val, day, month, year, TODAY, unlocked);
        const dis = (val) => w(val) ? '' : 'disabled';
        const rowCls = holidayBlocked ? 'washing-row-blocked' : '';
        html += `<tr class="${rowCls}">
            <td class="washing-date-col ${cls}" ${title ? `title="${escapeHtml(title)}"` : ''}>${day}</td>
            <td><input type="text" class="washing-input" data-day="${day}" data-field="ganti_air" value="${escapeHtml(r.ganti_air)}" ${dis(r.ganti_air)}></td>
            <td><input type="text" class="washing-input" data-day="${day}" data-field="temperatur_air" value="${escapeHtml(r.temperatur_air)}" ${dis(r.temperatur_air)}></td>
            <td><input type="text" class="washing-input" data-day="${day}" data-field="penambahan_gildaon" value="${escapeHtml(r.penambahan_gildaon)}" ${dis(r.penambahan_gildaon)}></td>
            <td><input type="text" class="washing-input" data-day="${day}" data-field="total_acid" value="${escapeHtml(r.total_acid)}" ${dis(r.total_acid)}></td>
            <td><select class="washing-select" data-day="${day}" data-field="checker_id" ${dis(r.checker_id)}>${peopleOptions(r.checker_id)}</select></td>
            <td><select class="washing-select" data-day="${day}" data-field="control_id" ${dis(r.control_id)}>${peopleOptions(r.control_id)}</select></td>
        </tr>`;
    }
    tbody.innerHTML = html;
}

async function loadMonth() {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">Loading data...</td></tr>';
    const month = monthSelect.value;
    const year = yearSelect.value;

    const [dataRes, holidays] = await Promise.all([
        fetch(`ajax/get_washing_month.php?department_id=${DEPARTMENT_ID}&month=${month}&year=${year}`),
        fetchHolidays(year),
    ]);
    const data = await dataRes.json();
    const unlocked = !!data.unlocked;

    const days = daysInMonth(Number(month), Number(year));
    renderRows(days, data.rows || {}, Number(month), Number(year), holidays || {}, unlocked);

    const banner = document.getElementById('unlock-banner');
    if (banner) banner.style.display = unlocked ? '' : 'none';
}

async function saveCell(day, field, value) {
    await fetch('ajax/save_washing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            department_id: DEPARTMENT_ID,
            month: monthSelect.value,
            year: yearSelect.value,
            day,
            field,
            value,
        }),
    });
}

tbody.addEventListener('change', (e) => {
    if (!e.target.matches('.washing-input, .washing-select')) return;
    saveCell(e.target.dataset.day, e.target.dataset.field, e.target.value.trim());
});

monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

loadMonth();

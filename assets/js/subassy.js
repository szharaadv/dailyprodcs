const jigSelect = document.getElementById('f_jig');
const monthSelect = document.getElementById('f_month');
const yearSelect = document.getElementById('f_year');
const supervisorSelect = document.getElementById('f_supervisor');
const foremanSelect = document.getElementById('f_foreman');
const checkerSelect = document.getElementById('f_checker');
const tableHead = document.getElementById('jig-table-head');
const tbody = document.getElementById('jig-tbody');

let currentItems = [];

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function daysInMonth(month, year) {
    return new Date(year, month, 0).getDate();
}

function renderHead(items) {
    tableHead.innerHTML = '<th>Day</th>' + items.map((it) => `<th>${escapeHtml(it.checking_item)}</th>`).join('');
}

function renderRows(items, details, month, year, holidays) {
    const total = daysInMonth(month, year);
    if (!items.length) {
        tbody.innerHTML = '<tr><td class="empty">No checking items set up for this jig yet.</td></tr>';
        return;
    }
    let html = '';
    for (let day = 1; day <= total; day++) {
        const { cls, title, blocked } = getDayInfo(day, month, year, holidays);
        html += `<tr><td class="jig-day ${cls}" ${title ? `title="${escapeHtml(title)}"` : ''}>${day}</td>`;
        for (const item of items) {
            const value = details[`${item.id}_${day}`] ?? '';
            html += `<td>
                <div class="cat-toggle jig-toggle ${blocked ? 'cal-blocked' : ''}" data-item-id="${item.id}" data-day="${day}">
                    <span class="cat-btn cat-ok ${value === 'OK' ? 'active' : ''}" data-value="OK">OK</span>
                    <span class="cat-btn cat-ng ${value === 'NG' ? 'active' : ''}" data-value="NG">NG</span>
                </div>
            </td>`;
        }
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

async function loadMonth() {
    const jigId = jigSelect.value;
    const month = monthSelect.value;
    const year = yearSelect.value;

    tbody.innerHTML = '<tr><td class="empty">Loading data...</td></tr>';
    if (!jigId) {
        tableHead.innerHTML = '<th>Day</th>';
        tbody.innerHTML = '<tr><td class="empty">No jigs set up for this department yet.</td></tr>';
        currentItems = [];
        return;
    }

    const [res, holidays] = await Promise.all([
        fetch(`ajax/get_jig_month.php?jig_id=${jigId}&month=${month}&year=${year}`),
        fetchHolidays(year),
    ]);
    const data = await res.json();
    currentItems = data.items || [];

    renderHead(currentItems);
    renderRows(currentItems, data.details || {}, Number(month), Number(year), holidays);

    const header = data.header;
    supervisorSelect.value = header?.supervisor_id ?? '';
    foremanSelect.value = header?.foreman_id ?? '';
    checkerSelect.value = header?.checker_id ?? '';
}

async function saveCell(jigItemId, day, result) {
    await fetch('ajax/save_jig.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            jig_id: jigSelect.value,
            month: monthSelect.value,
            year: yearSelect.value,
            jig_item_id: jigItemId,
            day,
            result,
        }),
    });
}

async function saveHeaderField(field, value) {
    await fetch('ajax/save_jig.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            jig_id: jigSelect.value,
            month: monthSelect.value,
            year: yearSelect.value,
            field,
            value,
        }),
    });
}

tbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.cat-btn');
    if (!btn) return;
    const wrapper = btn.closest('.jig-toggle');
    const itemId = wrapper.dataset.itemId;
    const day = wrapper.dataset.day;
    const value = btn.dataset.value;

    wrapper.querySelectorAll('.cat-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    saveCell(itemId, day, value);
});

jigSelect.addEventListener('change', loadMonth);
monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

supervisorSelect.addEventListener('change', () => saveHeaderField('supervisor_id', supervisorSelect.value));
foremanSelect.addEventListener('change', () => saveHeaderField('foreman_id', foremanSelect.value));
checkerSelect.addEventListener('change', () => saveHeaderField('checker_id', checkerSelect.value));

loadMonth();

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

function weekToggle(itemId, week, value) {
    return `<div class="cat-toggle t3-toggle" data-item-id="${itemId}" data-week="${week}">
        <span class="cat-btn cat-ok ${value === 'OK' ? 'active' : ''}" data-value="OK">OK</span>
        <span class="cat-btn cat-ng ${value === 'NG' ? 'active' : ''}" data-value="NG">NG</span>
    </div>`;
}

function renderRows(items, details) {
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="empty">No 3S-3T items set up yet.</td></tr>';
        return;
    }
    let html = '';
    let no = 1;
    for (const item of items) {
        const d = details[item.id] || {};
        html += `<tr>
            <td>${no++}</td>
            <td class="t3-category-cell">${escapeHtml(item.category ?? '')}</td>
            <td class="t3-item-cell">${escapeHtml(item.item_pemeriksaan)}</td>
            <td class="t3-standard-cell">${escapeHtml(item.standar_kriteria ?? '-')}</td>
            <td>${weekToggle(item.id, 'week1', d.week1)}</td>
            <td>${weekToggle(item.id, 'week2', d.week2)}</td>
            <td>${weekToggle(item.id, 'week3', d.week3)}</td>
            <td>${weekToggle(item.id, 'week4', d.week4)}</td>
            <td>${weekToggle(item.id, 'week5', d.week5)}</td>
            <td><input type="text" class="t3-remarks-input" data-item-id="${item.id}" value="${escapeHtml(d.remarks ?? '')}"></td>
            <td><select class="t3-pic-select" data-item-id="${item.id}">${peopleOptions(d.pic_id, PIC_PEOPLE)}</select></td>
        </tr>`;
    }
    tbody.innerHTML = html;
}

async function loadMonth() {
    const line = lineInput.value.trim();
    tbody.innerHTML = '<tr><td colspan="11" class="empty">Loading data...</td></tr>';

    const res = await fetch(`ajax/get_3s3t_month.php?department_id=${DEPARTMENT_ID}&line=${encodeURIComponent(line)}&month=${monthSelect.value}&year=${yearSelect.value}`);
    const data = await res.json();

    renderRows(data.items || [], data.details || {});

    const header = data.header;
    operatorSelect.value = header?.operator_id ?? '';
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
    const btn = e.target.closest('.cat-btn');
    if (!btn) return;
    const wrapper = btn.closest('.t3-toggle');
    const itemId = wrapper.dataset.itemId;
    const week = wrapper.dataset.week;
    const value = btn.dataset.value;

    wrapper.querySelectorAll('.cat-btn').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    saveCell(itemId, week, value);
});

tbody.addEventListener('change', (e) => {
    if (e.target.matches('.t3-remarks-input')) {
        saveCell(e.target.dataset.itemId, 'remarks', e.target.value.trim());
    } else if (e.target.matches('.t3-pic-select')) {
        saveCell(e.target.dataset.itemId, 'pic_id', e.target.value);
    }
});

lineInput.addEventListener('change', loadMonth);
monthSelect.addEventListener('change', loadMonth);
yearSelect.addEventListener('change', loadMonth);
wireMonthNav('btn-prev-month', 'btn-next-month', monthSelect, yearSelect);

operatorSelect.addEventListener('change', () => saveHeaderField('operator_id', operatorSelect.value));

loadMonth();

/**
 * "Keterangan tidak mengisi" — excuse a missing checksheet day with an
 * admin-managed reason (Preventive Maintenance, Stock Taking, …) instead of
 * filling it. Opened from a .cs-offday-btn in the missing-check banner.
 * Data attributes on the button:
 *   data-type, data-department-id, data-condition-id (optional),
 *   data-dates (comma-separated YYYY-MM-DD), data-label
 */
(function () {
    let modal = null;
    let reasonsLoaded = false;

    function dmy(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
    }

    function build() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'modal-overlay offday-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal-card">
                <div class="modal-card-header">
                    <h3>Keterangan Tidak Mengisi</h3>
                    <a href="#" class="modal-close" id="od-close">&times;</a>
                </div>
                <div class="modal-body offday-form">
                    <p id="od-record-label" class="offday-record-label"></p>
                    <label for="od-date">Tanggal</label>
                    <select id="od-date"></select>
                    <label for="od-reason">Alasan</label>
                    <select id="od-reason"></select>
                    <label for="od-user">Diisi oleh</label>
                    <select id="od-user"></select>
                    <label for="od-note">Catatan (opsional)</label>
                    <textarea id="od-note" rows="2" placeholder="Opsional"></textarea>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="od-cancel">Batal</button>
                    <button class="btn" id="od-submit">Simpan Keterangan</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
        modal.querySelector('#od-close').addEventListener('click', (e) => { e.preventDefault(); close(); });
        modal.querySelector('#od-cancel').addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        modal.querySelector('#od-submit').addEventListener('click', submit);
        return modal;
    }

    function close() { if (modal) modal.style.display = 'none'; }

    async function fillSelect(sel, url, key, labelKey, placeholder) {
        try {
            const res = await fetch(url);
            const data = await res.json();
            const rows = data[key] || [];
            sel.innerHTML = `<option value="">${placeholder}</option>` +
                rows.map(r => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('');
        } catch (e) {
            sel.innerHTML = `<option value="">(gagal memuat)</option>`;
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function open(btn) {
        build();
        modal.dataset.type = btn.dataset.type || '';
        modal.dataset.departmentId = btn.dataset.departmentId || '';
        modal.dataset.conditionId = btn.dataset.conditionId || '';
        modal.querySelector('#od-record-label').textContent = btn.dataset.label || '';

        const dates = (btn.dataset.dates || '').split(',').map(s => s.trim()).filter(Boolean);
        modal.querySelector('#od-date').innerHTML = dates.map(d => `<option value="${d}">${dmy(d)}</option>`).join('');

        modal.querySelector('#od-note').value = '';
        modal.style.display = 'flex';

        // Load reasons + users (once per open is fine; reasons cached).
        await Promise.all([
            fillSelect(modal.querySelector('#od-reason'), 'ajax/get_offday_reasons.php', 'reasons', 'name', 'Pilih alasan…'),
            fillSelect(modal.querySelector('#od-user'), 'ajax/get_active_users.php', 'users', 'name', 'Pilih nama…'),
        ]);
    }

    async function submit() {
        const reason = modal.querySelector('#od-reason').value;
        const date = modal.querySelector('#od-date').value;
        const user = modal.querySelector('#od-user').value;
        if (!date || !reason || !user) {
            alert('Pilih tanggal, alasan, dan nama dulu.');
            return;
        }
        const btn = modal.querySelector('#od-submit');
        btn.disabled = true;
        try {
            const res = await fetch('ajax/save_offday.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    checksheet_type: modal.dataset.type,
                    department_id: modal.dataset.departmentId,
                    condition_id: modal.dataset.conditionId,
                    tanggal: date,
                    reason_id: reason,
                    note: modal.querySelector('#od-note').value,
                    created_by: user,
                }),
            });
            const data = await res.json();
            if (!data.success) { alert('Gagal: ' + (data.error || 'unknown')); btn.disabled = false; return; }
            location.reload();
        } catch (e) {
            alert('Gagal menyimpan keterangan.');
            btn.disabled = false;
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.cs-offday-btn');
        if (!btn) return;
        e.preventDefault();
        open(btn);
    });
})();

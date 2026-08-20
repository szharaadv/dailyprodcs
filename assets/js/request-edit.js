(function () {
    let modal = null;
    let usersLoaded = false;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    function ensureModal() {
        if (modal) return modal;
        modal = document.createElement('div');
        modal.className = 'modal-overlay request-edit-modal';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal-card">
                <div class="modal-card-header">
                    <h3>Request Edit</h3>
                    <a href="#" class="modal-close" id="re-close">&times;</a>
                </div>
                <p class="re-record-label" id="re-record-label"></p>
                <div class="form-row">
                    <label>Nama kamu</label>
                    <select id="re-requester"><option value="">Loading...</option></select>
                </div>
                <div class="form-row">
                    <label>Alasan / apa yang perlu diperbaiki</label>
                    <textarea id="re-reason" rows="3" placeholder="Contoh: salah input nilai di item Water pump pressure"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="re-cancel">Cancel</button>
                    <button type="button" class="btn" id="re-submit">Send Request</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.querySelector('#re-close').addEventListener('click', (e) => { e.preventDefault(); closeModal(); });
        modal.querySelector('#re-cancel').addEventListener('click', () => closeModal());
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        modal.querySelector('#re-submit').addEventListener('click', submitRequest);

        return modal;
    }

    async function loadUsersOnce() {
        if (usersLoaded) return;
        const select = modal.querySelector('#re-requester');
        const res = await fetch('ajax/get_active_users.php');
        const data = await res.json();
        select.innerHTML = '<option value="">-- select --</option>' + (data.users || [])
            .map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');
        usersLoaded = true;
    }

    // Already know who's asking (each User signs in with their own PIN now
    // — see login.php) — fill it in and lock it so a request can't
    // accidentally go out under someone else's name. Admin's session has no
    // tied m_user id, so it still falls back to the manual picker.
    function applyKnownIdentity() {
        const select = modal.querySelector('#re-requester');
        if (typeof LOGGED_IN_USER_ID !== 'undefined' && LOGGED_IN_USER_ID) {
            select.value = String(LOGGED_IN_USER_ID);
            select.disabled = true;
        } else {
            select.disabled = false;
        }
    }

    let currentType = null;
    let currentId = null;

    function closeModal() {
        modal.style.display = 'none';
        currentType = null;
        currentId = null;
    }

    async function openModal(type, id, label) {
        ensureModal();
        currentType = type;
        currentId = id;
        modal.querySelector('#re-record-label').textContent = label || '';
        modal.querySelector('#re-reason').value = '';
        modal.querySelector('#re-requester').value = '';
        modal.style.display = 'flex';
        await loadUsersOnce();
        applyKnownIdentity();
    }

    async function submitRequest() {
        const requester = modal.querySelector('#re-requester').value;
        const reason = modal.querySelector('#re-reason').value.trim();
        if (!requester || !reason) {
            alert('Pilih nama kamu dan isi alasannya dulu.');
            return;
        }
        const res = await fetch('ajax/request_edit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                checksheet_type: currentType,
                header_id: currentId,
                label: modal.querySelector('#re-record-label').textContent,
                requested_by: requester,
                reason,
            }),
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Gagal mengirim request.');
            return;
        }
        alert('Request edit terkirim. Menunggu persetujuan Admin.');
        closeModal();
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.cs-request-edit-btn');
        if (!btn) return;
        openModal(btn.dataset.editType, btn.dataset.editId, btn.dataset.editLabel);
    });
})();

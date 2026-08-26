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

    // 'edit' → reopen an existing record (currentId = header_id).
    // 'fill' → create a record for a missed past day (currentFill set).
    let currentMode = 'edit';
    let currentType = null;
    let currentId = null;
    let currentFill = null;

    function closeModal() {
        modal.style.display = 'none';
        currentMode = 'edit';
        currentType = null;
        currentId = null;
        currentFill = null;
    }

    async function openModalCommon(title, label) {
        ensureModal();
        modal.querySelector('.modal-card-header h3').textContent = title;
        modal.querySelector('#re-record-label').textContent = label || '';
        modal.querySelector('#re-reason').value = '';
        modal.querySelector('#re-requester').value = '';
        modal.style.display = 'flex';
        await loadUsersOnce();
        applyKnownIdentity();
    }

    async function openModal(type, id, label) {
        currentMode = 'edit';
        currentType = type;
        currentId = id;
        currentFill = null;
        await openModalCommon('Request Edit', label);
    }

    async function openFillModal(fill) {
        currentMode = 'fill';
        currentType = fill.type;
        currentId = null;
        currentFill = fill;
        await openModalCommon('Request isi tanggal terlewat', fill.label);
    }

    async function submitRequest() {
        const requester = modal.querySelector('#re-requester').value;
        const reason = modal.querySelector('#re-reason').value.trim();
        if (!requester || !reason) {
            alert('Pilih nama kamu dan isi alasannya dulu.');
            return;
        }
        const payload = {
            mode: currentMode,
            checksheet_type: currentType,
            label: modal.querySelector('#re-record-label').textContent,
            requested_by: requester,
            reason,
        };
        if (currentMode === 'fill') {
            payload.target_date = currentFill.date;
            payload.department_id = currentFill.departmentId;
            payload.condition_id = currentFill.conditionId || null;
        } else {
            payload.header_id = currentId;
        }
        const res = await fetch('ajax/request_edit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Gagal mengirim request.');
            return;
        }
        alert(currentMode === 'fill'
            ? 'Request untuk mengisi tanggal terlewat terkirim. Menunggu persetujuan Admin.'
            : 'Request edit terkirim. Menunggu persetujuan Admin.');
        closeModal();
    }

    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.cs-request-edit-btn');
        if (editBtn) {
            openModal(editBtn.dataset.editType, editBtn.dataset.editId, editBtn.dataset.editLabel);
            return;
        }
        const fillBtn = e.target.closest('.cs-request-fill-btn');
        if (fillBtn) {
            e.preventDefault();
            openFillModal({
                type: fillBtn.dataset.fillType,
                date: fillBtn.dataset.fillDate,
                departmentId: fillBtn.dataset.departmentId,
                conditionId: fillBtn.dataset.conditionId || null,
                label: fillBtn.dataset.fillLabel,
            });
        }
    });
})();

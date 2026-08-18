/**
 * Wires every button with class "cs-delete-btn" (data-delete-type,
 * data-delete-id) to a small PIN-gated confirm modal. The PIN itself is
 * never sent to the browser — it's checked server-side in
 * ajax/delete_checksheet.php, so this file only ever sees "correct" or
 * "incorrect" back.
 */
(function () {
    let modal = null;

    function closeModal() {
        if (modal) { modal.remove(); modal = null; }
    }

    function openModal(type, id, label) {
        closeModal();
        modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-card pin-modal">
                <div class="modal-card-header">
                    <h3>Delete Checksheet</h3>
                    <a href="#" class="modal-close">&times;</a>
                </div>
                <p class="import-hint">Enter the 4-digit PIN to permanently delete${label ? ' "' + label + '"' : ' this checksheet'}. This cannot be undone.</p>
                <div class="pin-error" hidden></div>
                <input type="password" inputmode="numeric" pattern="\\d{4}" maxlength="4" class="pin-input" placeholder="••••" autocomplete="off">
                <div class="modal-actions">
                    <a href="#" class="btn btn-secondary pin-cancel">Cancel</a>
                    <button type="button" class="btn pin-confirm">Delete</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const input = modal.querySelector('.pin-input');
        const errorEl = modal.querySelector('.pin-error');
        input.focus();

        function fail(msg) {
            errorEl.textContent = msg;
            errorEl.hidden = false;
            input.value = '';
            input.focus();
        }

        async function submit() {
            const pin = input.value.trim();
            if (!/^\d{4}$/.test(pin)) { fail('Enter a 4-digit PIN.'); return; }

            const res = await fetch('ajax/delete_checksheet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id, pin }),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                fail(data.error || 'Failed to delete.');
                return;
            }
            window.location.reload();
        }

        modal.querySelector('.pin-confirm').addEventListener('click', submit);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') submit(); });
        modal.querySelector('.pin-cancel').addEventListener('click', (e) => { e.preventDefault(); closeModal(); });
        modal.querySelector('.modal-close').addEventListener('click', (e) => { e.preventDefault(); closeModal(); });
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.cs-delete-btn');
        if (!btn) return;
        e.preventDefault();
        openModal(btn.dataset.deleteType, btn.dataset.deleteId, btn.dataset.deleteLabel || '');
    });
})();

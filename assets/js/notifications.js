/**
 * Top-bar notification bell: toggles the panel, and marks everything read the
 * first time the panel is opened (the unread badge then clears). Read state is
 * saved server-side via ajax/mark_notifications_read.php.
 */
(function () {
    const bell = document.getElementById('notif-bell');
    const panel = document.getElementById('notif-panel');
    const badge = document.getElementById('notif-badge');
    if (!bell || !panel) return;

    let open = false;

    function setOpen(v) {
        open = v;
        panel.hidden = !v;
        bell.setAttribute('aria-expanded', v ? 'true' : 'false');
        if (v && badge && !badge.hidden) markRead();
    }

    async function markRead() {
        badge.hidden = true; // optimistic
        const url = bell.dataset.markUrl || 'ajax/mark_notifications_read.php';
        try {
            await fetch(url, { method: 'POST', keepalive: true });
        } catch (e) { /* leave it; next load reflects true state */ }
    }

    bell.addEventListener('click', (e) => { e.stopPropagation(); setOpen(!open); });
    panel.addEventListener('click', (e) => e.stopPropagation());
    document.addEventListener('click', () => { if (open) setOpen(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open) setOpen(false); });
})();

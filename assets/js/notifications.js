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

    // ---- Live check: toast new notifications that arrive while on the page ----
    const pollUrl = bell.dataset.pollUrl;
    let latestId = parseInt(bell.dataset.latestId || '0', 10) || 0;

    function toastLayer() {
        let l = document.getElementById('toast-layer');
        if (!l) { l = document.createElement('div'); l.id = 'toast-layer'; l.className = 'toast-layer'; document.body.appendChild(l); }
        return l;
    }
    function cap(s) { s = String(s || 'info'); return s.charAt(0).toUpperCase() + s.slice(1); }

    function showToast(n) {
        const el = document.createElement('div');
        el.className = 'toast toast-' + (n.type || 'info');
        const title = document.createElement('div'); title.className = 'toast-title'; title.textContent = n.title || 'Notifikasi';
        const body = document.createElement('div'); body.className = 'toast-body'; body.appendChild(title);
        if (n.body) { const t = document.createElement('div'); t.className = 'toast-text'; t.textContent = n.body; body.appendChild(t); }
        const icon = document.createElement('div'); icon.className = 'toast-icon'; icon.textContent = '🔔';
        const close = document.createElement('button'); close.className = 'toast-close'; close.setAttribute('aria-label', 'Tutup'); close.innerHTML = '&times;';
        el.append(icon, body, close);
        toastLayer().appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        const dismiss = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 250); };
        close.addEventListener('click', (e) => { e.stopPropagation(); dismiss(); });
        el.addEventListener('click', () => { dismiss(); setOpen(true); });
        setTimeout(dismiss, 7000);
    }

    function prependToPanel(n) {
        const list = panel.querySelector('.notif-panel-list');
        if (!list) return;
        const empty = list.querySelector('.notif-empty');
        if (empty) empty.remove();
        const item = document.createElement('div'); item.className = 'notif-item unread';
        const top = document.createElement('div'); top.className = 'notif-item-top';
        const type = document.createElement('span'); type.className = 'notif-type notif-type-' + (n.type || 'info'); type.textContent = cap(n.type);
        const time = document.createElement('span'); time.className = 'notif-time'; time.textContent = 'baru saja';
        top.append(type, time);
        const title = document.createElement('div'); title.className = 'notif-item-title'; title.textContent = n.title || '';
        item.append(top, title);
        if (n.body) { const b = document.createElement('div'); b.className = 'notif-item-body'; b.textContent = n.body; item.appendChild(b); }
        if (n.created_by) { const by = document.createElement('div'); by.className = 'notif-item-by'; by.textContent = '— ' + n.created_by; item.appendChild(by); }
        list.prepend(item);
    }

    async function poll() {
        if (!pollUrl || document.hidden) return;
        try {
            const data = await fetch(pollUrl + '?after=' + latestId).then((r) => r.json());
            if (!data || !Array.isArray(data.items) || !data.items.length) return;
            for (const n of data.items) {
                prependToPanel(n);
                showToast(n);
                if (n.id > latestId) latestId = n.id;
            }
            if (badge) { badge.hidden = false; badge.textContent = data.unread > 99 ? '99+' : String(data.unread); }
        } catch (e) { /* ignore transient errors */ }
    }
    if (pollUrl) setInterval(poll, 10000);
})();

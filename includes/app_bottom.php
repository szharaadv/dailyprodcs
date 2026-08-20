        </div>
    </div>
</div>
<script>
(function () {
    const toggle = document.getElementById('menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    function isMobile() {
        return window.innerWidth <= 960;
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
    }

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    });
    backdrop.addEventListener('click', closeMobileSidebar);

    document.querySelectorAll('[data-nav-toggle]').forEach((parent) => {
        const submenu = parent.nextElementSibling;
        if (!submenu || !submenu.classList.contains('nav-submenu')) return;
        parent.addEventListener('click', (e) => {
            e.preventDefault();
            parent.classList.toggle('active');
            submenu.classList.toggle('open');
        });
    });

    // While the window is actively resized, suppress the sidebar slide
    // transition so it doesn't lag across the mobile/desktop breakpoint and
    // briefly cover the content. Also close the mobile overlay once we grow
    // back to desktop width.
    let resizeTimer;
    window.addEventListener('resize', () => {
        document.body.classList.add('is-resizing');
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => document.body.classList.remove('is-resizing'), 200);
        if (!isMobile()) closeMobileSidebar();
    });
})();
</script>
<script>
    // Who's actually signed in right now (each User logs in with their own
    // PIN — see login.php) — lets the Request Edit modal skip asking "who
    // are you" when we already know. Null for the shared Admin identity.
    const LOGGED_IN_USER_ID = <?= json_encode($me['id'] ?? null) ?>;
</script>
<script src="<?= $base_url ?>assets/js/request-edit.js"></script>
</body>
</html>

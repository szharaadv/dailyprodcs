<?php
/**
 * Shared UI for the department / section / group picker pages (index.php,
 * select_section.php, select_group.php): a branded top bar, a meaningful icon
 * per check sheet, and today's fill-status chip. Keeps the three pages
 * consistent without duplicating markup.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dashboard.php';

/** An inline SVG icon chosen from keywords in a department / section name. */
function picker_icon(string $key): string
{
    $k = strtolower($key);
    $svg = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';

    if (str_contains($k, 'pump') || str_contains($k, 'fo '))
        return $svg('<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l2.5 2.5M16.5 16.5L19 19M19 5l-2.5 2.5M7.5 16.5L5 19"/>');
    if (str_contains($k, 'reject'))
        return $svg('<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>');
    if (str_contains($k, 'test'))
        return $svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');
    if (str_contains($k, 'viscosity'))
        return $svg('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');
    if (str_contains($k, 'paint'))
        return $svg('<path d="M19 11H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2V9a2 2 0 0 0-2-2M5 11V9a2 2 0 0 1 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M7 7h8"/>');
    if (str_contains($k, 'oven') || str_contains($k, 'bake'))
        return $svg('<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M7 13h2M7 16h2"/>');
    if (str_contains($k, 'wash'))
        return $svg('<path d="M12 2s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>');
    if (str_contains($k, 'torque') || str_contains($k, 'assembl'))
        return $svg('<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6 2 2 6-6a4 4 0 0 0 5.4-5.4l-2.3 2.3-1.7-1.7z"/>');
    if (str_contains($k, 'jig') || str_contains($k, 'sub assembly'))
        return $svg('<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>');
    if (str_contains($k, '3s') || str_contains($k, '3t') || str_contains($k, 'checklist'))
        return $svg('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>');
    if (str_contains($k, 'report') || str_contains($k, 'daily'))
        return $svg('<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>');

    return $svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>');
}

/** [cssClass, label] for a section's today state (from dashboard_sections). */
function picker_status(string $state): array
{
    switch ($state) {
        case 'filled':   return ['s-good', 'Filled'];
        case 'optional': return ['s-opt',  'As needed'];
        case 'off':      return ['s-off',  'Day off'];
        default:         return ['s-warn', 'Not filled'];
    }
}

/** [cssClass, label] summarising a department / group: all done vs N not filled. */
function picker_summary(int $total, int $missing): array
{
    if ($total <= 0)   return ['s-opt',  'No check sheet yet'];
    if ($missing <= 0) return ['s-good', 'All done today'];
    return ['s-warn', $missing . ' not filled today'];
}

/** section id => ['state','freq'] for every active section, today. */
function picker_section_info(PDO $pdo): array
{
    $map = [];
    foreach (dashboard_sections($pdo) as $s) {
        $map[(int) $s['id']] = ['state' => $s['state'], 'freq' => $s['freq']];
    }
    return $map;
}

/** The branded top bar shared by every picker page. */
function render_picker_topbar(): void
{
    $me = current_user();
    ?>
    <div class="pk-topbar">
        <a class="pk-brand" href="index.php">
            <div class="pk-mark">DP</div>
            <div class="pk-brand-text"><b>Daily Prod</b><span>Production Check Sheet</span></div>
        </a>
        <?php if ($me): ?>
        <div class="pk-right">
            <div class="pk-user">
                <div class="pk-avatar"><?= htmlspecialchars(user_initials($me['name'])) ?></div>
                <div class="pk-meta">
                    <div class="pk-nm"><?= htmlspecialchars($me['name']) ?></div>
                    <div class="pk-rl"><?= htmlspecialchars($me['role'] ?? '') ?></div>
                </div>
            </div>
            <a class="pk-logout" href="logout.php">Logout</a>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

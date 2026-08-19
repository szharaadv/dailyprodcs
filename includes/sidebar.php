<?php
/**
 * Expects before include:
 * $base_url      - '' when included from root pages
 * $active_nav    - one of: checksheet, view-checksheets, my-drafts, config-import
 * $section_route - optional: the section the CURRENT PAGE belongs to
 *                  ('painting_list.php' / 'assembly_list.php'). When a page
 *                  sets this explicitly, every nav link stays scoped to that
 *                  section, regardless of what $_SESSION happens to hold
 *                  (e.g. left over from a different section opened in
 *                  another tab). Falls back to the session value only when
 *                  the page doesn't know its own section (painting_list.php
 *                  / assembly_list.php do, and also refresh the session so
 *                  later requests stay consistent).
 */
$base_url = $base_url ?? '';
$active_nav = $active_nav ?? '';
$section_route = $section_route ?? ($_SESSION['section_route'] ?? null);

require_once __DIR__ . '/auth.php';
$me = current_user();

$pdo = get_db();

// Resolve department + form_type from the section route itself (not from
// $_SESSION) so the whole sidebar stays locked to the section the current
// page actually belongs to. A handful of routes (e.g. 3s3t_list.php) are
// shared by more than one department's section — in that case, prefer
// whichever match agrees with the session's current department so the
// sidebar reflects the department the user is actually working in.
$nav_department_id = null;
$is_assy_context = false;
if ($section_route) {
    $stmt = $pdo->prepare(
        'SELECT s.department_id, d.form_type FROM m_checksheet_section s
         JOIN m_department d ON d.id = s.department_id
         WHERE s.route = ? AND s.is_active = 1'
    );
    $stmt->execute([$section_route]);
    $matches = $stmt->fetchAll();
    $row = null;
    if (count($matches) > 1 && isset($_SESSION['department_id'])) {
        foreach ($matches as $m) {
            if ((int) $m['department_id'] === (int) $_SESSION['department_id']) { $row = $m; break; }
        }
    }
    $row = $row ?? ($matches[0] ?? null);
    if ($row) {
        $nav_department_id = (int) $row['department_id'];
        $is_assy_context = $row['form_type'] === 'assembly';
    }
}

$draft_table_map = [
    'painting_list.php' => 't_checksheet_header',
    'assembly_list.php' => 't_assy_header',
    'fopump_list.php' => 't_fopump_header',
    'fopump_check_list.php' => 't_fopump_check_header',
    'fopump_test_list.php' => 't_fopump_test_header',
    'fopump_reject_list.php' => 't_fopump_reject_header',
];
$draft_table = $draft_table_map[$section_route] ?? ($is_assy_context ? 't_assy_header' : 't_checksheet_header');
$draft_count = (int)$pdo->query("SELECT COUNT(*) FROM `$draft_table` WHERE status = 'draft'")->fetchColumn();

$checksheet_href = $section_route
    ? $base_url . $section_route . ($nav_department_id ? '?department_id=' . $nav_department_id : '')
    : $base_url . 'index.php';

// View Checksheets / My Drafts depend on the specific section too — each
// section keeps its own results list (and Sub Assembly has no "draft" concept
// at all, since its sheets auto-save as you go).
$view_map = [
    'painting_list.php' => 'view_checksheets.php',
    'assembly_list.php' => 'view_assy_checksheets.php',
    'sub_assembly_list.php' => 'view_jig_checksheets.php',
    'bakeoven_list.php' => 'view_bakeoven_checksheets.php',
    'fopump_list.php' => 'view_fopump_checksheets.php',
    // Check Sheet and Test Record have no daily date (their source sheets
    // don't carry one) — each is a single ongoing record per model, so
    // there's no per-day list to view.
    'fopump_check_list.php' => null,
    'fopump_test_list.php' => null,
    'fopump_reject_list.php' => 'view_fopump_reject_checksheets.php',
    'washing_list.php' => 'view_washing_checksheets.php',
    'paint_viscosity_list.php' => 'view_paint_viscosity_checksheets.php',
    '3s3t_list.php' => 'view_3s3t_checksheets.php',
];
$show_view = !array_key_exists($section_route ?? '', $view_map) || $view_map[$section_route] !== null;
$view_href = $base_url . ($view_map[$section_route] ?? ($is_assy_context ? 'view_assy_checksheets.php' : 'view_checksheets.php'));

$drafts_map = [
    'painting_list.php' => 'my_drafts.php',
    'assembly_list.php' => 'my_assy_drafts.php',
    'sub_assembly_list.php' => null,
    'bakeoven_list.php' => null,
    'washing_list.php' => null,
    'paint_viscosity_list.php' => null,
    '3s3t_list.php' => null,
    'fopump_list.php' => 'my_fopump_drafts.php',
    'fopump_check_list.php' => 'my_fopump_check_drafts.php',
    'fopump_test_list.php' => 'my_fopump_test_drafts.php',
    'fopump_reject_list.php' => 'my_fopump_reject_drafts.php',
];
$show_drafts = !array_key_exists($section_route ?? '', $drafts_map) || $drafts_map[$section_route] !== null;
$drafts_href = $base_url . ($drafts_map[$section_route] ?? ($is_assy_context ? 'my_assy_drafts.php' : 'my_drafts.php'));

// Import Data: scope the link to whichever section the current page belongs
// to, so it lands straight there instead of defaulting to the first one.
$import_section_id = null;
if ($section_route && $nav_department_id) {
    $stmt = $pdo->prepare('SELECT id FROM m_checksheet_section WHERE route = ? AND department_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$section_route, $nav_department_id]);
    $import_section_id = $stmt->fetchColumn() ?: null;
}
$import_href = $base_url . 'admin/import_data.php' . ($import_section_id ? ('?section_id=' . $import_section_id) : '');

function icon(string $name): string
{
    $icons = [
        'doc'      => '<path d="M6 2h9l5 5v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M15 2v5h5"/>',
        'folder'   => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6z"/>',
        'edit'     => '<path d="M4 20h4L18 10l-4-4L4 16v4z"/><path d="M13 7l4 4"/>',
        'upload'   => '<path d="M12 16V4"/><path d="M6 10l6-6 6 6"/><path d="M4 20h16"/>',
        'sliders'  => '<path d="M4 6h9"/><path d="M17 6h3"/><circle cx="14" cy="6" r="2"/><path d="M4 12h3"/><path d="M11 12h9"/><circle cx="8" cy="12" r="2"/><path d="M4 18h9"/><path d="M17 18h3"/><circle cx="14" cy="18" r="2"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'chevron'  => '<path d="M9 6l6 6-6 6"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">DP</div>
        <div class="brand-text">
            <div class="brand-title">Daily Prod</div>
            <div class="brand-subtitle">Production Check Sheet</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Workspace</div>
        <a class="nav-item <?= $active_nav === 'checksheet' ? 'active' : '' ?>" href="<?= $checksheet_href ?>">
            <?= icon('doc') ?> Check Sheet
        </a>
        <?php if ($show_view): ?>
        <a class="nav-item <?= $active_nav === 'view-checksheets' ? 'active' : '' ?>" href="<?= $view_href ?>">
            <?= icon('folder') ?> View Checksheets
        </a>
        <?php endif; ?>
        <?php if ($show_drafts): ?>
        <a class="nav-item <?= $active_nav === 'my-drafts' ? 'active' : '' ?>" href="<?= $drafts_href ?>">
            <?= icon('edit') ?> My Drafts
            <?php if ($draft_count > 0): ?><span class="nav-badge"><?= $draft_count ?></span><?php endif; ?>
        </a>
        <?php endif; ?>

        <?php
        // Configuration links depend on the specific section (route) the user
        // is currently in, not just the department — each check sheet section
        // has its own master data (e.g. Torque's Model vs Sub Assembly's Jig).
        $config_map = [
            'painting_list.php' => [
                ['key' => 'config-condition', 'label' => 'Condition', 'href' => 'admin/conditions.php'],
                ['key' => 'config-checklist-item', 'label' => 'Checking Item', 'href' => 'admin/checklist_items.php'],
                ['key' => 'config-shift', 'label' => 'Shift', 'href' => 'admin/shifts.php'],
            ],
            'assembly_list.php' => [
                ['key' => 'config-assy-model', 'label' => 'Model', 'href' => 'admin/assy_models.php'],
                ['key' => 'config-assy-checklist-item', 'label' => 'Checking Item', 'href' => 'admin/assy_checklist_items.php'],
            ],
            'sub_assembly_list.php' => [
                ['key' => 'config-jig', 'label' => 'Jig', 'href' => 'admin/jigs.php'],
                ['key' => 'config-jig-item', 'label' => 'Checking Item', 'href' => 'admin/jig_items.php'],
            ],
            'bakeoven_list.php' => [
                ['key' => 'config-bakeoven', 'label' => 'Oven', 'href' => 'admin/bakeovens.php'],
                ['key' => 'config-bakeoven-time', 'label' => 'Checking Time', 'href' => 'admin/bakeoven_times.php'],
            ],
            'fopump_list.php' => [
                ['key' => 'config-fopump-import', 'label' => 'Import Data', 'href' => 'admin/import_fopump.php'],
            ],
            'fopump_check_list.php' => [
                ['key' => 'config-fopump-check-model', 'label' => 'Model', 'href' => 'admin/fopump_check_models.php'],
                ['key' => 'config-fopump-check-item', 'label' => 'Checking Item', 'href' => 'admin/fopump_check_items.php'],
            ],
            'fopump_test_list.php' => [
                ['key' => 'config-fopump-test-model', 'label' => 'Model', 'href' => 'admin/fopump_test_models.php'],
            ],
            'fopump_reject_list.php' => [],
            'washing_list.php' => [],
            'paint_viscosity_list.php' => [
                ['key' => 'config-paint-viscosity-item', 'label' => 'Product', 'href' => 'admin/paint_viscosity_items.php'],
            ],
            '3s3t_list.php' => [
                ['key' => 'config-3s3t-item', 'label' => 'Item', 'href' => 'admin/3s3t_items.php'],
            ],
        ];
        $config_items = $config_map[$section_route] ?? $config_map['painting_list.php'];
        $show_import = in_array($section_route, ['painting_list.php', 'assembly_list.php'], true);
        $config_children = array_column($config_items, 'key');
        if ($show_import) $config_children[] = 'config-import';
        $config_open = in_array($active_nav, $config_children, true);
        $mgmt_children = ['mgmt-users'];
        $mgmt_open = in_array($active_nav, $mgmt_children, true);
        ?>
        <div class="nav-group-label">Master Data</div>
        <a class="nav-parent <?= $config_open ? 'active' : '' ?>" href="#" data-nav-toggle>
            <?= icon('gear') ?> Configuration
            <?= icon('chevron') ?>
        </a>
        <div class="nav-submenu <?= $config_open ? 'open' : '' ?>">
            <?php foreach ($config_items as $item): ?>
                <a class="nav-subitem <?= $active_nav === $item['key'] ? 'active' : '' ?>" href="<?= $base_url . $item['href'] ?>"><?= htmlspecialchars($item['label']) ?></a>
            <?php endforeach; ?>
            <?php if ($show_import): ?>
                <div class="nav-subdivider"></div>
                <a class="nav-subitem <?= $active_nav === 'config-import' ? 'active' : '' ?>" href="<?= $import_href ?>">Import Data</a>
            <?php endif; ?>
        </div>

        <a class="nav-item <?= $active_nav === 'config-master-engine' ? 'active' : '' ?>" href="<?= $base_url ?>admin/engines.php">
            <?= icon('sliders') ?> Master Engine
        </a>
        <a class="nav-item <?= $active_nav === 'config-holidays' ? 'active' : '' ?>" href="<?= $base_url ?>admin/holidays.php">
            <?= icon('clock') ?> YADIN Calendar
        </a>

        <?php if (is_admin()): ?>
        <div class="nav-group-label">Management</div>
        <a class="nav-parent <?= $mgmt_open ? 'active' : '' ?>" href="#" data-nav-toggle>
            <?= icon('users') ?> Management
            <?= icon('chevron') ?>
        </a>
        <div class="nav-submenu <?= $mgmt_open ? 'open' : '' ?>">
            <a class="nav-subitem <?= $active_nav === 'mgmt-users' ? 'active' : '' ?>" href="<?= $base_url ?>admin/users.php">Users</a>
        </div>
        <?php endif; ?>
    </nav>

</aside>

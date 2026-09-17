<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dashboard.php';
require_login();
$pdo = get_db();

$sections = dashboard_sections($pdo);
$missing = array_values(array_filter($sections, fn($s) => $s['state'] === 'missing'));

// Group by group_label (e.g. FO Pump) when set, else by department — so a
// grouped product line reads as its own section, not buried under its department.
$byDept = [];
foreach ($sections as $s) {
    $key = !empty($s['group_label']) ? $s['group_label'] : $s['dept_name'];
    $byDept[$key][] = $s;
}

$stateMeta = [
    'missing'  => ['label' => 'Not filled',   'cls' => 'ds-pill-missing'],
    'filled'   => ['label' => 'Filled',       'cls' => 'ds-pill-filled'],
    'optional' => ['label' => 'As needed',    'cls' => 'ds-pill-optional'],
    'off'      => ['label' => 'Off day',      'cls' => 'ds-pill-off'],
];

$me = current_user();
$base_url = '';
$active_nav = 'dashboard';
$page_title = 'Dashboard';
$page_subtitle = "Today's check sheet status · " . date('l, d M Y');
require __DIR__ . '/includes/app_top.php';
?>

<div class="ds-summary">
    <div class="ds-summary-main <?= count($missing) ? 'ds-summary-warn' : 'ds-summary-ok' ?>">
        <div class="ds-summary-num"><?= count($missing) ?></div>
        <div class="ds-summary-text">
            <?= count($missing) ? 'check sheet(s) not filled today' : 'All required check sheets are done 🎉' ?>
        </div>
    </div>
    <?php if ($missing): ?>
    <div class="ds-summary-list">
        <?php foreach ($missing as $s): ?>
            <a class="ds-chip" href="<?= htmlspecialchars($s['route']) ?>?department_id=<?= (int)$s['department_id'] ?>"><?= htmlspecialchars($s['name']) ?> &rarr;</a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($byDept as $deptName => $rows): ?>
<div class="ds-group">
    <div class="ds-group-title"><?= htmlspecialchars($deptName) ?></div>
    <div class="ds-list">
        <?php foreach ($rows as $s): $meta = $stateMeta[$s['state']]; ?>
        <a class="ds-row ds-row-<?= $s['state'] ?>" href="<?= htmlspecialchars($s['route']) ?>?department_id=<?= (int)$s['department_id'] ?>">
            <div class="ds-row-main">
                <div class="ds-row-name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="ds-row-freq"><?= htmlspecialchars(dashboard_freq_label($s['freq'])) ?></div>
            </div>
            <span class="ds-pill <?= $meta['cls'] ?>"><?= htmlspecialchars($meta['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<p class="import-hint" style="margin-top:16px;">
    "As needed" = not required every day (e.g. only when there's data).
    <?php if (is_admin()): ?>Set which are required in <a href="admin/dashboard_settings.php">Dashboard Settings</a>.<?php endif; ?>
</p>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>

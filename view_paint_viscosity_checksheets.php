<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$year = (int)($_GET['year'] ?? date('Y'));

$stmt = $pdo->prepare(
    "SELECT h.*, fm.name AS foreman_name,
            (SELECT COUNT(*) FROM m_paint_viscosity_item i WHERE i.department_id = h.department_id AND i.is_active = 1) AS total_items,
            (SELECT COUNT(*) FROM t_paint_viscosity_detail d WHERE d.header_id = h.id AND d.actual_result IS NOT NULL AND d.actual_result <> '') AS filled_count,
            (SELECT COUNT(*) FROM t_paint_viscosity_detail d
                 JOIN m_paint_viscosity_item i2 ON i2.id = d.item_id
                 WHERE d.header_id = h.id
                   AND d.actual_result REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$'
                   AND (CAST(d.actual_result AS DECIMAL(6,2)) < CAST(i2.standard_min AS DECIMAL(6,2))
                     OR CAST(d.actual_result AS DECIMAL(6,2)) > CAST(i2.standard_max AS DECIMAL(6,2)))
            ) AS ng_count
     FROM t_paint_viscosity_header h
     LEFT JOIN m_user fm ON fm.id = h.foreman_id
     WHERE h.department_id = ? AND h.year = ?
     ORDER BY h.month"
);
$stmt->execute([$department_id, $year]);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'paint_viscosity_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Paint Viscosity records';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Year</label>
            <select name="year">
                <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <?php
        $daysInThisMonth = (int)date('t', mktime(0, 0, 0, $row['month'], 1, $row['year']));
        $totalCells = $daysInThisMonth * max(1, (int)$row['total_items']);
    ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= substr($monthNames[$row['month']], 0, 3) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title">Paint Viscosity</div>
            <div class="cs-card-meta">
                <?= (int)$row['filled_count'] ?> of <?= $totalCells ?> readings filled
                <?php if ($row['ng_count'] > 0): ?> &middot; <span style="color:#9b3b32;font-weight:700;"><?= (int)$row['ng_count'] ?> out of range</span><?php endif; ?>
                <?php if ($row['foreman_name']): ?> &middot; Foreman <?= htmlspecialchars($row['foreman_name']) ?><?php endif; ?>
            </div>
        </div>
        <span class="cs-status <?= $row['ng_count'] > 0 ? 'cs-status-draft' : 'cs-status-submitted' ?>"><?= $row['ng_count'] > 0 ? 'Out of Range' : 'All OK' ?></span>
        <button type="button" class="cs-request-edit-btn" data-edit-type="paint_viscosity" data-edit-id="<?= $row['id'] ?>" data-edit-label="<?= htmlspecialchars('Paint Viscosity - ' . $monthNames[$row['month']] . ' ' . $row['year']) ?>">Request Edit</button>
        <a href="paint_viscosity_list.php?department_id=<?= $department_id ?>&month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="cs-view-btn">Open &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No paint viscosity records found for <?= $year ?>.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

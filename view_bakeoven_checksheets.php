<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM m_bakeoven WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$department_id]);
$ovens = $stmt->fetchAll();

$selected_oven_id = (int)($_GET['bakeoven_id'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

$where = ['o.department_id = ?', 'h.year = ?'];
$params = [$department_id, $year];
if ($selected_oven_id) {
    $where[] = 'h.bakeoven_id = ?';
    $params[] = $selected_oven_id;
}

$sql = "SELECT h.*, o.name AS oven_name, o.standard_min, o.standard_max, chk.name AS checker_name,
               (SELECT COUNT(*) FROM m_bakeoven_time t WHERE t.bakeoven_id = o.id AND t.is_active = 1) AS total_times,
               (SELECT COUNT(*) FROM t_bakeoven_detail d WHERE d.header_id = h.id AND d.actual_temp IS NOT NULL AND d.actual_temp <> '') AS filled_count,
               (SELECT COUNT(*) FROM t_bakeoven_detail d
                    WHERE d.header_id = h.id
                      AND d.actual_temp REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$'
                      AND (CAST(d.actual_temp AS DECIMAL(6,2)) < CAST(o.standard_min AS DECIMAL(6,2))
                        OR CAST(d.actual_temp AS DECIMAL(6,2)) > CAST(o.standard_max AS DECIMAL(6,2)))
               ) AS ng_count
        FROM t_bakeoven_header h
        JOIN m_bakeoven o ON o.id = h.bakeoven_id
        LEFT JOIN m_user chk ON chk.id = h.supervisor_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY o.sort_order, o.id, h.month';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'bakeoven_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Bake Oven temperature records';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Oven</label>
            <select name="bakeoven_id">
                <option value="0">All Ovens</option>
                <?php foreach ($ovens as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $o['id'] == $selected_oven_id ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Year</label>
            <select name="year">
                <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn">Search</button>
    </div>
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <?php
        $daysInThisMonth = (int)date('t', mktime(0, 0, 0, $row['month'], 1, $row['year']));
        $totalCells = $daysInThisMonth * max(1, (int)$row['total_times']);
    ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= substr($monthNames[$row['month']], 0, 3) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['oven_name']) ?></div>
            <div class="cs-card-meta">
                <?= (int)$row['filled_count'] ?> of <?= $totalCells ?> readings filled
                <?php if ($row['ng_count'] > 0): ?> &middot; <span style="color:#9b3b32;font-weight:700;"><?= (int)$row['ng_count'] ?> out of range</span><?php endif; ?>
                <?php if ($row['checker_name']): ?> &middot; Supervisor <?= htmlspecialchars($row['checker_name']) ?><?php endif; ?>
            </div>
        </div>
        <span class="cs-status <?= $row['ng_count'] > 0 ? 'cs-status-draft' : 'cs-status-submitted' ?>"><?= $row['ng_count'] > 0 ? 'Out of Range' : 'All OK' ?></span>
        <a href="bakeoven_list.php?department_id=<?= $department_id ?>&bakeoven_id=<?= $row['bakeoven_id'] ?>&month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="cs-view-btn">Open &rarr;</a>
        <button type="button" class="cs-delete-btn" data-delete-type="bakeoven" data-delete-id="<?= $row['id'] ?>" data-delete-label="<?= htmlspecialchars($row['oven_name'] . ' · ' . $monthNames[$row['month']] . ' ' . $row['year']) ?>">Delete</button>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No bake oven records found for <?= $year ?>.</div><?php endif; ?>
</div>

<script src="assets/js/delete-pin.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

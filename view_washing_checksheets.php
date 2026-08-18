<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$year = (int)($_GET['year'] ?? date('Y'));

$sql = "SELECT h.*,
               (SELECT COUNT(*) FROM t_washing_detail d WHERE d.header_id = h.id
                    AND (d.ganti_air IS NOT NULL OR d.temperatur_air IS NOT NULL OR d.penambahan_gildaon IS NOT NULL OR d.total_acid IS NOT NULL)
               ) AS filled_count
        FROM t_washing_header h
        WHERE h.department_id = ? AND h.year = ?
        ORDER BY h.month";
$stmt = $pdo->prepare($sql);
$stmt->execute([$department_id, $year]);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'washing_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Washing Machine Liquid Monitoring records';
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
    <?php $daysInThisMonth = (int)date('t', mktime(0, 0, 0, $row['month'], 1, $row['year'])); ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= substr($monthNames[$row['month']], 0, 3) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title">Washing Machine Liquid Monitoring</div>
            <div class="cs-card-meta"><?= (int)$row['filled_count'] ?> of <?= $daysInThisMonth ?> days filled</div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="washing_list.php?department_id=<?= $department_id ?>&month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="cs-view-btn">Open &rarr;</a>
        <button type="button" class="cs-delete-btn" data-delete-type="washing" data-delete-id="<?= $row['id'] ?>" data-delete-label="<?= htmlspecialchars($monthNames[$row['month']] . ' ' . $row['year']) ?>">Delete</button>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No washing machine records found for <?= $year ?>.</div><?php endif; ?>
</div>

<script src="assets/js/delete-pin.js"></script>
<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

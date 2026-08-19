<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/calendar_lib.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare(
    "SELECT h.*,
        (SELECT COALESCE(SUM(production_qty),0) FROM t_fopump_line WHERE header_id = h.id) AS prod_total,
        (SELECT COALESCE(SUM(assembly_qty),0) FROM t_fopump_line WHERE header_id = h.id) AS assy_total,
        (SELECT COALESCE(SUM(export_qty),0) FROM t_fopump_line WHERE header_id = h.id) AS exp_total
     FROM t_fopump_header h
     WHERE h.department_id = ? AND YEAR(h.tanggal) = ? AND MONTH(h.tanggal) = ? AND h.status = 'submitted'
     ORDER BY h.tanggal"
);
$stmt->execute([$department_id, $year, $month]);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// ---- Missing checks: working days this month with no submitted report at
// all (one header per day, no sub-entity breakdown). Only up to today.
$today = date('Y-m-d');
$capEnd = min($monthEnd, $today);
$missingDates = [];

if ($department_id && $capEnd >= $monthStart) {
    $workingDays = get_working_days($pdo, $monthStart, $capEnd);

    $presentStmt = $pdo->prepare(
        "SELECT DATE(tanggal) AS d FROM t_fopump_header
         WHERE department_id = ? AND tanggal BETWEEN ? AND ? AND status = 'submitted'"
    );
    $presentStmt->execute([$department_id, $monthStart, $capEnd]);
    $present = array_flip($presentStmt->fetchAll(PDO::FETCH_COLUMN));

    $missingDates = array_values(array_filter($workingDays, fn($d) => !isset($present[$d])));
}

$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view FO Pump daily reports';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Month</label>
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                <?php endfor; ?>
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
</form>

<?php if ($missingDates): ?>
<div class="missing-banner">
    <div class="missing-banner-title">&#9888; Missing checks this month</div>
    <div class="missing-banner-row">
        <span class="missing-banner-cond">FO Pump Daily Report</span>
        <span class="missing-banner-dates"><?= format_missing_dates($missingDates) ?></span>
    </div>
</div>
<?php endif; ?>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($row['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title">Production <?= (int)$row['prod_total'] ?> &middot; To Assy <?= (int)$row['assy_total'] ?> &middot; Export <?= (int)$row['exp_total'] ?></div>
            <div class="cs-card-meta"><?= htmlspecialchars($row['shift_label'] ?: 'No shift set') ?><?= $row['employee_count'] ? ' · ' . (int)$row['employee_count'] . ' employee(s)' : '' ?></div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="view_fopump_checksheet_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
        <button type="button" class="cs-delete-btn" data-delete-type="fopump" data-delete-id="<?= $row['id'] ?>" data-delete-label="FO Pump &middot; <?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))) ?>">Delete</button>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No FO Pump reports found for this month.</div><?php endif; ?>
</div>

<script src="assets/js/delete-pin.js"></script>
<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

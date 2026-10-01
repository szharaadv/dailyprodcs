<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/calendar_lib.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

// Find the Painting department by the section route (a stable natural key —
// ids differ between the local and server copies of the DB).
$department_id = (int)$pdo->query(
    "SELECT department_id FROM m_checksheet_section
     WHERE route = 'painting_prod_list.php' AND is_active = 1
     ORDER BY sort_order, id LIMIT 1"
)->fetchColumn();

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare(
    "SELECT h.*, u.name AS checker_name, sh.name AS shift_name,
        (SELECT COALESCE(SUM(cb),0)     FROM t_painting_prod_line WHERE header_id = h.id) AS cb_total,
        (SELECT COALESCE(SUM(fot),0)    FROM t_painting_prod_line WHERE header_id = h.id) AS fot_total,
        (SELECT COALESCE(SUM(fw),0)     FROM t_painting_prod_line WHERE header_id = h.id) AS fw_total,
        (SELECT COALESCE(SUM(part),0)   FROM t_painting_prod_line WHERE header_id = h.id) AS part_total,
        (SELECT COALESCE(SUM(others),0) FROM t_painting_prod_line WHERE header_id = h.id) AS others_total
     FROM t_painting_prod_header h
     LEFT JOIN m_user u ON u.id = h.checker_id
     LEFT JOIN m_shift sh ON sh.id = h.shift_id
     WHERE h.department_id = ? AND YEAR(h.tanggal) = ? AND MONTH(h.tanggal) = ? AND h.status = 'submitted'
     ORDER BY h.tanggal"
);
$stmt->execute([$department_id, $year, $month]);
$results = $stmt->fetchAll();

// ---- Missing checks: working days this month with no submitted report at all.
$today = date('Y-m-d');
$capEnd = min($monthEnd, $today);
$missingDates = [];

if ($department_id && $capEnd >= $monthStart) {
    $workingDays = get_working_days($pdo, $monthStart, $capEnd);

    $presentStmt = $pdo->prepare(
        "SELECT DATE(tanggal) AS d FROM t_painting_prod_header
         WHERE department_id = ? AND tanggal BETWEEN ? AND ? AND status = 'submitted'"
    );
    $presentStmt->execute([$department_id, $monthStart, $capEnd]);
    $present = array_flip($presentStmt->fetchAll(PDO::FETCH_COLUMN));

    $missingDates = array_values(array_filter($workingDays, fn($d) => !isset($present[$d])));
}

$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'painting_prod_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Painting daily reports';
require __DIR__ . '/includes/app_top.php';
$export_route = 'painting_prod_list.php'; $export_dept = $department_id; require __DIR__ . '/includes/export_button.php';

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
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
<?php $fillUnlockSet = active_fill_unlock_set($pdo, 'painting_prod', (int)$department_id); ?>
<div class="missing-banner">
    <div class="missing-banner-title">&#9888; Missing checks this month</div>
    <div class="missing-banner-row">
        <span class="missing-banner-cond">Painting Daily Report</span>
        <span class="missing-banner-dates"><?= format_missing_dates($missingDates) ?></span>
        <?php if (in_array(date('Y-m-d', strtotime('-1 day')), $missingDates, true)): ?>
            <a class="missing-banner-fill-btn" href="painting_prod_list.php?department_id=<?= $department_id ?>&tanggal=<?= date('Y-m-d', strtotime('-1 day')) ?>">Fill yesterday</a>
        <?php elseif (in_array(date('Y-m-d'), $missingDates, true)): ?>
            <a class="missing-banner-fill-btn" href="painting_prod_list.php?department_id=<?= $department_id ?>">Fill today</a>
        <?php endif; ?>
        <?php
        // Dates older than yesterday need Admin approval (no-backdating rule).
        $oldMissing = array_values(array_filter($missingDates, fn($d) => $d < date('Y-m-d', strtotime('-1 day'))));
        foreach ($oldMissing as $d): ?>
            <?php if (!empty($fillUnlockSet['|' . $d])): ?>
                <a class="missing-banner-fill-btn" href="painting_prod_list.php?department_id=<?= $department_id ?>&fill_date=<?= htmlspecialchars($d) ?>">Fill <?= htmlspecialchars(date('d/m', strtotime($d))) ?> &check;</a>
            <?php else: ?>
                <button type="button" class="missing-banner-fill-btn missing-banner-req-btn cs-request-fill-btn"
                        data-fill-type="painting_prod"
                        data-fill-date="<?= htmlspecialchars($d) ?>"
                        data-department-id="<?= $department_id ?>"
                        data-fill-label="<?= htmlspecialchars('Painting Daily Report — ' . date('d/m/Y', strtotime($d))) ?>">Request <?= htmlspecialchars(date('d/m', strtotime($d))) ?></button>
            <?php endif; ?>
        <?php endforeach; ?>
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
            <div class="cs-card-title">CB <?= (int)$row['cb_total'] ?> &middot; FOT <?= (int)$row['fot_total'] ?> &middot; FW <?= (int)$row['fw_total'] ?> &middot; PART <?= (int)$row['part_total'] ?> &middot; OTHERS <?= (int)$row['others_total'] ?></div>
            <div class="cs-card-meta"><?= htmlspecialchars($row['shift_name'] ?: 'No shift set') ?><?= $row['checker_name'] ? ' · ' . htmlspecialchars($row['checker_name']) : '' ?></div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <?php if (is_admin()): ?>
            <a class="cs-view-btn-sm" href="painting_prod_list.php?edit_id=<?= $row['id'] ?>">Edit</a>
        <?php else: ?>
        <button type="button" class="cs-request-edit-btn" data-edit-type="painting_prod" data-edit-id="<?= $row['id'] ?>" data-edit-label="<?= htmlspecialchars('Painting Daily Report - ' . date('d M Y', strtotime($row['tanggal']))) ?>">Request Edit</button>
        <?php endif; ?>
        <a href="view_painting_prod_checksheet_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No Painting reports found for this month.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

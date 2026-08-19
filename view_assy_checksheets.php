<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/calendar_lib.php';
require_login();
$pdo = get_db();

$departments = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' ORDER BY sort_order, id")->fetchAll();

$selected_department_id = (int)($_GET['department_id'] ?? ($_SESSION['department_id'] ?? 0));
if (!in_array($selected_department_id, array_column($departments, 'id'))) {
    $selected_department_id = $departments[0]['id'] ?? 0;
}

$stmt = $pdo->prepare('SELECT * FROM m_assy_model WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_department_id]);
$models = $stmt->fetchAll();

$selected_model_id = (int)($_GET['model_id'] ?? 0);

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$where = ["h.status = 'submitted'", 'h.tanggal BETWEEN ? AND ?', 'h.department_id = ?'];
$params = [$monthStart, $monthEnd, $selected_department_id];

if ($selected_model_id) {
    $where[] = 'h.model_id = ?';
    $params[] = $selected_model_id;
}

$sql = 'SELECT h.*, d.name AS department_name, m.name AS model_name, ck.name AS checker_name
        FROM t_assy_header h
        JOIN m_department d ON d.id = h.department_id
        JOIN m_assy_model m ON m.id = h.model_id
        JOIN m_user ck ON ck.id = h.checker_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.tanggal DESC, h.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// ---- Missing checks: working days this month with zero submitted
// checksheets at all (any one engine/model checked that day is enough —
// Torque only requires one engine input per day, not every model).
// Only days up to today are considered.
$today = date('Y-m-d');
$capEnd = min($monthEnd, $today);
$missingDates = [];

if ($selected_department_id && $capEnd >= $monthStart) {
    $workingDays = get_working_days($pdo, $monthStart, $capEnd);

    $presentStmt = $pdo->prepare(
        "SELECT DISTINCT DATE(tanggal) AS d FROM t_assy_header
         WHERE department_id = ? AND tanggal BETWEEN ? AND ? AND status = 'submitted'"
    );
    $presentStmt->execute([$selected_department_id, $monthStart, $capEnd]);
    $present = array_flip($presentStmt->fetchAll(PDO::FETCH_COLUMN));

    $missingDates = array_values(array_filter($workingDays, fn($d) => !isset($present[$d])));
}

$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'assembly_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view submitted Torque checksheet results';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Department</label>
            <select name="department_id">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Month</label>
            <select name="month">
                <?php
                $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach ($monthNames as $i => $mName): ?>
                    <option value="<?= $i + 1 ?>" <?= ($i + 1) == $month ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Year</label>
            <select name="year">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Model</label>
            <select name="model_id">
                <option value="0">All Models</option>
                <?php foreach ($models as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $selected_model_id ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($missingDates): ?>
<div class="missing-banner">
    <div class="missing-banner-title">&#9888; Missing checks this month</div>
    <div class="missing-banner-row">
        <span class="missing-banner-dates"><?= format_missing_dates($missingDates) ?></span>
        <?php if (in_array(date('Y-m-d'), $missingDates, true)): ?>
            <a class="missing-banner-fill-btn" href="assembly_list.php?department_id=<?= $selected_department_id ?>">Fill today</a>
        <?php endif; ?>
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
            <div class="cs-card-title"><?= htmlspecialchars($row['model_name']) ?><?php if ($row['detail_model']): ?> &middot; <?= htmlspecialchars($row['detail_model']) ?><?php endif; ?></div>
            <div class="cs-card-meta">Checked by <?= htmlspecialchars($row['checker_name']) ?><?php if ($row['no_engine']): ?> &middot; Engine <?= htmlspecialchars($row['no_engine']) ?><?php endif; ?></div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="view_assy_checksheet_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No checksheets found for this date range / filter.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
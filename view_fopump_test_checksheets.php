<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM m_fopump_test_model WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$department_id]);
$models = $stmt->fetchAll();

$selected_model_id = (int)($_GET['model_id'] ?? 0);

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$where = ["h.status = 'submitted'", 'h.tanggal BETWEEN ? AND ?', 'h.department_id = ?'];
$params = [$monthStart, $monthEnd, $department_id];

if ($selected_model_id) {
    $where[] = 'h.model_id = ?';
    $params[] = $selected_model_id;
}

$sql = 'SELECT h.*, m.name AS model_name, ck.name AS checker_name,
            (SELECT COUNT(*) FROM t_fopump_test_row WHERE header_id = h.id) AS row_count
        FROM t_fopump_test_header h
        JOIN m_fopump_test_model m ON m.id = h.model_id
        JOIN m_user ck ON ck.id = h.checker_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.tanggal DESC, h.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'fopump_test_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view submitted FO Pump test records';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
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
    <div class="form-row">
        <button type="submit" class="btn">Search</button>
    </div>
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($row['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($row['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['model_name']) ?></div>
            <div class="cs-card-meta">Checked by <?= htmlspecialchars($row['checker_name']) ?> &middot; <?= (int)$row['row_count'] ?> row(s)</div>
        </div>
        <span class="cs-status cs-status-submitted">Submitted</span>
        <a href="view_fopump_test_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn">View &rarr;</a>
        <button type="button" class="cs-delete-btn" data-delete-type="fopump_test" data-delete-id="<?= $row['id'] ?>" data-delete-label="<?= htmlspecialchars($row['model_name'] . ' · ' . date('d/m/Y', strtotime($row['tanggal']))) ?>">Delete</button>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No checksheets found for this date range / filter.</div><?php endif; ?>
</div>

<script src="assets/js/delete-pin.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

if (isset($_GET['department_id'])) {
    $_SESSION['department_id'] = (int)$_GET['department_id'];
}
$department_id = (int)($_SESSION['department_id'] ?? 0);

$department = null;
if ($department_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_department WHERE id = ? AND is_active = 1');
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}
if (!$department) {
    header('Location: index.php');
    exit;
}

$year = (int)($_GET['year'] ?? date('Y'));
$selected_line = trim((string)($_GET['line'] ?? ''));

$where = ['h.department_id = ?', 'h.year = ?'];
$params = [$department_id, $year];
if ($selected_line !== '') {
    $where[] = 'h.line = ?';
    $params[] = $selected_line;
}

$stmt = $pdo->prepare(
    "SELECT h.*, op.name AS operator_name,
            (SELECT COUNT(*) FROM m_3s3t_item i WHERE i.department_id = h.department_id AND i.is_active = 1) AS total_items,
            (SELECT COUNT(*) FROM t_3s3t_detail d WHERE d.header_id = h.id
                AND (d.week1 IS NOT NULL OR d.week2 IS NOT NULL OR d.week3 IS NOT NULL OR d.week4 IS NOT NULL OR d.week5 IS NOT NULL)
            ) AS filled_items,
            (SELECT COUNT(*) FROM t_3s3t_detail d WHERE d.header_id = h.id
                AND (d.week1 = 'NG' OR d.week2 = 'NG' OR d.week3 = 'NG' OR d.week4 = 'NG' OR d.week5 = 'NG')
            ) AS ng_count
     FROM t_3s3t_header h
     LEFT JOIN m_user op ON op.id = h.operator_id
     WHERE " . implode(' AND ', $where) . '
     ORDER BY h.line, h.month'
);
$stmt->execute($params);
$results = $stmt->fetchAll();

$linesStmt = $pdo->prepare('SELECT DISTINCT line FROM t_3s3t_header WHERE department_id = ? ORDER BY line');
$linesStmt->execute([$department_id]);
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = '3s3t_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Checksheet 3S-3T records';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Line</label>
            <select name="line">
                <option value="">All Lines</option>
                <?php foreach ($lines as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>" <?= $l === $selected_line ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
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
</form>

<div class="cs-card-list">
    <?php foreach ($results as $row): ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= substr($monthNames[$row['month']], 0, 3) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['line']) ?></div>
            <div class="cs-card-meta">
                <?= (int)$row['filled_items'] ?> of <?= (int)$row['total_items'] ?> items checked
                <?php if ($row['ng_count'] > 0): ?> &middot; <span style="color:#9b3b32;font-weight:700;"><?= (int)$row['ng_count'] ?> NG</span><?php endif; ?>
                <?php if ($row['operator_name']): ?> &middot; OP <?= htmlspecialchars($row['operator_name']) ?><?php endif; ?>
            </div>
        </div>
        <span class="cs-status <?= $row['ng_count'] > 0 ? 'cs-status-draft' : 'cs-status-submitted' ?>"><?= $row['ng_count'] > 0 ? 'Has NG' : 'All OK' ?></span>
        <button type="button" class="cs-request-edit-btn" data-edit-type="3s3t" data-edit-id="<?= $row['id'] ?>" data-edit-label="<?= htmlspecialchars($row['line'] . ' - ' . $monthNames[$row['month']] . ' ' . $row['year']) ?>">Request Edit</button>
        <a href="3s3t_list.php?department_id=<?= $department_id ?>&line=<?= urlencode($row['line']) ?>&month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="cs-view-btn">Open &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No 3S-3T records found for <?= $year ?>.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/calendar_lib.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE LOWER(name) = 'painting' LIMIT 1")->fetch();
$selected_department_id = $department ? (int) $department['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_department_id]);
$conditions = $stmt->fetchAll();

$selected_condition_id = (int)($_GET['condition_id'] ?? 0);

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));
$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$where = ["h.status = 'submitted'", 'h.tanggal BETWEEN ? AND ?'];
$params = [$monthStart, $monthEnd];

if ($selected_department_id) {
    $where[] = 'h.department_id = ?';
    $params[] = $selected_department_id;
}
if ($selected_condition_id) {
    $where[] = 'h.condition_id = ?';
    $params[] = $selected_condition_id;
}

$sql = 'SELECT h.*, d.name AS department_name, c.name AS condition_name, ck.name AS checker_name, s.name AS shift_name,
               (SELECT COUNT(*) FROM t_checksheet_detail x WHERE x.header_id = h.id) AS item_count,
               (SELECT COUNT(*) FROM t_checksheet_detail x WHERE x.header_id = h.id AND x.category = \'NG\') AS abnormal_count
        FROM t_checksheet_header h
        JOIN m_department d ON d.id = h.department_id
        JOIN m_condition c ON c.id = h.condition_id
        JOIN m_user ck ON ck.id = h.checker_id
        JOIN m_shift s ON s.id = h.shift_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY h.tanggal DESC, h.jam ASC, h.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Group rows sharing the same Department + Date + Time into one card.
$groups = [];
foreach ($results as $row) {
    $key = $row['department_id'] . '|' . $row['tanggal'] . '|' . $row['jam'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'tanggal' => $row['tanggal'],
            'jam' => $row['jam'],
            'department_name' => $row['department_name'],
            'items' => [],
            'abnormal_total' => 0,
        ];
    }
    $groups[$key]['items'][] = $row;
    $groups[$key]['abnormal_total'] += (int) $row['abnormal_count'];
}

foreach ($groups as &$g) {
    $checkers = array_unique(array_column($g['items'], 'checker_name'));
    $shifts = array_unique(array_column($g['items'], 'shift_name'));
    $g['uniform_checker'] = count($checkers) === 1 ? $checkers[0] : null;
    $g['uniform_shift'] = count($shifts) === 1 ? $shifts[0] : null;
}
unset($g);

// ---- Missing checks: working days in this month with zero submitted
// checksheets for a condition. Only days up to today are considered — a
// future date hasn't been missed yet.
$today = date('Y-m-d');
$capEnd = min($monthEnd, $today);
$missingByCondition = [];

if ($selected_department_id && $capEnd >= $monthStart) {
    $workingDays = get_working_days($pdo, $monthStart, $capEnd);

    $presentStmt = $pdo->prepare(
        "SELECT condition_id, DATE(tanggal) AS d FROM t_checksheet_header
         WHERE department_id = ? AND tanggal BETWEEN ? AND ? AND status = 'submitted'
         GROUP BY condition_id, DATE(tanggal)"
    );
    $presentStmt->execute([$selected_department_id, $monthStart, $capEnd]);
    $present = [];
    foreach ($presentStmt->fetchAll() as $p) { $present[$p['condition_id']][$p['d']] = true; }

    foreach ($conditions as $c) {
        if ($selected_condition_id && $selected_condition_id != $c['id']) continue;
        $missing = array_values(array_filter($workingDays, fn($d) => empty($present[$c['id']][$d])));
        if ($missing) $missingByCondition[] = ['id' => $c['id'], 'name' => $c['name'], 'dates' => $missing];
    }
}

$backQuery = $_SERVER['QUERY_STRING'] ?? '';

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'painting_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Painting · Search & view submitted checksheet results';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <input type="hidden" name="department_id" value="<?= $selected_department_id ?>">
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
            <label>Condition</label>
            <select name="condition_id">
                <option value="0">All Conditions</option>
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_condition_id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<?php if ($missingByCondition): ?>
<div class="missing-banner">
    <div class="missing-banner-title">&#9888; Missing checks this month</div>
    <?php foreach ($missingByCondition as $mc): ?>
        <div class="missing-banner-row">
            <span class="missing-banner-cond"><?= htmlspecialchars($mc['name']) ?></span>
            <span class="missing-banner-dates"><?= format_missing_dates($mc['dates']) ?></span>
            <?php if (in_array(date('Y-m-d'), $mc['dates'], true)): ?>
                <a class="missing-banner-fill-btn" href="painting_list.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $mc['id'] ?>">Fill today</a>
            <?php elseif (in_array(date('Y-m-d', strtotime('-1 day')), $mc['dates'], true)): ?>
                <a class="missing-banner-fill-btn" href="painting_list.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $mc['id'] ?>&tanggal=<?= date('Y-m-d', strtotime('-1 day')) ?>">Fill yesterday</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="cs-card-list">
    <?php foreach ($groups as $g): ?>
    <div class="cs-card cs-card-group">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= htmlspecialchars(date('d', strtotime($g['tanggal']))) ?></div>
            <div class="cs-card-month"><?= htmlspecialchars(date('M', strtotime($g['tanggal']))) ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-head-row">
                <div>
                    <div class="cs-card-title"><?= htmlspecialchars(substr($g['jam'], 0, 5)) ?></div>
                    <div class="cs-card-meta">
                        <?= count($g['items']) ?> condition<?= count($g['items']) > 1 ? 's' : '' ?> checked
                        <?php if ($g['uniform_checker']): ?> &middot; Checked by <?= htmlspecialchars($g['uniform_checker']) ?><?php endif; ?>
                        <?php if ($g['uniform_shift']): ?> - <?= htmlspecialchars($g['uniform_shift']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($g['abnormal_total'] > 0): ?>
                    <span class="cs-summary-badge cs-summary-badge-abnormal"><?= $g['abnormal_total'] ?> abnormal</span>
                <?php else: ?>
                    <span class="cs-summary-badge cs-summary-badge-ok">All normal</span>
                <?php endif; ?>
            </div>
            <div class="cs-condition-list">
                <?php foreach ($g['items'] as $row): ?>
                    <div class="cs-condition-row">
                        <span class="cs-condition-name"><?= htmlspecialchars($row['condition_name']) ?></span>
                        <span class="cs-condition-meta">
                            <?= (int) $row['item_count'] ?> item<?= (int) $row['item_count'] !== 1 ? 's' : '' ?>
                            <?php if (!$g['uniform_checker'] || !$g['uniform_shift']): ?>
                                - <?= htmlspecialchars($row['checker_name']) ?> - <?= htmlspecialchars($row['shift_name']) ?>
                            <?php endif; ?>
                        </span>
                        <?php if ((int) $row['abnormal_count'] > 0): ?>
                            <span class="cs-row-badge cs-row-badge-abnormal"><?= (int) $row['abnormal_count'] ?> abnormal</span>
                        <?php else: ?>
                            <span class="cs-row-badge cs-row-badge-ok">Normal</span>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                            <a class="cs-view-btn-sm" href="painting_list.php?edit_id=<?= $row['id'] ?>">Edit</a>
                        <?php else: ?>
                        <button type="button" class="cs-request-edit-btn" data-edit-type="painting" data-edit-id="<?= $row['id'] ?>" data-edit-label="<?= htmlspecialchars($row['condition_name'] . ' - ' . date('d M Y', strtotime($g['tanggal']))) ?>">Request Edit</button>
                        <?php endif; ?>
                        <a href="view_checksheet_detail.php?id=<?= $row['id'] ?>&back=<?= urlencode($backQuery) ?>" class="cs-view-btn-sm">View &rarr;</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$groups): ?><div class="empty-state">No checksheets found for this date range / filter.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

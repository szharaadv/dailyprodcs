<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$stmt = $pdo->prepare('SELECT * FROM m_jig WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$department_id]);
$jigs = $stmt->fetchAll();

$selected_jig_id = (int)($_GET['jig_id'] ?? 0);
$year = (int)($_GET['year'] ?? date('Y'));

$where = ['j.department_id = ?', 'h.year = ?'];
$params = [$department_id, $year];
if ($selected_jig_id) {
    $where[] = 'h.jig_id = ?';
    $params[] = $selected_jig_id;
}

$sql = "SELECT h.*, j.name AS jig_name, chk.name AS checker_name,
               (SELECT COUNT(DISTINCT d.day) FROM t_jig_detail d WHERE d.header_id = h.id) AS filled_days,
               (SELECT COUNT(*) FROM t_jig_detail d WHERE d.header_id = h.id AND d.result = 'NG') AS ng_count
        FROM t_jigheader h
        JOIN m_jig j ON j.id = h.jig_id
        LEFT JOIN m_user chk ON chk.id = h.checker_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY j.sort_order, j.id, h.month';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$base_url = '';
$active_nav = 'view-checksheets';
$section_route = 'sub_assembly_list.php';
$page_title = 'View Checksheets';
$page_subtitle = 'Search & view Sub Assembly jig inspection sheets';
require __DIR__ . '/includes/app_top.php';
?>

<form method="get" class="admin-form filter-bar">
    <div class="form-grid">
        <div class="form-row">
            <label>Jig</label>
            <select name="jig_id">
                <option value="0">All Jigs</option>
                <?php foreach ($jigs as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= $j['id'] == $selected_jig_id ? 'selected' : '' ?>><?= htmlspecialchars($j['name']) ?></option>
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
    <?php $daysInThisMonth = (int)date('t', mktime(0, 0, 0, $row['month'], 1, $row['year'])); ?>
    <div class="cs-card">
        <div class="cs-card-date">
            <div class="cs-card-day"><?= substr($monthNames[$row['month']], 0, 3) ?></div>
            <div class="cs-card-month"><?= (int)$row['year'] ?></div>
        </div>
        <div class="cs-card-body">
            <div class="cs-card-title"><?= htmlspecialchars($row['jig_name']) ?></div>
            <div class="cs-card-meta">
                <?= (int)$row['filled_days'] ?> of <?= $daysInThisMonth ?> days filled
                <?php if ($row['ng_count'] > 0): ?> &middot; <span style="color:#9b3b32;font-weight:700;"><?= (int)$row['ng_count'] ?> NG</span><?php endif; ?>
                <?php if ($row['checker_name']): ?> &middot; Checked by <?= htmlspecialchars($row['checker_name']) ?><?php endif; ?>
            </div>
        </div>
        <span class="cs-status <?= $row['ng_count'] > 0 ? 'cs-status-draft' : 'cs-status-submitted' ?>"><?= $row['ng_count'] > 0 ? 'Has NG' : 'All OK' ?></span>
        <?php if (is_admin()): ?>
            <a class="cs-view-btn-sm" href="sub_assembly_list.php?edit_id=<?= $row['id'] ?>">Edit</a>
        <?php else: ?>
        <button type="button" class="cs-request-edit-btn" data-edit-type="jig" data-edit-id="<?= $row['id'] ?>" data-edit-label="<?= htmlspecialchars($row['jig_name'] . ' - ' . $monthNames[$row['month']] . ' ' . $row['year']) ?>">Request Edit</button>
        <?php endif; ?>
        <a href="sub_assembly_list.php?department_id=<?= $department_id ?>&jig_id=<?= $row['jig_id'] ?>&month=<?= $row['month'] ?>&year=<?= $row['year'] ?>" class="cs-view-btn">Open &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">No jig check sheets found for <?= $year ?>.</div><?php endif; ?>
</div>

<script src="assets/js/filter-autosubmit.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

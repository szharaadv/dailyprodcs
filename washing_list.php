<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

if (isset($_GET['department_id'])) {
    $_SESSION['department_id'] = (int)$_GET['department_id'];
}

$department_id = $_SESSION['department_id'] ?? null;

$department = null;
if ($department_id) {
    $stmt = $pdo->prepare("SELECT * FROM m_department WHERE id = ? AND is_active = 1 AND form_type = 'assembly'");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'washing_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'washing_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'washing_list.php';
$page_title = 'Production Check Sheet - Washing Machine Liquid Monitoring';
$page_subtitle = $department['name'] . ' · Washing Machine Liquid Monitoring';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'washing_list.php');
require __DIR__ . '/includes/app_top.php';

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range((int)date('Y') - 1, (int)date('Y') + 1);
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Month</label>
            <select id="f_month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Year</label>
            <select id="f_year">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>&nbsp;</label>
            <div class="month-nav">
                <button type="button" class="month-nav-btn" id="btn-prev-month">&larr; Prev</button>
                <button type="button" class="month-nav-btn" id="btn-next-month">Next &rarr;</button>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="washing-table" class="washing-table">
            <thead>
                <tr>
                    <th class="washing-date-col">Date</th>
                    <th>Ganti Air (Kuras)<br><span class="washing-subhead">YM08 = min 30 ltr</span></th>
                    <th>Temperatur Air (&deg;C)<br><span class="washing-subhead">std. 50 - 60 &deg;C</span></th>
                    <th>Penambahan Gildaon YM08<br><span class="washing-subhead">0.5 poin = 4 ltr</span></th>
                    <th>Total Acid<br><span class="washing-subhead">(3 - 4 point)</span></th>
                    <th>Checker<br><span class="washing-subhead">Foreman</span></th>
                    <th>Control<br><span class="washing-subhead">Supervisor</span></th>
                </tr>
            </thead>
            <tbody id="washing-tbody">
                <tr><td colspan="7" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
    <p class="import-hint">Type a value and click away to save — no submit button needed.</p>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const PEOPLE = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $people)) ?>;
</script>
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/washing.js?v=<?= @filemtime(__DIR__ . '/assets/js/washing.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

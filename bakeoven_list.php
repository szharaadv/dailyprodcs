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
    $stmt = $pdo->prepare("SELECT * FROM m_department WHERE id = ? AND is_active = 1 AND form_type = 'checklist'");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'bakeoven_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'bakeoven_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_bakeoven WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$ovens = $stmt->fetchAll();

$selected_oven_id = (int)($_GET['bakeoven_id'] ?? ($ovens[0]['id'] ?? 0));
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

$selectedOven = null;
foreach ($ovens as $o) {
    if ($o['id'] == $selected_oven_id) { $selectedOven = $o; break; }
}

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'bakeoven_list.php';
$page_title = 'Check Sheet - Bake Oven Temperature';
$page_subtitle = $department['name'] . ' · Monthly oven temperature record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'bakeoven_list.php');
require __DIR__ . '/includes/app_top.php';

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range((int)date('Y') - 1, (int)date('Y') + 1);
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Oven</label>
            <select id="f_oven">
                <?php foreach ($ovens as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $o['id'] == $selected_oven_id ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                <?php endforeach; ?>
                <?php if (!$ovens): ?><option value="">No ovens set up yet</option><?php endif; ?>
            </select>
        </div>
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
        <div class="field-block">
            <label>Standard</label>
            <div class="static-value" id="f_standard"><?= $selectedOven ? htmlspecialchars($selectedOven['standard_min'] . '°C ~ ' . $selectedOven['standard_max'] . '°C') : '-' ?></div>
        </div>
    </div>

    <div class="table-wrap">
        <table id="bakeoven-table" class="bakeoven-table">
            <thead>
                <tr id="bakeoven-table-head">
                    <th class="bo-corner-cell"><span class="bo-corner-text">Waktu<br>Pengecekan</span></th>
                </tr>
            </thead>
            <tbody id="bakeoven-tbody">
                <tr><td class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bakeoven-footer">
        <div class="field-block">
            <label>Asst. Foreman</label>
            <select id="f_asst_foreman">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <select id="f_foreman">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <select id="f_supervisor">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block" style="flex:1 1 260px;">
            <label>Keterangan</label>
            <textarea id="f_notes" rows="2"></textarea>
        </div>
    </div>
    <p class="import-hint">Type a temperature and click away to save — no submit button needed. Paraf picks who checked that day.</p>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const STANDARDS = <?= json_encode(array_column($ovens, null, 'id')) ?>;
    const PEOPLE = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $people)) ?>;
</script>
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/bakeoven.js?v=<?= @filemtime(__DIR__ . '/assets/js/bakeoven.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

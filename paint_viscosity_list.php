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

$_SESSION['section_route'] = 'paint_viscosity_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'paint_viscosity_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'paint_viscosity_list.php';
$page_title = 'Check Sheet - Paint Viscosity';
$page_subtitle = $department['name'] . ' · F-PS-08 · Monthly paint viscosity record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'paint_viscosity_list.php');
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
        <table id="viscosity-table" class="bakeoven-table viscosity-table">
            <thead>
                <tr id="viscosity-table-head">
                    <th class="visc-process-col">Process Name</th>
                    <th class="visc-product-col">Product Name</th>
                    <th class="visc-maker-col">Maker/Brand</th>
                    <th class="visc-standard-col">Viscosity Standard</th>
                </tr>
            </thead>
            <tbody id="viscosity-tbody">
                <tr><td class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bakeoven-footer">
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
            <label>Catatan</label>
            <textarea id="f_notes" rows="2"></textarea>
        </div>
    </div>
    <p class="import-hint">Type a viscosity value and click away to save — no submit button needed.</p>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const TODAY = <?= json_encode(date('Y-m-d')) ?>;
</script>
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/paint_viscosity.js?v=<?= @filemtime(__DIR__ . '/assets/js/paint_viscosity.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

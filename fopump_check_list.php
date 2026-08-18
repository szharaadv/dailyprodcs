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

$_SESSION['section_route'] = 'fopump_check_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'fopump_check_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_fopump_check_model WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$models = $stmt->fetchAll();

$selected_model_id = $_GET['model_id'] ?? ($models[0]['id'] ?? null);
$selected_model_name = '';
foreach ($models as $m) {
    if ($m['id'] == $selected_model_id) { $selected_model_name = $m['name']; break; }
}

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'fopump_check_list.php';
$page_title = 'FO Pump Assy Daily Check Sheet';
$page_subtitle = $department['name'] . ' · F-FIP-01 quality checklist · one ongoing record per model';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fopump_check_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-section-header">
        <span>Check Sheet Details</span>
        <button type="button" class="form-toggle-btn" id="btn-toggle-details">Hide &#9650;</button>
    </div>
    <div class="form-grid-top" id="form-grid-top">
        <div class="field-block">
            <label>Model</label>
            <input type="text" id="f_model" placeholder="Search model..." value="<?= htmlspecialchars($selected_model_name) ?>">
        </div>
        <div class="field-block">
            <label>FOP Code</label>
            <div class="static-value" id="f_fop_code">-</div>
        </div>
        <div class="field-block">
            <label>Part No.</label>
            <div class="static-value" id="f_part_no">-</div>
        </div>
        <div class="field-block">
            <label>Prod. Date Code</label>
            <input type="text" id="f_prod_date_code" placeholder="e.g. 2024/10">
        </div>
        <div class="field-block">
            <label>Checker</label>
            <select id="f_checker">
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
    </div>

    <div class="fopump-check-toolbar">
        <button type="button" class="btn btn-secondary" id="btn-add-sample">+ Add Sample</button>
        <span class="import-hint">Add one column per unit sampled. Submitting updates this model's record in place — there's no date to track.</span>
    </div>

    <div class="table-wrap">
        <table id="fopump-check-table" class="fopump-check-table">
            <thead>
                <tr id="fopump-check-head-row">
                    <th class="fopump-check-item-col">Checking Item</th>
                    <th class="fopump-check-std-col">Standard</th>
                </tr>
            </thead>
            <tbody id="fopump-check-tbody">
                <tr><td colspan="2" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <div class="progress-label" id="fopump-check-status-label"></div>
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <button type="button" class="btn btn-submit" id="btn-submit">Submit</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const MODELS = <?= json_encode(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $models)) ?>;
</script>
<script src="assets/js/combo-select.js"></script>
<script src="assets/js/fopump_check.js?v=<?= @filemtime(__DIR__ . '/assets/js/fopump_check.js') ?: 1 ?>"></script>
<script>
(function () {
    const panel = document.getElementById('form-grid-top');
    const btn = document.getElementById('btn-toggle-details');
    btn.addEventListener('click', () => {
        const hidden = panel.classList.toggle('is-hidden');
        btn.innerHTML = hidden ? 'Show &#9660;' : 'Hide &#9650;';
    });
})();
</script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

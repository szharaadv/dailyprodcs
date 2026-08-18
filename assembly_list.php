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

$_SESSION['section_route'] = 'assembly_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'assembly_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$checkers = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_assy_model WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$models = $stmt->fetchAll();

$draft = null;
$draft_values = [];
$draft_id = (int)($_GET['draft_id'] ?? 0);
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_assy_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();

    if ($draft) {
        $stmt = $pdo->prepare('SELECT checklist_item_id, actual_result, consumable_item FROM t_assy_detail WHERE header_id = ?');
        $stmt->execute([$draft_id]);
        foreach ($stmt->fetchAll() as $d) {
            $draft_values[$d['checklist_item_id']] = ['actual' => $d['actual_result'], 'consumable' => $d['consumable_item']];
        }
    }
}

$prefill_tanggal = $_GET['tanggal'] ?? null;

$selected_model_id = $_GET['model_id'] ?? ($draft['model_id'] ?? ($models[0]['id'] ?? null));
$selected_model_name = '';
foreach ($models as $m) {
    if ($m['id'] == $selected_model_id) { $selected_model_name = $m['name']; break; }
}

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'assembly_list.php';
$page_title = 'Production Check Sheet - Daily Torque';
$page_subtitle = $department['name'] . ' · Fill daily production record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'assembly_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly
                   value="<?= htmlspecialchars($draft['tanggal'] ?? ((preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefill_tanggal ?? '') ? $prefill_tanggal : null) ?? date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>">
        </div>

        <div class="field-block">
            <label>Model</label>
            <input type="text" id="f_model" placeholder="Search model..." value="<?= htmlspecialchars($selected_model_name) ?>">
        </div>

        <div class="field-block">
            <label>Checker</label>
            <select id="f_checker">
                <?php foreach ($checkers as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $draft && $ch['id'] == $draft['checker_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-block">
            <label>Mark Crank Shaft</label>
            <input type="text" id="f_mark_crank_shaft" value="<?= htmlspecialchars($draft['mark_crank_shaft'] ?? '') ?>">
        </div>

        <div class="field-block">
            <label>Mark Con-rod</label>
            <input type="text" id="f_mark_conrod" value="<?= htmlspecialchars($draft['mark_conrod'] ?? '') ?>">
        </div>

        <div class="field-block">
            <label>Mark FO Pump</label>
            <input type="text" id="f_mark_fo_pump" value="<?= htmlspecialchars($draft['mark_fo_pump'] ?? '') ?>">
        </div>

        <div class="field-block">
            <label>No Cyl Block</label>
            <input type="text" id="f_no_cyl_block" value="<?= htmlspecialchars($draft['no_cyl_block'] ?? '') ?>">
        </div>

        <div class="field-block">
            <label>No Engine</label>
            <input type="text" id="f_no_engine" value="<?= htmlspecialchars($draft['no_engine'] ?? '') ?>">
        </div>

        <div class="field-block">
            <label>Detail Model</label>
            <input type="text" id="f_detail_model" value="<?= htmlspecialchars($draft['detail_model'] ?? '') ?>">
        </div>
    </div>

    <div class="table-wrap">
        <table id="assy-table" class="assy-table">
            <thead>
                <tr>
                    <th>Checking Item</th>
                    <th>Standard</th>
                    <th>Standard Min.</th>
                    <th>Standard Max.</th>
                    <th>Actual Result</th>
                    <th>Consumable Item</th>
                </tr>
            </thead>
            <tbody id="assy-tbody">
                <tr><td colspan="6" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <button type="button" class="btn btn-submit" id="btn-submit">Submit</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const DRAFT_ID = <?= json_encode($draft_id ?: null) ?>;
    const DRAFT_VALUES = <?= json_encode($draft_values, JSON_FORCE_OBJECT) ?>;
    const MODELS = <?= json_encode(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $models)) ?>;
</script>
<script src="assets/js/combo-select.js"></script>
<script src="assets/js/assy.js?v=<?= @filemtime(__DIR__ . '/assets/js/assy.js') ?: 1 ?>"></script>
<script src="assets/js/holiday-calendar.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

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

$_SESSION['section_route'] = 'fopump_test_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'fopump_test_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_fopump_test_model WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$models = $stmt->fetchAll();

$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft = null;
$draft_rows = [];
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_fopump_test_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();

    if ($draft) {
        $stmt = $pdo->prepare('SELECT row_no, rpm, cc_sec, shim FROM t_fopump_test_row WHERE header_id = ? ORDER BY sort_order, id');
        $stmt->execute([$draft_id]);
        $draft_rows = $stmt->fetchAll();
    }
}

$selected_model_id = $_GET['model_id'] ?? ($draft['model_id'] ?? ($models[0]['id'] ?? null));
$selected_model_name = '';
foreach ($models as $m) {
    if ($m['id'] == $selected_model_id) { $selected_model_name = $m['name']; break; }
}
$prefill_tanggal = $_GET['tanggal'] ?? null;

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'fopump_test_list.php';
$page_title = 'FO Pump Test Record';
$page_subtitle = $department['name'] . ' · F-FIP-02 FOP tester data';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fopump_test_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly value="<?= htmlspecialchars($draft['tanggal'] ?? ((preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefill_tanggal ?? '') ? $prefill_tanggal : null) ?? date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="field-block">
            <label>Model</label>
            <input type="text" id="f_model" placeholder="Search model..." value="<?= htmlspecialchars($selected_model_name) ?>">
        </div>
        <div class="field-block">
            <label>Destination</label>
            <select id="f_destination">
                <option value="local" <?= ($draft['destination'] ?? 'local') === 'local' ? 'selected' : '' ?>>Local</option>
                <option value="export" <?= ($draft['destination'] ?? '') === 'export' ? 'selected' : '' ?>>Export</option>
            </select>
        </div>
        <div class="field-block"><label>FOP Code</label><div class="static-value" id="f_fop_code">-</div></div>
        <div class="field-block"><label>Standard (cc/sec)</label><div class="static-value" id="f_standard_cc_sec">-</div></div>
        <div class="field-block"><label>Rpm</label><div class="static-value" id="f_rpm_std">-</div></div>
        <div class="field-block"><label>Master Test</label><div class="static-value" id="f_master_test">-</div></div>
        <div class="field-block">
            <label>Oil Pressure</label>
            <input type="text" id="f_oil_pressure" value="<?= htmlspecialchars($draft['oil_pressure'] ?? '') ?>" placeholder="e.g. 5-7">
        </div>
        <div class="field-block">
            <label>Oil Temp.</label>
            <input type="text" id="f_oil_temp" value="<?= htmlspecialchars($draft['oil_temp'] ?? '') ?>">
        </div>
        <div class="field-block">
            <label>Room Temp.</label>
            <input type="text" id="f_room_temp" value="<?= htmlspecialchars($draft['room_temp'] ?? '') ?>" placeholder="e.g. 28-30° C">
        </div>
        <div class="field-block">
            <label>Start Test Time</label>
            <input type="time" id="f_start_test_time" value="<?= htmlspecialchars($draft['start_test_time'] ?? '') ?>">
        </div>
        <div class="field-block">
            <label>Checker</label>
            <select id="f_checker">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $draft && $p['id'] == $draft['checker_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Foreman</label>
            <select id="f_foreman">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $draft && $p['id'] == $draft['foreman_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Supervisor</label>
            <select id="f_supervisor">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $draft && $p['id'] == $draft['supervisor_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="fopump-check-toolbar">
        <button type="button" class="btn btn-secondary" id="btn-add-row">+ Add Row</button>
        <span class="import-hint">One row per unit tested; new rows pre-fill from this model's target spec.</span>
    </div>

    <div class="table-wrap">
        <table id="fopump-test-table" class="fopump-check-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Rpm</th>
                    <th>cc / sec</th>
                    <th>Shim</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="fopump-test-tbody">
                <tr><td colspan="5" class="empty">Loading data...</td></tr>
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
    const DRAFT_ROWS = <?= json_encode($draft_rows) ?>;
    const MODELS = <?= json_encode(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $models)) ?>;
</script>
<script src="assets/js/combo-select.js"></script>
<script src="assets/js/fopump_test.js?v=<?= @filemtime(__DIR__ . '/assets/js/fopump_test.js') ?: 1 ?>"></script>
<script src="assets/js/holiday-calendar.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

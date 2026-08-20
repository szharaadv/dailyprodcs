<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
$editing_tanggal = null;
if ($edit_id && has_active_unlock($pdo, 'fopump', $edit_id)) {
    $stmt = $pdo->prepare('SELECT department_id, tanggal FROM t_fopump_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $_SESSION['department_id'] = (int)$editRow['department_id'];
        $editing_unlocked = true;
        $editing_tanggal = $editRow['tanggal'];
    }
}

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

$_SESSION['section_route'] = 'fopump_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'fopump_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$models = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft = null;
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_fopump_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
}

$selected_date = $editing_unlocked ? $editing_tanggal : date('Y-m-d');

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'fopump_list.php';
$page_title = 'Daily Report - FO Pump Assy';
$page_subtitle = $department['name'] . ' · FO Pump daily production record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fopump_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <?php if ($editing_unlocked): ?>
    <div class="alert alert-ok">Editing an approved past record (<?= htmlspecialchars($selected_date) ?>). Changes save back to that same date.</div>
    <?php endif; ?>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly value="<?= htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="field-block">
            <label>Employee</label>
            <input type="number" id="f_employee" min="0" placeholder="Count">
        </div>
        <div class="field-block">
            <label>Working Time (minutes)</label>
            <input type="number" id="f_working_time" min="0" placeholder="e.g. 480">
        </div>
        <div class="field-block">
            <label>Shift</label>
            <input type="text" id="f_shift" placeholder="e.g. SHIFT 1, LS 1">
        </div>
        <div class="field-block">
            <label>Operator</label>
            <select id="f_operator">
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

    <div class="table-wrap">
        <table id="fopump-table" class="fopump-table">
            <colgroup>
                <col class="fopump-col-no">
                <col class="fopump-col-model"><col class="fopump-col-qty">
                <col class="fopump-col-model"><col class="fopump-col-qty">
                <col class="fopump-col-model"><col class="fopump-col-qty">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">NO</th>
                    <th colspan="2">FO Pump Production</th>
                    <th colspan="2">To Assembly Line</th>
                    <th colspan="2">To Sparepart PTC</th>
                </tr>
                <tr>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                    <th>Model</th><th>Quantity</th>
                </tr>
            </thead>
            <tbody id="fopump-tbody"></tbody>
            <tfoot id="fopump-tfoot"></tfoot>
        </table>
    </div>

    <div class="actions">
        <div class="progress-label" id="fopump-status-label"></div>
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <button type="button" class="btn btn-submit" id="btn-submit">Submit</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const DRAFT_ID = <?= json_encode($draft_id ?: null) ?>;
    const MODEL_NAMES = <?= json_encode($models) ?>;
</script>
<script src="assets/js/combo-select.js"></script>
<script src="assets/js/fopump.js?v=<?= @filemtime(__DIR__ . '/assets/js/fopump.js') ?: 1 ?>"></script>
<script src="assets/js/holiday-calendar.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

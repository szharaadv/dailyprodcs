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

$_SESSION['section_route'] = 'fopump_reject_list.php';

$models = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft = null;
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_fopump_reject_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
}

$selected_month = (int)($draft['month'] ?? ($_GET['month'] ?? date('n')));
$selected_year = (int)($draft['year'] ?? ($_GET['year'] ?? date('Y')));

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'fopump_reject_list.php';
$page_title = 'FO Pump Daily Reject';
$page_subtitle = $department['name'] . ' · F-FIP-04 monthly reject log';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'fopump_reject_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <div class="form-grid-top">
        <div class="field-block">
            <label>Month</label>
            <select id="f_month">
                <?php
                $monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach ($monthNames as $i => $mName): ?>
                    <option value="<?= $i + 1 ?>" <?= ($i + 1) == $selected_month ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Year</label>
            <select id="f_year">
                <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
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
            <label>Target</label>
            <input type="number" id="f_target" min="0" value="<?= htmlspecialchars($draft['target'] ?? '') ?>">
        </div>
    </div>

    <div class="fopump-check-toolbar">
        <button type="button" class="btn btn-secondary" id="btn-add-row">+ Add Row</button>
        <span class="import-hint">Add one row per reject occurrence during the month.</span>
    </div>

    <div class="table-wrap">
        <table id="fopump-reject-table" class="fopump-check-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Model</th>
                    <th>Quantity</th>
                    <th>Remarks</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="fopump-reject-tbody">
                <tr><td colspan="5" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <div class="progress-label" id="fopump-reject-status-label"></div>
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
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/fopump_reject.js?v=<?= @filemtime(__DIR__ . '/assets/js/fopump_reject.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

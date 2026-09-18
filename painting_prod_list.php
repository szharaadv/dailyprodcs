<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
$editing_tanggal = null;
if ($edit_id && has_active_unlock($pdo, 'painting_prod', $edit_id)) {
    $stmt = $pdo->prepare('SELECT department_id, tanggal FROM t_painting_prod_header WHERE id = ?');
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
    $stmt = $pdo->prepare('SELECT * FROM m_department WHERE id = ? AND is_active = 1');
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'painting_prod_list.php';

// Admin can backdate freely (for trials/testing) — the date picker is opened up
// to any past day and the save endpoint honours the chosen date. Everyone else
// stays locked to today (plus the normal catch-up flow).
$is_admin_user = is_admin();

// Pekerja ("Checked By") dropdown: people assigned to THIS section (Management
// → Users → "Visible on check sheets"), so each department keeps its own list.
$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'painting_prod_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$workers = $stmt->fetchAll();

$shifts = $pdo->query('SELECT * FROM m_shift WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$models = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft = null;
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_painting_prod_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
}

// Catch-up on a missed day: yesterday can be filled in (via a "Fill yesterday"
// link) but only if the department genuinely has no submitted report for that
// date yet (a real miss, not a backdate).
$catchup_tanggal = null;
if (!$editing_unlocked) {
    $requestedTanggal = $_GET['tanggal'] ?? null;
    if ($requestedTanggal === date('Y-m-d', strtotime('-1 day'))) {
        $stmt = $pdo->prepare("SELECT 1 FROM t_painting_prod_header WHERE department_id = ? AND tanggal = ? AND status = 'submitted'");
        $stmt->execute([$department['id'], $requestedTanggal]);
        if (!$stmt->fetchColumn()) {
            $catchup_tanggal = $requestedTanggal;
        }
    }

    // Approved fill request: a missed day older than yesterday, once Admin-approved.
    $fillDate = $_GET['fill_date'] ?? null;
    if (!$catchup_tanggal && $fillDate && $fillDate < date('Y-m-d')
        && has_active_fill_unlock($pdo, 'painting_prod', (int)$department['id'], null, $fillDate)) {
        $stmt = $pdo->prepare("SELECT 1 FROM t_painting_prod_header WHERE department_id = ? AND tanggal = ? AND status = 'submitted'");
        $stmt->execute([$department['id'], $fillDate]);
        if (!$stmt->fetchColumn()) {
            $catchup_tanggal = $fillDate;
        }
    }
}

// Continuing a draft must open on the draft's OWN date, not today.
$selected_date = $editing_unlocked ? $editing_tanggal
    : ($draft ? $draft['tanggal']
    : ($catchup_tanggal ?: date('Y-m-d')));

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'painting_prod_list.php';
$page_title = 'Daily Report - Painting';
$page_subtitle = $department['name'] . ' · Painting daily production record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'painting_prod_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <?php if ($editing_unlocked): ?>
    <div class="alert alert-ok">Editing a past record (<?= htmlspecialchars($selected_date) ?>). Changes save back to that same date.</div>
    <?php elseif ($catchup_tanggal): ?>
    <div class="alert alert-ok">Catching up on a missed day (<?= htmlspecialchars($catchup_tanggal) ?>).  </div>
    <?php elseif ($is_admin_user): ?>
    <div class="alert alert-ok">Admin mode: you can pick any past date to backdate this report (for testing).</div>
    <?php endif; ?>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Day</label>
            <input type="text" id="f_hari" readonly value="">
        </div>
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly value="<?= htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" <?php if ($is_admin_user): ?>data-plain<?php else: ?>min="<?= date('Y-m-d') ?>"<?php endif; ?>>
        </div>
        <div class="field-block">
            <label>Checked By</label>
            <select id="f_pekerja">
                <option value="">—</option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field-block">
            <label>Shift</label>
            <select id="f_shift">
                <option value="">—</option>
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="fopump-check-toolbar">
        <button type="button" class="btn btn-secondary" id="btn-add-row">+ Add Row</button>
    </div>

    <!-- data-optional: this is a production tally, not a checklist — rows and
         whole columns are often legitimately blank, so the table is exempt from
         the "all fields required" submit guard. -->
    <div class="table-wrap" data-optional>
        <table id="pprod-table" class="fopump-table pprod-table">
            <colgroup>
                <col class="fopump-col-no">
                <col class="pprod-col-model">
                <col class="pprod-col-qty"><col class="pprod-col-qty"><col class="pprod-col-qty">
                <col class="pprod-col-qty"><col class="pprod-col-qty">
                <col class="pprod-col-note">
            </colgroup>
            <thead>
                <tr>
                    <th>NO</th>
                    <th>MODEL</th>
                    <th>CB</th><th>FOT</th><th>FW</th><th>PART</th><th>OTHERS</th>
                    <th>REMARKS</th>
                </tr>
            </thead>
            <tbody id="pprod-tbody"></tbody>
            <tfoot id="pprod-tfoot"></tfoot>
        </table>
    </div>

    <div class="actions">
        <div class="progress-label" id="pprod-status-label"></div>
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
<script src="assets/js/painting_prod.js?v=<?= @filemtime(__DIR__ . '/assets/js/painting_prod.js') ?: 1 ?>"></script>
<script src="assets/js/holiday-calendar.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

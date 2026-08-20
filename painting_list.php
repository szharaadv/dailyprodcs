<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

// An Admin-approved edit request lets us reopen one specific already-submitted
// record outside the normal "today only" window — see includes/edit_requests.php.
$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
if ($edit_id && has_active_unlock($pdo, 'painting', $edit_id)) {
    $stmt = $pdo->prepare('SELECT department_id FROM t_checksheet_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $editRowDept = $stmt->fetchColumn();
    if ($editRowDept) {
        $_SESSION['department_id'] = (int)$editRowDept;
        $editing_unlocked = true;
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

$_SESSION['section_route'] = 'painting_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'painting_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$checkers = $stmt->fetchAll();

$shifts = $pdo->query('SELECT * FROM m_shift WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$conditions = $stmt->fetchAll();

// Catch-up on a missed day: yesterday can be filled in (via a "Fill
// yesterday" link from View Checksheets' missing banner) but ONLY if that
// exact condition genuinely has no submitted record for it yet — this is
// for catching a miss, never for backdating over something already there.
$catchup_tanggal = null;
$requestedTanggal = $_GET['tanggal'] ?? null;
$requestedConditionId = (int)($_GET['condition_id'] ?? 0);
if ($requestedTanggal === date('Y-m-d', strtotime('-1 day')) && $requestedConditionId) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM t_checksheet_header WHERE department_id = ? AND condition_id = ? AND tanggal = ? AND status = 'submitted'"
    );
    $stmt->execute([$department['id'], $requestedConditionId, $requestedTanggal]);
    if (!$stmt->fetchColumn()) {
        $catchup_tanggal = $requestedTanggal;
    }
}
$selected_date = $catchup_tanggal ?: date('Y-m-d');

$draft = null;
$draft_values = [];
$draft_id = (int)($_GET['draft_id'] ?? 0);
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_checksheet_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
} elseif ($editing_unlocked) {
    $stmt = $pdo->prepare('SELECT * FROM t_checksheet_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $draft = $stmt->fetch();
    $draft_id = $edit_id;
}

if ($draft) {
    $stmt = $pdo->prepare('SELECT checklist_item_id, actual_result, category FROM t_checksheet_detail WHERE header_id = ?');
    $stmt->execute([$draft_id]);
    foreach ($stmt->fetchAll() as $d) {
        $draft_values[$d['checklist_item_id']] = ['actual' => $d['actual_result'], 'category' => $d['category']];
    }
}

$selected_condition_id = $_GET['condition_id'] ?? ($draft['condition_id'] ?? ($conditions[0]['id'] ?? null));

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'painting_list.php';
$page_title = 'Production Check Sheet - Daily Painting';
$page_subtitle = $department['name'] . ' · Fill daily production record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'painting_list.php');
require __DIR__ . '/includes/app_top.php';
?>

<div class="checksheet-card">
    <?php if ($editing_unlocked): ?>
    <div class="alert alert-ok">Editing a past record (<?= htmlspecialchars($draft['tanggal']) ?>). Changes save back to that same date.</div>
    <?php elseif ($catchup_tanggal): ?>
    <div class="alert alert-ok">Catching up on a missed day (<?= htmlspecialchars($catchup_tanggal) ?>).  </div>
    <?php endif; ?>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly value="<?= $editing_unlocked ? htmlspecialchars($draft['tanggal']) : htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
        </div>

        <div class="field-block">
            <label>Condition</label>
            <select id="f_condition">
                <?php foreach ($conditions as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_condition_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-block">
            <label>Checked by</label>
            <select id="f_checker">
                <?php foreach ($checkers as $ch): ?>
                    <option value="<?= $ch['id'] ?>" <?= $draft && $ch['id'] == $draft['checker_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-block">
            <label>Time</label>
            <input type="time" id="f_jam" value="<?= htmlspecialchars($draft ? substr($draft['jam'], 0, 5) : '06:30') ?>">
        </div>

        <div class="field-block">
            <label>Shift</label>
            <select id="f_shift">
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $draft && $s['id'] == $draft['shift_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table id="checklist-table" class="checklist-table">
            <thead>
                <tr>
                    <th>Condition</th>
                    <th>Checking Item</th>
                    <th>Checking Method</th>
                    <th>Standard Min.</th>
                    <th>Standard Max.</th>
                    <th>Tank/Tube</th>
                    <th>Unit</th>
                    <th>Actual Result</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody id="checklist-tbody">
                <tr><td colspan="9" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <div class="progress-label" id="progress-label">Loading...</div>
        <div class="progress-bar"><div class="progress-bar-fill" id="progress-bar-fill"></div></div>
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <button type="button" class="btn btn-submit" id="btn-submit">Submit</button>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const DRAFT_ID = <?= json_encode($draft_id ?: null) ?>;
    const DRAFT_VALUES = <?= json_encode($draft_values, JSON_FORCE_OBJECT) ?>;
</script>
<script src="assets/js/holiday-calendar.js?v=<?= @filemtime(__DIR__ . '/assets/js/holiday-calendar.js') ?: 1 ?>"></script>
<script src="assets/js/app.js?v=<?= @filemtime(__DIR__ . '/assets/js/app.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

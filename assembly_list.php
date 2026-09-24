<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
if ($edit_id && has_active_unlock($pdo, 'assy', $edit_id)) {
    $stmt = $pdo->prepare('SELECT department_id FROM t_assy_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $editRowDept = $stmt->fetchColumn();
    if ($editRowDept) {
        $_SESSION['department_id'] = (int)$editRowDept;
        $editing_unlocked = true;
    }
}

// Revision flow: open a submitted record directly on the checksheet to revise
// its values and/or drop (×) checking items. Reached from assy_engine_revision.php.
$revise_id = (int)($_GET['revise_id'] ?? 0);
$revise_mode = false;
if ($revise_id) {
    $stmt = $pdo->prepare('SELECT department_id FROM t_assy_header WHERE id = ?');
    $stmt->execute([$revise_id]);
    $reviseDept = $stmt->fetchColumn();
    if ($reviseDept) {
        $_SESSION['department_id'] = (int)$reviseDept;
        $revise_mode = true;
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
// Checker dropdown lists only people whose job title is Operator.
// …but if this section has nobody titled Operator (e.g. Torque is checked by
// a Foreman/Staff here), fall back to the full roster so it's never empty.
$operators = users_by_title($checkers, 'Operator') ?: $checkers;

$stmt = $pdo->prepare('SELECT * FROM m_assy_model WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$models = $stmt->fetchAll();

// Catch-up on a missed day: yesterday can be filled in (via a "Fill
// yesterday" link) but only if the department genuinely has zero submitted
// records for that date yet — Torque only needs one engine checked per day,
// so any existing submission for that date already counts as "not missed".
$catchup_tanggal = null;
$requestedTanggal = $_GET['tanggal'] ?? null;
if ($requestedTanggal === date('Y-m-d', strtotime('-1 day'))) {
    $stmt = $pdo->prepare("SELECT 1 FROM t_assy_header WHERE department_id = ? AND tanggal = ? AND status = 'submitted'");
    $stmt->execute([$department['id'], $requestedTanggal]);
    if (!$stmt->fetchColumn()) {
        $catchup_tanggal = $requestedTanggal;
    }
}

// Approved fill request: a missed day older than yesterday, once Admin-approved.
$fillDate = $_GET['fill_date'] ?? null;
if (!$catchup_tanggal && $fillDate && $fillDate < date('Y-m-d')
    && has_active_fill_unlock($pdo, 'assy', (int)$department['id'], null, $fillDate)) {
    $stmt = $pdo->prepare("SELECT 1 FROM t_assy_header WHERE department_id = ? AND tanggal = ? AND status = 'submitted'");
    $stmt->execute([$department['id'], $fillDate]);
    if (!$stmt->fetchColumn()) {
        $catchup_tanggal = $fillDate;
    }
}
$selected_date = $catchup_tanggal ?: date('Y-m-d');

$draft = null;
$draft_values = [];
$draft_id = (int)($_GET['draft_id'] ?? 0);
if ($draft_id) {
    $stmt = $pdo->prepare("SELECT * FROM t_assy_header WHERE id = ? AND status = 'draft'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
} elseif ($editing_unlocked) {
    $stmt = $pdo->prepare('SELECT * FROM t_assy_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $draft = $stmt->fetch();
    $draft_id = $edit_id;
} elseif ($revise_mode) {
    $stmt = $pdo->prepare('SELECT * FROM t_assy_header WHERE id = ?');
    $stmt->execute([$revise_id]);
    $draft = $stmt->fetch();
    $draft_id = $revise_id;
}

if ($draft) {
    $stmt = $pdo->prepare('SELECT checklist_item_id, actual_result, consumable_item FROM t_assy_detail WHERE header_id = ?');
    $stmt->execute([$draft_id]);
    foreach ($stmt->fetchAll() as $d) {
        $draft_values[$d['checklist_item_id']] = ['actual' => $d['actual_result'], 'consumable' => $d['consumable_item']];
    }
}

// In revise mode, the checksheet shows exactly the items this record currently
// has (so items dropped in an earlier revision don't come back), each with an ×
// to remove it.
$revise_item_ids = ($revise_mode && $draft) ? array_map('intval', array_keys($draft_values)) : [];

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
    <?php if ($revise_mode): ?>
    <div class="alert alert-ok">Mode Revisi — ubah nilainya bila perlu, dan klik <b>×</b> untuk menghapus item. Saat disimpan, hanya item yang tidak dihapus yang dipertahankan.</div>
    <?php elseif ($editing_unlocked): ?>
    <div class="alert alert-ok">Editing a past record (<?= htmlspecialchars($draft['tanggal']) ?>). Changes save back to that same date.</div>
    <?php elseif ($catchup_tanggal): ?>
    <div class="alert alert-ok">Catching up on a missed day (<?= htmlspecialchars($catchup_tanggal) ?>).  </div>
    <?php endif; ?>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Date</label>
            <input type="text" id="f_tanggal" class="holiday-date-input" readonly
                   value="<?= ($editing_unlocked || $revise_mode) ? htmlspecialchars($draft['tanggal']) : htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
        </div>

        <div class="field-block">
            <label>Model</label>
            <input type="text" id="f_model" placeholder="Search model..." value="<?= htmlspecialchars($selected_model_name) ?>">
        </div>

        <div class="field-block">
            <label>Checker</label>
            <select id="f_checker">
                <?php foreach ($operators as $ch): ?>
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
                    <?php if ($revise_mode): ?><th style="width:44px;"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="assy-tbody">
                <tr><td colspan="<?= $revise_mode ? 7 : 6 ?>" class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <?php if (!$revise_mode): ?>
        <button type="button" class="btn btn-draft" id="btn-draft">Save as Draft</button>
        <?php endif; ?>
        <button type="button" class="btn btn-submit" id="btn-submit"><?= $revise_mode ? 'Simpan Revisi' : 'Submit' ?></button>
    </div>
</div>

<style>
    .assy-table tr.row-blocked { background: #f2f3f5; }
    .assy-table tr.row-blocked td:first-child { color: #9aa0a6; }
    .actual-input.blocked-input {
        background: #e9ebee; color: #9aa0a6; cursor: not-allowed;
        border-style: dashed; text-align: center;
    }
    /* Revision mode: × drop button + dropped-row styling. */
    .assy-remove-cell { text-align: center; }
    .assy-remove-btn {
        border: none; background: #f3d6d3; color: #9b3b32;
        width: 26px; height: 26px; border-radius: 6px;
        font-size: 16px; line-height: 1; cursor: pointer;
    }
    .assy-remove-btn:hover { background: #e9b8b3; }
    .assy-row-removed td { opacity: .45; text-decoration: line-through; }
    .assy-row-removed .assy-remove-btn {
        background: #e3efe4; color: #2f7d34; text-decoration: none;
    }
    .assy-row-removed .assy-remove-cell { text-decoration: none; }
</style>
<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const DRAFT_ID = <?= json_encode($draft_id ?: null) ?>;
    const DRAFT_VALUES = <?= json_encode($draft_values, JSON_FORCE_OBJECT) ?>;
    const MODELS = <?= json_encode(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $models)) ?>;
    const REVISE_MODE = <?= json_encode($revise_mode) ?>;
    const REVISE_ITEM_IDS = <?= json_encode($revise_item_ids) ?>;
</script>
<script src="assets/js/combo-select.js"></script>
<script src="assets/js/assy.js?v=<?= @filemtime(__DIR__ . '/assets/js/assy.js') ?: 1 ?>"></script>
<script src="assets/js/holiday-calendar.js"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

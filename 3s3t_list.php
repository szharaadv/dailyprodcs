<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
$editing_line = null;
$editing_month = null;
$editing_year = null;
if ($edit_id && has_active_unlock($pdo, '3s3t', $edit_id)) {
    $stmt = $pdo->prepare('SELECT department_id, line, month, year FROM t_3s3t_header WHERE id = ?');
    $stmt->execute([$edit_id]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $_SESSION['department_id'] = (int)$editRow['department_id'];
        $editing_unlocked = true;
        $editing_line = $editRow['line'];
        $editing_month = (int)$editRow['month'];
        $editing_year = (int)$editRow['year'];
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

$_SESSION['section_route'] = '3s3t_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = '3s3t_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$selected_line = $editing_unlocked ? $editing_line : trim((string)($_GET['line'] ?? ''));
// Always open on today's month/year — editing is locked to the current
// week anyway, so a stale month/year from a bookmark or browser-back would
// just be dead weight. Prev/Next still lets you browse other months once
// the page is open. (Unless we're reopening a specific approved edit-request
// record — then we jump straight to that record's own month/year instead.)
$selected_month = $editing_unlocked ? $editing_month : (int) date('n');
$selected_year = $editing_unlocked ? $editing_year : (int) date('Y');

$base_url = '';
$active_nav = 'checksheet';
$section_route = '3s3t_list.php';
$page_title = 'Checksheet 3S-3T';
$page_subtitle = $department['name'] . ' · SEIRI-SEITON-SEISO & TEI-ICHI-TEI-RYO-TEI-HIN weekly audit';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, '3s3t_list.php');
require __DIR__ . '/includes/app_top.php';

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range((int)date('Y') - 1, (int)date('Y') + 1);
?>

<div class="checksheet-card">
    <div class="alert alert-ok" id="unlock-banner" style="display:none;">Editing a past record. Every week this month is unlocked for editing while it stays active.</div>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Line</label>
            <input type="text" id="f_line" placeholder="e.g. Line 1" value="<?= htmlspecialchars($selected_line) ?>">
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
            <label>OP (Operator)</label>
            <select id="f_operator">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table id="threes3t-table" class="fopump-check-table threes3t-table">
            <thead>
                <tr>
                    <th class="t3-no-col">No</th>
                    <th class="t3-category-col">Kategori</th>
                    <th class="t3-item-col">Item Pemeriksaan</th>
                    <th class="t3-standard-col">Standar / Kriteria</th>
                    <th>Week 1</th>
                    <th>Week 2</th>
                    <th>Week 3</th>
                    <th>Week 4</th>
                    <th>Week 5</th>
                    <th class="t3-remarks-col">Keterangan / Tindakan Perbaikan</th>
                    <th class="t3-pic-col">PIC</th>
                </tr>
            </thead>
            <tbody id="threes3t-tbody">
                <tr><td colspan="11" class="empty">Type a Line name above to load the checksheet.</td></tr>
            </tbody>
        </table>
    </div>
    <p class="import-hint">Tap OK / NG for each week — it saves right away, no submit button needed.</p>
</div>

<div class="modal-overlay t3-remarks-modal" id="t3-remarks-modal" style="display:none;">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3 id="t3-remarks-modal-title">Keterangan / Tindakan Perbaikan</h3>
            <a href="#" class="modal-close" id="t3-remarks-modal-close">&times;</a>
        </div>
        <textarea id="t3-remarks-textarea" rows="4" placeholder="Type the finding and corrective action taken..."></textarea>
        <div class="t3-remarks-modal-actions">
            <button type="button" class="btn btn-secondary" id="t3-remarks-cancel">Cancel</button>
            <button type="button" class="btn" id="t3-remarks-save">Save</button>
        </div>
        <div class="t3-remarks-history">
            <div class="t3-remarks-history-title">History (previous months)</div>
            <div class="t3-remarks-history-list" id="t3-remarks-history-list">Loading...</div>
        </div>
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const PEOPLE = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $people)) ?>;
    const PIC_PEOPLE = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], array_values(array_filter($people, fn($p) => in_array($p['name'], ['Rinaldi', 'Mita'], true))))) ?>;
    // No backdating, no future-dating: only the current week of the
    // current month is editable (see includes/calendar_lib.php's
    // is_current_period()/current_week_of_month()).
    const CURRENT_MONTH = <?= json_encode((int) date('n')) ?>;
    const CURRENT_YEAR = <?= json_encode((int) date('Y')) ?>;
    const CURRENT_WEEK = <?= json_encode((int) ceil(((int) date('j')) / 7)) ?>;
</script>
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/3s3t.js?v=<?= @filemtime(__DIR__ . '/assets/js/3s3t.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

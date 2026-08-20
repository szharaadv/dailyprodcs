<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edit_requests.php';
require_login();
$pdo = get_db();

$edit_id = (int)($_GET['edit_id'] ?? 0);
$editing_unlocked = false;
$editing_jig_id = null;
$editing_month = null;
$editing_year = null;
if ($edit_id && has_active_unlock($pdo, 'jig', $edit_id)) {
    $stmt = $pdo->prepare('SELECT h.*, j.department_id FROM t_jigheader h JOIN m_jig j ON j.id = h.jig_id WHERE h.id = ?');
    $stmt->execute([$edit_id]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $_SESSION['department_id'] = (int)$editRow['department_id'];
        $editing_unlocked = true;
        $editing_jig_id = (int)$editRow['jig_id'];
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
    $stmt = $pdo->prepare("SELECT * FROM m_department WHERE id = ? AND is_active = 1 AND form_type = 'assembly'");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch();
}

if (!$department) {
    header('Location: index.php');
    exit;
}

$_SESSION['section_route'] = 'sub_assembly_list.php';

$stmt = $pdo->prepare(
    "SELECT u.* FROM m_user u
     JOIN m_user_section us ON us.user_id = u.id
     JOIN m_checksheet_section s ON s.id = us.section_id
     WHERE u.is_active = 1 AND s.department_id = ? AND s.route = 'sub_assembly_list.php'
     ORDER BY u.name"
);
$stmt->execute([$department['id']]);
$people = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM m_jig WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department['id']]);
$jigs = $stmt->fetchAll();

$selected_jig_id = $editing_unlocked ? $editing_jig_id : (int)($_GET['jig_id'] ?? ($jigs[0]['id'] ?? 0));
// Always open on today's month/year — editing is locked to today anyway,
// so a stale month/year from a bookmark or browser-back would just be dead
// weight. Prev/Next still lets you browse other months once the page is open.
$selected_month = $editing_unlocked ? $editing_month : (int) date('n');
$selected_year = $editing_unlocked ? $editing_year : (int) date('Y');

$selectedJig = null;
foreach ($jigs as $j) {
    if ($j['id'] == $selected_jig_id) { $selectedJig = $j; break; }
}

$refItems = [];
if ($selectedJig) {
    $stmt = $pdo->prepare('SELECT checking_item, photo FROM m_jigitem WHERE jig_id = ? AND is_active = 1 ORDER BY sort_order, id');
    $stmt->execute([$selectedJig['id']]);
    $refItems = $stmt->fetchAll();
}

$base_url = '';
$active_nav = 'checksheet';
$section_route = 'sub_assembly_list.php';
$page_title = 'Check Sheet - Sub Assembly';
$page_subtitle = $department['name'] . ' · Monthly jig inspection record';
require_once __DIR__ . '/includes/breadcrumb.php';
$breadcrumb = build_checksheet_breadcrumb($pdo, $department, 'sub_assembly_list.php');
require __DIR__ . '/includes/app_top.php';

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range((int)date('Y') - 1, (int)date('Y') + 1);
?>

<div class="checksheet-card">
    <div class="alert alert-ok" id="unlock-banner" style="display:none;">Editing an approved past record. Every day this month is unlocked for editing while it stays active.</div>
    <div class="form-grid-top">
        <div class="field-block">
            <label>Jig</label>
            <select id="f_jig">
                <?php foreach ($jigs as $j): ?>
                    <option value="<?= $j['id'] ?>" <?= $j['id'] == $selected_jig_id ? 'selected' : '' ?>><?= htmlspecialchars($j['name']) ?></option>
                <?php endforeach; ?>
                <?php if (!$jigs): ?><option value="">No jigs set up yet</option><?php endif; ?>
            </select>
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
            <label>Supervisor</label>
            <select id="f_supervisor">
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
            <label>Checker</label>
            <select id="f_checker">
                <option value="">—</option>
                <?php foreach ($people as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($selectedJig): ?>
    <details class="jig-reference" open>
        <summary>Jig reference &amp; checking guide</summary>

        <div class="jig-ref-wrap">
        <?php if ($refItems): ?>
        <table class="jig-ref-table">
            <colgroup>
                <col class="jig-ref-col-part">
                <col class="jig-ref-col-method">
                <col class="jig-ref-col-item">
                <col class="jig-ref-col-photo">
                <col class="jig-ref-col-freq">
                <col class="jig-ref-col-pic">
            </colgroup>
            <thead>
                <tr>
                    <th>Part name</th>
                    <th>Checking method</th>
                    <th>Checking item</th>
                    <th>Photo</th>
                    <th>Checking frequency</th>
                    <th>PIC</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($refItems as $i => $item): ?>
                <tr>
                    <?php if ($i === 0): ?><td class="jig-ref-merge" rowspan="<?= count($refItems) ?>"><?= htmlspecialchars($selectedJig['part_name'] ?? '-') ?></td><?php endif; ?>
                    <td><span class="jig-ref-badge"><?= $i + 1 ?></span> <?= htmlspecialchars($selectedJig['checking_method'] ?? '-') ?></td>
                    <td class="jig-ref-item-cell"><?= htmlspecialchars($item['checking_item']) ?></td>
                    <td>
                        <?php if ($item['photo']): ?>
                            <button type="button" class="jig-ref-thumb" style="background-image:url('assets/uploads/jig/<?= htmlspecialchars($item['photo']) ?>');background-size:cover;background-position:center;" data-photo-src="assets/uploads/jig/<?= htmlspecialchars($item['photo']) ?>" data-photo-caption="<?= htmlspecialchars(($i + 1) . '. ' . $item['checking_item']) ?>" aria-label="View photo for <?= htmlspecialchars($item['checking_item']) ?>"></button>
                        <?php else: ?>
                            <div class="jig-ref-thumb jig-ref-thumb-empty">No photo</div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($selectedJig['frequency'] ?? '-') ?></td>
                    <?php if ($i === 0): ?><td class="jig-ref-merge" rowspan="<?= count($refItems) ?>"><?= htmlspecialchars($selectedJig['pic'] ?? '-') ?></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <div class="table-wrap">
        <table id="jig-table" class="jig-table">
            <thead>
                <tr id="jig-table-head"><th>Day</th></tr>
            </thead>
            <tbody id="jig-tbody">
                <tr><td class="empty">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
    <p class="import-hint">Tap OK / NG for each day — it saves right away, no submit button needed.</p>
</div>

<div class="modal-overlay jig-photo-modal" id="jig-photo-modal" style="display:none;">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3 id="jig-photo-caption"></h3>
            <a href="#" class="modal-close" id="jig-photo-close">&times;</a>
        </div>
        <img id="jig-photo-img" src="" alt="">
    </div>
</div>

<script>
    const DEPARTMENT_ID = <?= json_encode($department['id']) ?>;
    const TODAY = <?= json_encode(date('Y-m-d')) ?>;
</script>
<script src="assets/js/calendar-day.js"></script>
<script src="assets/js/subassy.js?v=<?= @filemtime(__DIR__ . '/assets/js/subassy.js') ?: 1 ?>"></script>
<script>
(function () {
    const modal = document.getElementById('jig-photo-modal');
    const img = document.getElementById('jig-photo-img');
    const caption = document.getElementById('jig-photo-caption');
    if (!modal) return;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-photo-src]');
        if (btn) {
            img.src = btn.dataset.photoSrc;
            caption.textContent = btn.dataset.photoCaption || '';
            modal.style.display = 'flex';
            return;
        }
        if (e.target.id === 'jig-photo-close' || e.target === modal) {
            e.preventDefault();
            modal.style.display = 'none';
            img.src = '';
        }
    });
})();
</script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

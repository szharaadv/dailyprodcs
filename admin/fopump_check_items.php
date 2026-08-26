<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

// Checking items are a single GLOBAL master list shared by every FO Pump
// model (model_id IS NULL). The two "Label check" rows fill their Standard
// from the selected model at display time — see standard_source.
$allowedSources = ['static', 'part_no', 'fop_code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $checking_item = trim($_POST['checking_item'] ?? '');
    $standard_source = in_array($_POST['standard_source'] ?? '', $allowedSources, true) ? $_POST['standard_source'] : 'static';
    // For a dynamic label row the Standard comes from the model — store none.
    $standard = $standard_source === 'static' ? trim($_POST['standard'] ?? '') : '';
    $result_type = ($_POST['result_type'] ?? '') === 'boolean' ? 'boolean' : 'value';
    $expected_value = trim($_POST['expected_value'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($checking_item === '') {
        $error = 'Checking Item is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_fopump_check_item SET model_id=NULL, checking_item=?, standard=?, standard_source=?, result_type=?, expected_value=?, sort_order=? WHERE id=?');
            $stmt->execute([$checking_item, $standard ?: null, $standard_source, $result_type, $expected_value ?: null, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_fopump_check_item (model_id, checking_item, standard, standard_source, result_type, expected_value, sort_order) VALUES (NULL,?,?,?,?,?,?)');
            $stmt->execute([$checking_item, $standard ?: null, $standard_source, $result_type, $expected_value ?: null, $sort_order]);
        }
        header('Location: fopump_check_items.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_fopump_check_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: fopump_check_items.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_fopump_check_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: fopump_check_items.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this checking item is already used by a checksheet. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_fopump_check_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$rows = $pdo->query('SELECT * FROM m_fopump_check_item WHERE model_id IS NULL ORDER BY sort_order, id')->fetchAll();

$sourceLabels = ['static' => 'Static', 'part_no' => "Model's Part No", 'fop_code' => "Model's Model code"];

$base_url = '../';
$active_nav = 'config-fopump-check-item';
$section_route = 'fopump_check_list.php';
$page_title = 'Checking Item';
$page_subtitle = 'Master Data · FO Pump Check Sheet Checking Items';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="alert alert-ok" style="background:#eef2ff;color:#3949ab;border-color:#c5cae9;">
    This one checklist applies to <b>every</b> FO Pump model. The <b>Label check – Part no</b> and <b>Label check – Model code</b>
    rows fill their Standard automatically from each model's own data (set <i>Standard source</i> accordingly).
</div>

<div class="list-toolbar">
    <span class="import-hint"><?= count($rows) ?> checking item<?= count($rows) === 1 ? '' : 's' ?></span>
    <a class="btn" href="fopump_check_items.php?action=new">+ Add Checking Item</a>
</div>

<?php if ($showModal): ?>
<?php $curSource = $editRow['standard_source'] ?? 'static'; ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Checking Item' : 'Add Checking Item' ?></h3>
            <a class="modal-close" href="fopump_check_items.php">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Checking Item</label>
                    <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Standard source</label>
                    <select name="standard_source" id="fc-source">
                        <?php foreach ($sourceLabels as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= $curSource === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="import-hint">Choose <i>Model's Part No</i> / <i>Model's Model code</i> for the Label check rows — the Standard is then filled per model automatically.</p>
                </div>
                <div class="form-row" id="fc-standard-row">
                    <label>Standard</label>
                    <input type="text" name="standard" value="<?= htmlspecialchars($editRow['standard'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Result Type</label>
                    <select name="result_type">
                        <option value="boolean" <?= ($editRow['result_type'] ?? 'value') === 'boolean' ? 'selected' : '' ?>>Boolean (TRUE/FALSE match)</option>
                        <option value="value" <?= ($editRow['result_type'] ?? 'value') === 'value' ? 'selected' : '' ?>>Value (typed reading, e.g. OK/NG or a number)</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Expected Value</label>
                    <input type="text" name="expected_value" value="<?= htmlspecialchars($editRow['expected_value'] ?? '') ?>" placeholder="e.g. TRUE, OK, 4.5">
                    <p class="import-hint">The conforming/expected value for reference. Sample columns start empty — the operator fills in each reading.</p>
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="fopump_check_items.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var src = document.getElementById('fc-source');
    var row = document.getElementById('fc-standard-row');
    function sync() { row.style.display = src.value === 'static' ? '' : 'none'; }
    src.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Checking Item</th>
            <th>Standard</th>
            <th>Result Type</th>
            <th>Expected Value</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['checking_item']) ?></td>
            <td>
                <?php if ($row['standard_source'] !== 'static'): ?>
                    <span class="badge badge-ok">Auto · <?= htmlspecialchars($sourceLabels[$row['standard_source']]) ?></span>
                <?php else: ?>
                    <?= htmlspecialchars($row['standard'] ?? '') ?>
                <?php endif; ?>
            </td>
            <td><?= $row['result_type'] === 'boolean' ? 'Boolean' : 'Value' ?></td>
            <td><?= htmlspecialchars($row['expected_value'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="fopump_check_items.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="fopump_check_items.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="fopump_check_items.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this checking item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No checking items yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

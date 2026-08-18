<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $model_id = (int)($_POST['model_id'] ?? 0);
    $checking_item = trim($_POST['checking_item'] ?? '');
    $standard = trim($_POST['standard'] ?? '');
    $result_type = ($_POST['result_type'] ?? '') === 'boolean' ? 'boolean' : 'value';
    $expected_value = trim($_POST['expected_value'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($checking_item === '' || !$model_id) {
        $error = 'Model and Checking Item are required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_fopump_check_item SET model_id=?, checking_item=?, standard=?, result_type=?, expected_value=?, sort_order=? WHERE id=?');
            $stmt->execute([$model_id, $checking_item, $standard ?: null, $result_type, $expected_value ?: null, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_fopump_check_item (model_id, checking_item, standard, result_type, expected_value, sort_order) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$model_id, $checking_item, $standard ?: null, $result_type, $expected_value ?: null, $sort_order]);
        }
        header('Location: fopump_check_items.php?model_id=' . $model_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_fopump_check_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: fopump_check_items.php?model_id=' . (int)($_GET['model_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_fopump_check_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: fopump_check_items.php?model_id=' . (int)($_GET['model_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this checking item is already used by a checksheet. Deactivate it instead.';
    }
}

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_fopump_check_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$modelStmt = $pdo->prepare('SELECT * FROM m_fopump_check_model WHERE department_id = ? ORDER BY sort_order, id');
$modelStmt->execute([$department_id]);
$models = $modelStmt->fetchAll();

$selected_model_id = (int)($_GET['model_id'] ?? ($editRow['model_id'] ?? ($models[0]['id'] ?? 0)));
$selectedModel = null;
foreach ($models as $m) {
    if ($m['id'] == $selected_model_id) { $selectedModel = $m; break; }
}
if (!$selectedModel && $models) {
    $selectedModel = $models[0];
    $selected_model_id = $selectedModel['id'];
}

$rows = [];
if ($selected_model_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_fopump_check_item WHERE model_id = ? ORDER BY sort_order, id');
    $stmt->execute([$selected_model_id]);
    $rows = $stmt->fetchAll();
}

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

<div class="list-toolbar">
    <form method="get" class="toolbar-filter">
        <label for="model_id">Model</label>
        <select id="model_id" name="model_id" onchange="this.form.submit()">
            <?php foreach ($models as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $m['id'] == $selected_model_id ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
            <?php if (!$models): ?><option value="">No models yet</option><?php endif; ?>
        </select>
    </form>
    <?php if ($selectedModel): ?>
        <a class="btn" href="fopump_check_items.php?model_id=<?= $selected_model_id ?>&action=new">+ Add Checking Item</a>
    <?php endif; ?>
</div>

<?php if ($selectedModel): ?>
<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Checking Item' : 'Add Checking Item' ?></h3>
            <a class="modal-close" href="fopump_check_items.php?model_id=<?= $selected_model_id ?>">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Model</label>
                    <select name="model_id" required>
                        <?php foreach ($models as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $m['id'] == ($editRow['model_id'] ?? $selected_model_id) ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Checking Item</label>
                    <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
                </div>
                <div class="form-row">
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
                    <p class="import-hint">Pre-fills every new sample column with this conforming default; the operator only edits it if it deviates.</p>
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="fopump_check_items.php?model_id=<?= $selected_model_id ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
            </div>
        </form>
    </div>
</div>
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
            <td><?= htmlspecialchars($row['standard'] ?? '') ?></td>
            <td><?= $row['result_type'] === 'boolean' ? 'Boolean' : 'Value' ?></td>
            <td><?= htmlspecialchars($row['expected_value'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="fopump_check_items.php?model_id=<?= $selected_model_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="fopump_check_items.php?model_id=<?= $selected_model_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="fopump_check_items.php?model_id=<?= $selected_model_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this checking item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No checking items for this model yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <div class="empty">No models found — add a Model first.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

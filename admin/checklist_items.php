<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/import_lib.php';
$pdo = get_db();

$departments = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' ORDER BY sort_order, id")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $condition_id = (int)($_POST['condition_id'] ?? 0);
    $checking_item = trim($_POST['checking_item'] ?? '');
    $metode_pengecekan = trim($_POST['metode_pengecekan'] ?? '') ?: 'Visual';
    $standard_min = trim($_POST['standard_min'] ?? '');
    $standard_max = trim($_POST['standard_max'] ?? '');
    $satuan = trim($_POST['satuan'] ?? '') ?: '-';
    $tank_tube = trim($_POST['tank_tube'] ?? '') ?: '-';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($checking_item === '' || !$condition_id) {
        $error = 'Condition and Checking Item are required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_checklist_item SET condition_id=?, checking_item=?, metode_pengecekan=?, standard_min=?, standard_max=?, satuan=?, tank_tube=?, sort_order=? WHERE id=?');
            $stmt->execute([$condition_id, $checking_item, $metode_pengecekan, import_nz($standard_min), import_nz($standard_max), $satuan, $tank_tube, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_checklist_item (condition_id, checking_item, metode_pengecekan, standard_min, standard_max, satuan, tank_tube, sort_order) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$condition_id, $checking_item, $metode_pengecekan, import_nz($standard_min), import_nz($standard_max), $satuan, $tank_tube, $sort_order]);
        }
        header('Location: checklist_items.php?condition_id=' . $condition_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_checklist_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: checklist_items.php?condition_id=' . (int)($_GET['condition_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_checklist_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: checklist_items.php?condition_id=' . (int)($_GET['condition_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this checking item is already used by a checksheet. Deactivate it instead.';
    }
}

$selected_department_id = (int)($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_checklist_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$condStmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? ORDER BY sort_order, id');
$condStmt->execute([$selected_department_id]);
$conditions = $condStmt->fetchAll();

$selected_condition_id = (int)($_GET['condition_id'] ?? ($editRow['condition_id'] ?? ($conditions[0]['id'] ?? 0)));
$selectedCond = null;
foreach ($conditions as $c) {
    if ($c['id'] == $selected_condition_id) { $selectedCond = $c; break; }
}
if (!$selectedCond && $conditions) {
    $selectedCond = $conditions[0];
    $selected_condition_id = $selectedCond['id'];
}
if ($editRow && !$selectedCond) {
    // editRow's condition belongs to a different department filter — pull it in for the dropdown/table.
    $stmt = $pdo->prepare('SELECT * FROM m_condition WHERE id = ?');
    $stmt->execute([$editRow['condition_id']]);
    $selectedCond = $stmt->fetch() ?: null;
    if ($selectedCond) {
        $selected_department_id = (int)$selectedCond['department_id'];
        $selected_condition_id = (int)$selectedCond['id'];
        $condStmt->execute([$selected_department_id]);
        $conditions = $condStmt->fetchAll();
    }
}

$rows = [];
if ($selected_condition_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_checklist_item WHERE condition_id = ? ORDER BY sort_order, id');
    $stmt->execute([$selected_condition_id]);
    $rows = $stmt->fetchAll();
}

$base_url = '../';
$active_nav = 'config-checklist-item';
$section_route = 'painting_list.php';
$page_title = 'Checking Item';
$page_subtitle = 'Master Data · Checking Items';
require __DIR__ . '/../includes/app_top.php';
?>


<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="list-toolbar">
    <form method="get" class="toolbar-filter">
        <label for="condition_id">Condition</label>
        <select id="condition_id" name="condition_id" onchange="this.form.submit()">
            <?php foreach ($conditions as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $selected_condition_id ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
            <?php if (!$conditions): ?><option value="">No conditions in this department</option><?php endif; ?>
        </select>
    </form>
    <?php if ($selectedCond): ?>
        <a class="btn" href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=new">+ Add Checking Item</a>
    <?php endif; ?>
</div>

<?php if ($selectedCond): ?>
<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Checking Item' : 'Add Checking Item' ?></h3>
            <a class="modal-close" href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Condition</label>
                    <select name="condition_id" required>
                        <?php foreach ($conditions as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == ($editRow['condition_id'] ?? $selected_condition_id) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Checking Item</label>
                    <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Metode Pengecekkan</label>
                    <input type="text" name="metode_pengecekan" value="<?= htmlspecialchars($editRow['metode_pengecekan'] ?? 'Visual') ?>">
                </div>
                <div class="form-row">
                    <label>Standard Min.</label>
                    <input type="text" name="standard_min" value="<?= htmlspecialchars($editRow['standard_min'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Standard Max.</label>
                    <input type="text" name="standard_max" value="<?= htmlspecialchars($editRow['standard_max'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Satuan</label>
                    <input type="text" name="satuan" value="<?= htmlspecialchars($editRow['satuan'] ?? '-') ?>">
                </div>
                <div class="form-row">
                    <label>Tank/Tube</label>
                    <input type="text" name="tank_tube" value="<?= htmlspecialchars($editRow['tank_tube'] ?? '-') ?>">
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>" class="btn btn-secondary">Cancel</a>
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
            <th>Metode</th>
            <th>Std Min</th>
            <th>Std Max</th>
            <th>Satuan</th>
            <th>Tank/Tube</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['checking_item']) ?></td>
            <td><?= htmlspecialchars($row['metode_pengecekan']) ?></td>
            <td><?= htmlspecialchars($row['standard_min'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['standard_max'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['satuan'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['tank_tube'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="checklist_items.php?department_id=<?= $selected_department_id ?>&condition_id=<?= $selected_condition_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this checking item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="empty">No checking items for this condition yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <div class="empty">No conditions found for this department — add a Condition first.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

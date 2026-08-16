<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $bakeoven_id = (int)($_POST['bakeoven_id'] ?? 0);
    $time_label = trim($_POST['time_label'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($time_label === '' || !$bakeoven_id) {
        $error = 'Oven and Checking Time are required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_bakeoven_time SET bakeoven_id=?, time_label=?, sort_order=? WHERE id=?');
            $stmt->execute([$bakeoven_id, $time_label, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_bakeoven_time (bakeoven_id, time_label, sort_order) VALUES (?,?,?)');
            $stmt->execute([$bakeoven_id, $time_label, $sort_order]);
        }
        header('Location: bakeoven_times.php?bakeoven_id=' . $bakeoven_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_bakeoven_time SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: bakeoven_times.php?bakeoven_id=' . (int)($_GET['bakeoven_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_bakeoven_time WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: bakeoven_times.php?bakeoven_id=' . (int)($_GET['bakeoven_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this checking time already has check sheet history. Deactivate it instead.';
    }
}

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_bakeoven_time WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$ovenStmt = $pdo->prepare('SELECT * FROM m_bakeoven WHERE department_id = ? ORDER BY sort_order, id');
$ovenStmt->execute([$department_id]);
$ovens = $ovenStmt->fetchAll();

$selected_bakeoven_id = (int)($_GET['bakeoven_id'] ?? ($editRow['bakeoven_id'] ?? ($ovens[0]['id'] ?? 0)));
$selectedOven = null;
foreach ($ovens as $o) {
    if ($o['id'] == $selected_bakeoven_id) { $selectedOven = $o; break; }
}
if (!$selectedOven && $ovens) {
    $selectedOven = $ovens[0];
    $selected_bakeoven_id = $selectedOven['id'];
}

$rows = [];
if ($selected_bakeoven_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_bakeoven_time WHERE bakeoven_id = ? ORDER BY sort_order, id');
    $stmt->execute([$selected_bakeoven_id]);
    $rows = $stmt->fetchAll();
}

$base_url = '../';
$active_nav = 'config-bakeoven-time';
$section_route = 'bakeoven_list.php';
$page_title = 'Checking Time';
$page_subtitle = 'Master Data · Bake Oven Checking Times';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="list-toolbar">
    <form method="get" class="toolbar-filter">
        <label for="bakeoven_id">Oven</label>
        <select id="bakeoven_id" name="bakeoven_id" onchange="this.form.submit()">
            <?php foreach ($ovens as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $o['id'] == $selected_bakeoven_id ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
            <?php if (!$ovens): ?><option value="">No ovens yet</option><?php endif; ?>
        </select>
    </form>
    <?php if ($selectedOven): ?>
        <a class="btn" href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>&action=new">+ Add Checking Time</a>
    <?php endif; ?>
</div>

<?php if ($selectedOven): ?>
<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Checking Time' : 'Add Checking Time' ?></h3>
            <a class="modal-close" href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Oven</label>
                    <select name="bakeoven_id" required>
                        <?php foreach ($ovens as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $o['id'] == ($editRow['bakeoven_id'] ?? $selected_bakeoven_id) ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Checking Time</label>
                    <input type="text" name="time_label" value="<?= htmlspecialchars($editRow['time_label'] ?? '') ?>" placeholder="e.g. 7:00" required>
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>" class="btn btn-secondary">Cancel</a>
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
            <th>Checking Time</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['time_label']) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="bakeoven_times.php?bakeoven_id=<?= $selected_bakeoven_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this checking time?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No checking times for this oven yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <div class="empty">No ovens found — add an Oven first.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

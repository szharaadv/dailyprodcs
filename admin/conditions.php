<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$departments = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' ORDER BY sort_order, id")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $department_id = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '' || !$department_id) {
        $error = 'Department and Condition Name are required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_condition SET department_id = ?, name = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$department_id, $name, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_condition (department_id, name, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$department_id, $name, $sort_order]);
        }
        header('Location: conditions.php?department_id=' . $department_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_condition SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: conditions.php?department_id=' . (int)($_GET['department_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_condition WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: conditions.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this condition is already used by a checking item / checksheet. Deactivate it instead.';
    }
}

$selected_department_id = (int)($_GET['department_id'] ?? ($departments[0]['id'] ?? 0));

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_condition WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $selected_department_id = (int)$editRow['department_id'];
    }
}

$stmt = $pdo->prepare('SELECT * FROM m_condition WHERE department_id = ? ORDER BY sort_order, id');
$stmt->execute([$selected_department_id]);
$rows = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-condition';
$section_route = 'painting_list.php';
$page_title = 'Condition';
$page_subtitle = 'Master Data · Painting';
require __DIR__ . '/../includes/app_top.php';
?>


<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Department</label>
            <select name="department_id" required>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == ($editRow['department_id'] ?? $selected_department_id) ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Condition Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="conditions.php?department_id=<?= $selected_department_id ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Condition Name</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="conditions.php?department_id=<?= $selected_department_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="conditions.php?department_id=<?= $selected_department_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="conditions.php?department_id=<?= $selected_department_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this condition?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No conditions for this department yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

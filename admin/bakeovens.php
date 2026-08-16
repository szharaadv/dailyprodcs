<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'checklist' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $standard_min = trim($_POST['standard_min'] ?? '');
    $standard_max = trim($_POST['standard_max'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Oven name is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_bakeoven SET name=?, standard_min=?, standard_max=?, sort_order=? WHERE id=?');
            $stmt->execute([$name, $standard_min ?: null, $standard_max ?: null, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_bakeoven (department_id, name, standard_min, standard_max, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$department_id, $name, $standard_min ?: null, $standard_max ?: null, $sort_order]);
        }
        header('Location: bakeovens.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_bakeoven SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: bakeovens.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_bakeoven WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: bakeovens.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this oven already has checking times / check sheet history. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_bakeoven WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->prepare('SELECT * FROM m_bakeoven WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$department_id]);
$rows = $rows->fetchAll();

$base_url = '../';
$active_nav = 'config-bakeoven';
$section_route = 'bakeoven_list.php';
$page_title = 'Oven';
$page_subtitle = 'Master Data · Bake Ovens';
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
            <label>Oven Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Standard Min. (°C)</label>
            <input type="text" name="standard_min" value="<?= htmlspecialchars($editRow['standard_min'] ?? '160') ?>">
        </div>
        <div class="form-row">
            <label>Standard Max. (°C)</label>
            <input type="text" name="standard_max" value="<?= htmlspecialchars($editRow['standard_max'] ?? '165') ?>">
        </div>
        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="bakeovens.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Oven Name</th>
            <th>Standard Min.</th>
            <th>Standard Max.</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['standard_min'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['standard_max'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="bakeovens.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="bakeovens.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="bakeovens.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this oven?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No ovens yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

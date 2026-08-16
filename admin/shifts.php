<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Shift name is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_shift SET name = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$name, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_shift (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $sort_order]);
        }
        header('Location: shifts.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_shift SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: shifts.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_shift WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: shifts.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this shift is already used by a checksheet. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_shift WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->query('SELECT * FROM m_shift ORDER BY sort_order, id')->fetchAll();

$base_url = '../';
$active_nav = 'config-shift';
$section_route = 'painting_list.php';
$page_title = 'Shift';
$page_subtitle = 'Master Data · Shift';
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
            <label>Shift Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="shifts.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Shift Name</th>
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
                <a href="shifts.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="shifts.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="shifts.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this shift?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No shifts yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

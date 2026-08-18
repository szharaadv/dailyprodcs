<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $department_id = (int)($_POST['department_id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $item_pemeriksaan = trim($_POST['item_pemeriksaan'] ?? '');
    $standar_kriteria = trim($_POST['standar_kriteria'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($item_pemeriksaan === '' || !$department_id) {
        $error = 'Department and Item Pemeriksaan are required.';
    } else {
        $params = [$department_id, $category ?: null, $item_pemeriksaan, $standar_kriteria ?: null, $sort_order];
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_3s3t_item SET department_id=?, category=?, item_pemeriksaan=?, standar_kriteria=?, sort_order=? WHERE id=?');
            $stmt->execute(array_merge($params, [(int)$id]));
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_3s3t_item (department_id, category, item_pemeriksaan, standar_kriteria, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute($params);
        }
        header('Location: 3s3t_items.php?department_id=' . $department_id . '&saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare('UPDATE m_3s3t_item SET is_active = NOT is_active WHERE id = ?')->execute([(int)$_GET['id']]);
    header('Location: 3s3t_items.php?department_id=' . (int)($_GET['department_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $pdo->prepare('DELETE FROM m_3s3t_item WHERE id = ?')->execute([(int)$_GET['id']]);
        header('Location: 3s3t_items.php?department_id=' . (int)($_GET['department_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this item already has check sheet history. Deactivate it instead.';
    }
}

$departments = $pdo->query('SELECT * FROM m_department WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_3s3t_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$selected_department_id = (int)($_GET['department_id'] ?? ($editRow['department_id'] ?? ($departments[0]['id'] ?? 0)));

$rows = $pdo->prepare('SELECT * FROM m_3s3t_item WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$selected_department_id]);
$rows = $rows->fetchAll();

$base_url = '../';
$active_nav = 'config-3s3t-item';
$section_route = '3s3t_list.php';
$page_title = 'Checksheet 3S-3T Item';
$page_subtitle = 'Master Data · Checksheet 3S-3T Items';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="list-toolbar">
    <form method="get" class="toolbar-filter">
        <label for="department_id">Department</label>
        <select id="department_id" name="department_id" onchange="this.form.submit()">
            <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id'] == $selected_department_id ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a class="btn" href="3s3t_items.php?department_id=<?= $selected_department_id ?>&action=new">+ Add Item</a>
</div>

<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Item' : 'Add Item' ?></h3>
            <a class="modal-close" href="3s3t_items.php?department_id=<?= $selected_department_id ?>">&times;</a>
        </div>
        <form method="post">
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
                    <label>Category</label>
                    <input type="text" name="category" value="<?= htmlspecialchars($editRow['category'] ?? '') ?>" placeholder="e.g. SEIRI (Sortir/Pemilahan) — leave blank to group under the row above">
                </div>
                <div class="form-row">
                    <label>Item Pemeriksaan</label>
                    <input type="text" name="item_pemeriksaan" value="<?= htmlspecialchars($editRow['item_pemeriksaan'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Standar / Kriteria</label>
                    <input type="text" name="standar_kriteria" value="<?= htmlspecialchars($editRow['standar_kriteria'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="3s3t_items.php?department_id=<?= $selected_department_id ?>" class="btn btn-secondary">Cancel</a>
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
            <th>Category</th>
            <th>Item Pemeriksaan</th>
            <th>Standar / Kriteria</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['item_pemeriksaan']) ?></td>
            <td><?= htmlspecialchars($row['standar_kriteria'] ?? '-') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="3s3t_items.php?department_id=<?= $selected_department_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="3s3t_items.php?department_id=<?= $selected_department_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="3s3t_items.php?department_id=<?= $selected_department_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">No items yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

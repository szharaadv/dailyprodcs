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
    $process_name = trim($_POST['process_name'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $maker_brand = trim($_POST['maker_brand'] ?? '');
    $standard_min = trim($_POST['standard_min'] ?? '');
    $standard_max = trim($_POST['standard_max'] ?? '');
    $standard_unit = trim($_POST['standard_unit'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($product_name === '') {
        $error = 'Product Name is required.';
    } else {
        $params = [
            $department_id, $process_name ?: null, $product_name, $maker_brand ?: null,
            $standard_min ?: null, $standard_max ?: null, $standard_unit ?: null, $sort_order,
        ];
        if ($id !== '') {
            $stmt = $pdo->prepare(
                'UPDATE m_paint_viscosity_item SET department_id=?, process_name=?, product_name=?, maker_brand=?, standard_min=?, standard_max=?, standard_unit=?, sort_order=? WHERE id=?'
            );
            $stmt->execute(array_merge($params, [(int)$id]));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO m_paint_viscosity_item (department_id, process_name, product_name, maker_brand, standard_min, standard_max, standard_unit, sort_order) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute($params);
        }
        header('Location: paint_viscosity_items.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare('UPDATE m_paint_viscosity_item SET is_active = NOT is_active WHERE id = ?')->execute([(int)$_GET['id']]);
    header('Location: paint_viscosity_items.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $pdo->prepare('DELETE FROM m_paint_viscosity_item WHERE id = ?')->execute([(int)$_GET['id']]);
        header('Location: paint_viscosity_items.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this item already has check sheet history. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_paint_viscosity_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$rows = $pdo->prepare('SELECT * FROM m_paint_viscosity_item WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$department_id]);
$rows = $rows->fetchAll();

$base_url = '../';
$active_nav = 'config-paint-viscosity-item';
$section_route = 'paint_viscosity_list.php';
$page_title = 'Viscosity Item';
$page_subtitle = 'Master Data · Paint Viscosity Products';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="list-toolbar">
    <a class="btn" href="paint_viscosity_items.php?action=new">+ Add Product</a>
</div>

<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Product' : 'Add Product' ?></h3>
            <a class="modal-close" href="paint_viscosity_items.php">&times;</a>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Process Name</label>
                    <input type="text" name="process_name" value="<?= htmlspecialchars($editRow['process_name'] ?? '') ?>" placeholder="e.g. PRIMER COAT (leave blank to group under the row above)">
                </div>
                <div class="form-row">
                    <label>Product Name</label>
                    <input type="text" name="product_name" value="<?= htmlspecialchars($editRow['product_name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Maker/Brand</label>
                    <input type="text" name="maker_brand" value="<?= htmlspecialchars($editRow['maker_brand'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Standard Min</label>
                    <input type="text" name="standard_min" value="<?= htmlspecialchars($editRow['standard_min'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Standard Max</label>
                    <input type="text" name="standard_max" value="<?= htmlspecialchars($editRow['standard_max'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Unit</label>
                    <input type="text" name="standard_unit" value="<?= htmlspecialchars($editRow['standard_unit'] ?? 'SEC (NK/CUP)') ?>">
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <a href="paint_viscosity_items.php" class="btn btn-secondary">Cancel</a>
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
            <th>Process</th>
            <th>Product Name</th>
            <th>Maker/Brand</th>
            <th>Standard</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['process_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['product_name']) ?></td>
            <td><?= htmlspecialchars($row['maker_brand'] ?? '-') ?></td>
            <td><?= htmlspecialchars(trim(($row['standard_min'] ?? '') . ' ~ ' . ($row['standard_max'] ?? '') . ' ' . ($row['standard_unit'] ?? ''))) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="paint_viscosity_items.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="paint_viscosity_items.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="paint_viscosity_items.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this product?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No products yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

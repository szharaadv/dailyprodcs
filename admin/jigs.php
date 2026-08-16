<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $part_name = trim($_POST['part_name'] ?? '');
    $checking_method = trim($_POST['checking_method'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $pic = trim($_POST['pic'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Jig name is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_jig SET name=?, part_name=?, checking_method=?, frequency=?, pic=?, sort_order=? WHERE id=?');
            $stmt->execute([$name, $part_name ?: null, $checking_method ?: null, $frequency ?: null, $pic ?: null, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_jig (department_id, name, part_name, checking_method, frequency, pic, sort_order) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$department_id, $name, $part_name ?: null, $checking_method ?: null, $frequency ?: null, $pic ?: null, $sort_order]);
        }
        header('Location: jigs.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_jig SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: jigs.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_jig WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: jigs.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this jig already has checking items / check sheet history. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_jig WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->prepare('SELECT * FROM m_jig WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$department_id]);
$rows = $rows->fetchAll();

$base_url = '../';
$active_nav = 'config-jig';
$section_route = 'sub_assembly_list.php';
$page_title = 'Jig';
$page_subtitle = 'Master Data · Sub Assembly Jigs';
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
            <label>Jig Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Part Name</label>
            <input type="text" name="part_name" value="<?= htmlspecialchars($editRow['part_name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Checking Method</label>
            <input type="text" name="checking_method" value="<?= htmlspecialchars($editRow['checking_method'] ?? 'Touched') ?>">
        </div>
        <div class="form-row">
            <label>Checking Frequency</label>
            <input type="text" name="frequency" value="<?= htmlspecialchars($editRow['frequency'] ?? 'Before work activity') ?>">
        </div>
        <div class="form-row">
            <label>PIC</label>
            <input type="text" name="pic" value="<?= htmlspecialchars($editRow['pic'] ?? '') ?>" placeholder="e.g. Operator sub assy gear case">
        </div>
        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="jigs.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Jig Name</th>
            <th>Part Name</th>
            <th>Checking Method</th>
            <th>Frequency</th>
            <th>PIC</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['part_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['checking_method'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['frequency'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['pic'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="jigs.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="jigs.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="jigs.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this jig?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="empty">No jigs yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

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
    $fop_code = trim($_POST['fop_code'] ?? '');
    $standard_cc_sec = trim($_POST['standard_cc_sec'] ?? '');
    $rpm = trim($_POST['rpm'] ?? '');
    $master_test = trim($_POST['master_test'] ?? '');
    $default_shim = trim($_POST['default_shim'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Model name is required.';
    } else {
        $params = [$name, $fop_code ?: null, $standard_cc_sec ?: null, $rpm ?: null, $master_test ?: null, $default_shim ?: null, $sort_order];
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_fopump_test_model SET name=?, fop_code=?, standard_cc_sec=?, rpm=?, master_test=?, default_shim=?, sort_order=? WHERE id=?');
            $stmt->execute(array_merge($params, [(int)$id]));
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_fopump_test_model (department_id, name, fop_code, standard_cc_sec, rpm, master_test, default_shim, sort_order) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute(array_merge([$department_id], $params));
        }
        header('Location: fopump_test_models.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_fopump_test_model SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: fopump_test_models.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_fopump_test_model WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: fopump_test_models.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this model is already used by a checksheet. Deactivate it instead.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_fopump_test_model WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->prepare('SELECT * FROM m_fopump_test_model WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$department_id]);
$rows = $rows->fetchAll();

$engineModels = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

$base_url = '../';
$active_nav = 'config-fopump-test-model';
$section_route = 'fopump_test_list.php';
$page_title = 'Model';
$page_subtitle = 'Master Data · FO Pump Test Record Models';
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
            <label>Model Name</label>
            <input type="text" name="name" id="model_name_input" placeholder="Search Master Engine..." value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>FOP Code</label>
            <input type="text" name="fop_code" value="<?= htmlspecialchars($editRow['fop_code'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label>Standard (cc/sec)</label>
            <input type="text" name="standard_cc_sec" value="<?= htmlspecialchars($editRow['standard_cc_sec'] ?? '') ?>" placeholder="e.g. 0.71-0.72">
        </div>
        <div class="form-row">
            <label>Rpm</label>
            <input type="text" name="rpm" value="<?= htmlspecialchars($editRow['rpm'] ?? '') ?>" placeholder="e.g. 1200">
        </div>
        <div class="form-row">
            <label>Master Test</label>
            <input type="text" name="master_test" value="<?= htmlspecialchars($editRow['master_test'] ?? '') ?>" placeholder="e.g. N750">
        </div>
        <div class="form-row">
            <label>Default Shim</label>
            <input type="text" name="default_shim" value="<?= htmlspecialchars($editRow['default_shim'] ?? '') ?>" placeholder="e.g. 0.5+0.25">
        </div>
        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="fopump_test_models.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Model Name</th>
            <th>FOP Code</th>
            <th>Standard (cc/sec)</th>
            <th>Rpm</th>
            <th>Master Test</th>
            <th>Default Shim</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['fop_code'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['standard_cc_sec'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['rpm'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['master_test'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['default_shim'] ?? '') ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="fopump_test_models.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="fopump_test_models.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="fopump_test_models.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this model?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="empty">No models yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<script>
    const ENGINE_MODELS = <?= json_encode($engineModels) ?>;
</script>
<script src="../assets/js/combo-select.js"></script>
<script>
    turnIntoCombo(document.getElementById('model_name_input'), ENGINE_MODELS.map(m => ({ value: m, label: m })), { allowCustom: true });
</script>
<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

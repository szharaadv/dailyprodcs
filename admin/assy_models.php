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
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Model name is required.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_assy_model SET name = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$name, $sort_order, (int)$id]);
            header('Location: assy_models.php?saved=1');
            exit;
        }

        // New model starts EMPTY (no auto-seeded checking items). The admin
        // builds its checking items incrementally via "Bulk Checking Item" or
        // "Checking Item". It shows the "Baru · belum diset" badge until items
        // are added (configured stays 0).
        $stmt = $pdo->prepare('INSERT INTO m_assy_model (department_id, name, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([$department_id, $name, $sort_order]);
        header('Location: assy_models.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'configure' && isset($_GET['id'])) {
    $pdo->prepare('UPDATE m_assy_model SET configured = 1 WHERE id = ?')->execute([(int)$_GET['id']]);
    header('Location: assy_models.php?saved=1');
    exit;
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_assy_model SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: assy_models.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $mid = (int)$_GET['id'];
    // A new model is auto-seeded with checking items, so it always "has" items —
    // that alone shouldn't block deletion. Only real usage (an actual checksheet
    // referencing this model) does. If none, remove its checking items first,
    // then the model.
    $used = $pdo->prepare('SELECT COUNT(*) FROM t_assy_header WHERE model_id = ?');
    $used->execute([$mid]);
    if ((int)$used->fetchColumn() > 0) {
        $error = 'Tidak bisa dihapus: model ini sudah dipakai di checksheet. Nonaktifkan (Deactivate) saja.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM m_assy_checklist_item WHERE model_id = ?')->execute([$mid]);
            $pdo->prepare('DELETE FROM m_assy_model WHERE id = ?')->execute([$mid]);
            $pdo->commit();
            header('Location: assy_models.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Tidak bisa dihapus: model ini masih dipakai. Nonaktifkan (Deactivate) saja.';
        }
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_assy_model WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->prepare('SELECT * FROM m_assy_model WHERE department_id = ? ORDER BY sort_order, id');
$rows->execute([$department_id]);
$rows = $rows->fetchAll();

$engineModels = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

$base_url = '../';
$active_nav = 'config-assy-model';
$section_route = 'assembly_list.php';
$page_title = 'Model';
$page_subtitle = 'Master Data · Assembling Models';
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
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="assy_models.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Model Name</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td>
                <a class="model-link" href="assy_checklist_items.php?model_id=<?= (int)$row['id'] ?>" title="Lihat Checking Item model ini"><?= htmlspecialchars($row['name']) ?></a>
                <?php if (empty($row['configured'])): ?>
                    <span class="badge badge-new" title="Model baru — checking item masih default, belum disesuaikan ke standar">Baru · belum diset</span>
                <?php endif; ?>
            </td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="assy_checklist_items.php?model_id=<?= (int)$row['id'] ?>">Setting Item</a>
                <?php if (empty($row['configured'])): ?>
                    <a href="assy_models.php?action=configure&id=<?= $row['id'] ?>" title="Tandai model ini sudah disesuaikan ke standar">Tandai sudah diset</a>
                <?php endif; ?>
                <a href="assy_models.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="assy_models.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="assy_models.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this model?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">No models yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<style>
    .model-link { color: #9a342c; font-weight: 600; text-decoration: none; }
    .model-link:hover { text-decoration: underline; }
    .badge-new { background: #fff4e5; color: #b25e09; border: 1px solid #f4c988; font-weight: 600;
        font-size: 11px; padding: 1px 8px; border-radius: 20px; margin-left: 8px; white-space: nowrap; }
</style>
<script>
    const ENGINE_MODELS = <?= json_encode($engineModels) ?>;
</script>
<script src="../assets/js/combo-select.js"></script>
<script>
    turnIntoCombo(document.getElementById('model_name_input'), ENGINE_MODELS.map(m => ({ value: m, label: m })), { allowCustom: true });
</script>
<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

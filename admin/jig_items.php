<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

$uploadDir = __DIR__ . '/../assets/uploads/jig/';
$uploadUrlBase = '../assets/uploads/jig/';
$allowedExt = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];

/** Validate + move an uploaded reference photo. Returns the stored relative path, or null. */
function handle_jig_photo_upload(array $file, string $uploadDir, array $allowedExt): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Photo is too large (max 5MB).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowedExt[$ext])) {
        throw new RuntimeException('Only .jpg, .png or .webp photos are accepted.');
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new RuntimeException('Could not save the uploaded photo.');
    }
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $jig_id = (int)($_POST['jig_id'] ?? 0);
    $checking_item = trim($_POST['checking_item'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $remove_photo = !empty($_POST['remove_photo']);

    if ($checking_item === '' || !$jig_id) {
        $error = 'Jig and Checking Item are required.';
    } else {
        try {
            $photo = handle_jig_photo_upload($_FILES['photo'] ?? [], $uploadDir, $allowedExt);

            if ($id !== '') {
                if ($photo === null && !$remove_photo) {
                    $stmt = $pdo->prepare('UPDATE m_jig_item SET jig_id=?, checking_item=?, sort_order=? WHERE id=?');
                    $stmt->execute([$jig_id, $checking_item, $sort_order, (int)$id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE m_jig_item SET jig_id=?, checking_item=?, sort_order=?, photo=? WHERE id=?');
                    $stmt->execute([$jig_id, $checking_item, $sort_order, $remove_photo ? null : $photo, (int)$id]);
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_jig_item (jig_id, checking_item, photo, sort_order) VALUES (?,?,?,?)');
                $stmt->execute([$jig_id, $checking_item, $photo, $sort_order]);
            }
            header('Location: jig_items.php?jig_id=' . $jig_id . '&saved=1');
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_jig_item SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: jig_items.php?jig_id=' . (int)($_GET['jig_id'] ?? 0));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_jig_item WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: jig_items.php?jig_id=' . (int)($_GET['jig_id'] ?? 0) . '&deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this checking item already has check sheet history. Deactivate it instead.';
    }
}

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_jig_item WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$jigStmt = $pdo->prepare('SELECT * FROM m_jig WHERE department_id = ? ORDER BY sort_order, id');
$jigStmt->execute([$department_id]);
$jigs = $jigStmt->fetchAll();

$selected_jig_id = (int)($_GET['jig_id'] ?? ($editRow['jig_id'] ?? ($jigs[0]['id'] ?? 0)));
$selectedJig = null;
foreach ($jigs as $j) {
    if ($j['id'] == $selected_jig_id) { $selectedJig = $j; break; }
}
if (!$selectedJig && $jigs) {
    $selectedJig = $jigs[0];
    $selected_jig_id = $selectedJig['id'];
}

$rows = [];
if ($selected_jig_id) {
    $stmt = $pdo->prepare('SELECT * FROM m_jig_item WHERE jig_id = ? ORDER BY sort_order, id');
    $stmt->execute([$selected_jig_id]);
    $rows = $stmt->fetchAll();
}

$base_url = '../';
$active_nav = 'config-jig-item';
$section_route = 'sub_assembly_list.php';
$page_title = 'Checking Item';
$page_subtitle = 'Master Data · Sub Assembly Checking Items';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<div class="list-toolbar">
    <form method="get" class="toolbar-filter">
        <label for="jig_id">Jig</label>
        <select id="jig_id" name="jig_id" onchange="this.form.submit()">
            <?php foreach ($jigs as $j): ?>
                <option value="<?= $j['id'] ?>" <?= $j['id'] == $selected_jig_id ? 'selected' : '' ?>><?= htmlspecialchars($j['name']) ?></option>
            <?php endforeach; ?>
            <?php if (!$jigs): ?><option value="">No jigs yet</option><?php endif; ?>
        </select>
    </form>
    <?php if ($selectedJig): ?>
        <a class="btn" href="jig_items.php?jig_id=<?= $selected_jig_id ?>&action=new">+ Add Checking Item</a>
    <?php endif; ?>
</div>

<?php if ($selectedJig): ?>
<?php if ($showModal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit Checking Item' : 'Add Checking Item' ?></h3>
            <a class="modal-close" href="jig_items.php?jig_id=<?= $selected_jig_id ?>">&times;</a>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Jig</label>
                    <select name="jig_id" required>
                        <?php foreach ($jigs as $j): ?>
                            <option value="<?= $j['id'] ?>" <?= $j['id'] == ($editRow['jig_id'] ?? $selected_jig_id) ? 'selected' : '' ?>><?= htmlspecialchars($j['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Checking Item</label>
                    <input type="text" name="checking_item" value="<?= htmlspecialchars($editRow['checking_item'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Order</label>
                    <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
                </div>
                <div class="form-row">
                    <label>Reference Photo</label>
                    <?php if (!empty($editRow['photo'])): ?>
                        <img src="<?= $uploadUrlBase . htmlspecialchars($editRow['photo']) ?>" alt="" style="max-width:120px;max-height:90px;border-radius:6px;border:1px solid #e3e5e9;margin-bottom:6px;">
                    <?php endif; ?>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($editRow['photo'])): ?>
                        <label class="checkbox-item" style="margin-top:6px;">
                            <input type="checkbox" name="remove_photo" value="1"> Remove current photo
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal-actions">
                <a href="jig_items.php?jig_id=<?= $selected_jig_id ?>" class="btn btn-secondary">Cancel</a>
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
            <th>Photo</th>
            <th>Checking Item</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?php if ($row['photo']): ?><img src="<?= $uploadUrlBase . htmlspecialchars($row['photo']) ?>" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:4px;"><?php else: ?>—<?php endif; ?></td>
            <td><?= htmlspecialchars($row['checking_item']) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="jig_items.php?jig_id=<?= $selected_jig_id ?>&action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="jig_items.php?jig_id=<?= $selected_jig_id ?>&action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="jig_items.php?jig_id=<?= $selected_jig_id ?>&action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this checking item?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No checking items for this jig yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php else: ?>
    <div class="empty">No jigs found — add a Jig first.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

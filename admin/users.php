<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = get_db();

$sections = $pdo->query(
    "SELECT s.id, s.name, d.name AS dept_name FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order"
)->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['superadmin', 'admin'], true) ? $_POST['role'] : 'user';
    $pin = trim($_POST['pin'] ?? '');
    $section_ids = array_map('intval', $_POST['section_ids'] ?? []);

    $pinTaken = false;
    if ($pin !== '' && preg_match('/^\d{4}$/', $pin)) {
        $stmt = $pdo->prepare('SELECT name FROM m_user WHERE pin = ? AND id <> ?');
        $stmt->execute([$pin, $id !== '' ? (int)$id : 0]);
        $pinTaken = $stmt->fetchColumn();
    }

    if ($name === '') {
        $error = 'Name is required.';
    } elseif ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) {
        $error = 'PIN must be exactly 4 digits.';
    } elseif ($pinTaken) {
        $error = "PIN already in use by '$pinTaken' — since the User login now identifies people by PIN alone, each PIN must be unique.";
    } else {
        try {
            $pdo->beginTransaction();
            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE m_user SET name = ?, title = ?, role = ?, pin = ? WHERE id = ?');
                $stmt->execute([$name, $title !== '' ? $title : null, $role, $pin !== '' ? $pin : null, (int)$id]);
                $user_id = (int)$id;
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_user (name, title, role, pin) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $title !== '' ? $title : null, $role, $pin !== '' ? $pin : null]);
                $user_id = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM m_user_section WHERE user_id = ?')->execute([$user_id]);
            if ($section_ids) {
                $insSec = $pdo->prepare('INSERT INTO m_user_section (user_id, section_id) VALUES (?, ?)');
                foreach ($section_ids as $sid) {
                    $insSec->execute([$user_id, $sid]);
                }
            }
            $pdo->commit();
            header('Location: users.php?saved=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "A user named '$name' already exists.";
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_user SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: users.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_user WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: users.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Cannot delete, this user is already referenced by a checksheet. Deactivate instead.';
    }
}

$editRow = null;
$editSectionIds = [];
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_user WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
    if ($editRow) {
        $stmt = $pdo->prepare('SELECT section_id FROM m_user_section WHERE user_id = ?');
        $stmt->execute([$editRow['id']]);
        $editSectionIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

$rows = $pdo->query(
    "SELECT u.*, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS section_names
     FROM m_user u
     LEFT JOIN m_user_section us ON us.user_id = u.id
     LEFT JOIN m_checksheet_section s ON s.id = us.section_id
     GROUP BY u.id
     ORDER BY u.name"
)->fetchAll();

$base_url = '../';
$active_nav = 'mgmt-users';
// No hardcoded section — let sidebar.php fall back to $_SESSION['section_route'],
// so Configuration keeps showing whichever department (Painting/Assembling) the
// user was last working in, instead of always snapping back to Painting.
$page_title = 'Users';
$page_subtitle = 'Management · Users';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<button type="button" class="btn" id="btn-add-user" style="margin-bottom:16px;">+ Add User</button>

<div class="modal-overlay" id="user-modal" style="display:<?= ($editRow || $error) ? 'flex' : 'none' ?>;">
    <div class="modal-card">
        <div class="modal-card-header">
            <h3><?= $editRow ? 'Edit User' : 'Add User' ?></h3>
            <a href="users.php" class="modal-close" id="user-modal-close">&times;</a>
        </div>
        <form method="post" class="admin-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

            <div class="form-grid">
                <div class="form-row">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <label>Title (optional)</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editRow['title'] ?? '') ?>" placeholder="e.g. Foreman, Supervisor">
                </div>
                <div class="form-row">
                    <label>App Role</label>
                    <select name="role">
                        <option value="user" <?= ($editRow['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= ($editRow['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="superadmin" <?= ($editRow['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>PIN (4 digits)</label>
                    <input type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="e.g. 1234" value="<?= htmlspecialchars($editRow['pin'] ?? '') ?>">
                </div>
            </div>
            <p class="import-hint">This person uses their PIN to sign in from the login screen's "User" option — enter their PIN there. Leave blank if they shouldn't be able to sign in yet.</p>

            <div class="form-row">
                <label>Visible on check sheets</label>
                <div class="checkbox-grid">
                    <?php $lastDept = null; foreach ($sections as $s): if ($s['dept_name'] !== $lastDept): $lastDept = $s['dept_name']; ?>
                        <div class="checkbox-grid-label"><?= htmlspecialchars($lastDept) ?></div>
                    <?php endif; ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="section_ids[]" value="<?= $s['id'] ?>" <?= in_array($s['id'], $editSectionIds, true) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="import-hint">Tick a check sheet to let this person be picked as "Checked by" there. Leave everything unchecked and they won't show up on any check sheet.</p>
            </div>

            <div class="modal-actions">
                <a href="users.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('user-modal');
    const addBtn = document.getElementById('btn-add-user');
    addBtn.addEventListener('click', () => { modal.style.display = 'flex'; });
    modal.addEventListener('click', (e) => { if (e.target === modal) window.location.href = 'users.php'; });
})();
</script>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Title</th>
            <th>App Role</th>
            <th>PIN</th>
            <th>Visible On</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
            <?php $roleBadge = ['superadmin' => 'badge-accent', 'admin' => 'badge-ok', 'user' => 'badge-off'][$row['role']] ?? 'badge-off'; ?>
            <td><span class="badge <?= $roleBadge ?>"><?= $row['role'] === 'superadmin' ? 'Super Admin' : ucfirst($row['role']) ?></span></td>
            <td><?= $row['pin'] ? '<span class="badge badge-ok">Set</span>' : '<span class="badge badge-off">Not set</span>' ?></td>
            <td><?= $row['section_names'] ? htmlspecialchars($row['section_names']) : '<span class="import-hint">Not routed — won\'t appear on any check sheet</span>' ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="users.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="users.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="users.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Delete this user?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">No users yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

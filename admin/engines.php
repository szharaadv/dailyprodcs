<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $sales_type = ($_POST['sales_type'] ?? '') === 'EXP' ? 'EXP' : 'DOM';
    $model = trim($_POST['model'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($model === '') {
        $error = 'Model is required.';
    } else {
        try {
            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE m_engine SET sales_type=?, model=?, sort_order=? WHERE id=?');
                $stmt->execute([$sales_type, $model, $sort_order, (int)$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_engine (sales_type, model, sort_order) VALUES (?, ?, ?)');
                $stmt->execute([$sales_type, $model, $sort_order]);
            }
            $postQ = $_POST['q'] ?? '';
            header('Location: engines.php?saved=1' . ($postQ !== '' ? '&q=' . urlencode($postQ) : ''));
            exit;
        } catch (PDOException $e) {
            $error = "Model '$model' already exists.";
        }
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_engine SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: engines.php?q=' . urlencode($_GET['q'] ?? ''));
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM m_engine WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: engines.php?deleted=1&q=' . urlencode($_GET['q'] ?? ''));
    exit;
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_engine WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}
$showModal = in_array($_GET['action'] ?? '', ['edit', 'new'], true);

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT * FROM m_engine WHERE model LIKE ? ORDER BY sort_order, model');
    $stmt->execute(['%' . $q . '%']);
} else {
    $stmt = $pdo->query('SELECT * FROM m_engine ORDER BY sort_order, model');
}
$rows = $stmt->fetchAll();

$base_url = '../';
$active_nav = 'config-master-engine';
$page_title = 'Master Engine';
$page_subtitle = 'Master Data · Engine model codes used across every Model field';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">
    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Sales Type</label>
            <select name="sales_type">
                <option value="DOM" <?= ($editRow['sales_type'] ?? 'DOM') === 'DOM' ? 'selected' : '' ?>>DOM</option>
                <option value="EXP" <?= ($editRow['sales_type'] ?? '') === 'EXP' ? 'selected' : '' ?>>EXP</option>
            </select>
        </div>
        <div class="form-row">
            <label>Model</label>
            <input type="text" name="model" value="<?= htmlspecialchars($editRow['model'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Order</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="engines.php?q=<?= urlencode($q) ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<form method="get" class="toolbar-filter" style="margin-bottom:12px;">
    <label for="q">Search Model</label>
    <input type="text" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. TF90, TS230..." autocomplete="off">
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($q !== ''): ?><a href="engines.php" class="btn btn-secondary">Clear</a><?php endif; ?>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Sales Type</th>
            <th>Model</th>
            <th>Order</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['sales_type']) ?></td>
            <td><?= htmlspecialchars($row['model']) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="engines.php?action=edit&id=<?= $row['id'] ?>&q=<?= urlencode($q) ?>">Edit</a>
                <a href="engines.php?action=toggle&id=<?= $row['id'] ?>&q=<?= urlencode($q) ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="engines.php?action=delete&id=<?= $row['id'] ?>&q=<?= urlencode($q) ?>" onclick="return confirm('Delete this engine model?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No engine models found<?= $q !== '' ? ' for "' . htmlspecialchars($q) . '"' : '' ?>.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

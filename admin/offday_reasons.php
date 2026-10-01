<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($name === '') {
        $error = 'Nama alasan wajib diisi.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE m_offday_reason SET name = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$name, $sort_order, (int)$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO m_offday_reason (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $sort_order]);
        }
        header('Location: offday_reasons.php?saved=1');
        exit;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('UPDATE m_offday_reason SET is_active = NOT is_active WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: offday_reasons.php');
    exit;
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare('DELETE FROM m_offday_reason WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        header('Location: offday_reasons.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Tidak bisa dihapus — alasan ini sudah dipakai di keterangan. Nonaktifkan saja.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_offday_reason WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->query('SELECT * FROM m_offday_reason ORDER BY sort_order, id')->fetchAll();

$base_url = '../';
$active_nav = 'config-offday-reason';
$page_title = 'Keterangan Tidak Isi';
$page_subtitle = 'Master Data · Alasan tidak mengisi checksheet';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<p class="import-hint" style="margin-bottom:14px;">Alasan di sini muncul di tombol <b>+ Keterangan</b> pada banner "Missing checks this month" di semua checksheet harian.</p>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">
    <div class="form-grid">
        <div class="form-row">
            <label>Nama Alasan</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" placeholder="mis. Preventive Maintenance" required>
        </div>
        <div class="form-row">
            <label>Urutan</label>
            <input type="number" name="sort_order" value="<?= htmlspecialchars($editRow['sort_order'] ?? (count($rows) + 1)) ?>">
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Tambah' ?></button>
        <?php if ($editRow): ?><a href="offday_reasons.php" class="btn btn-secondary">Batal</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr><th>Nama Alasan</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td><?= $row['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="offday_reasons.php?action=edit&id=<?= $row['id'] ?>">Edit</a>
                <a href="offday_reasons.php?action=toggle&id=<?= $row['id'] ?>"><?= $row['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="offday_reasons.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Hapus alasan ini?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">Belum ada alasan.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

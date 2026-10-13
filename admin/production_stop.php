<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = get_db();
$me = current_user();

$error = null;

// ---- Add / update a production-stop period ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_stop') {
    $id = $_POST['id'] ?? '';
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $reason_id = (int)($_POST['reason_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    $ds = DateTime::createFromFormat('Y-m-d', $start);
    $de = DateTime::createFromFormat('Y-m-d', $end);
    if (!$ds || $ds->format('Y-m-d') !== $start || !$de || $de->format('Y-m-d') !== $end) {
        $error = 'Tanggal mulai/selesai tidak valid.';
    } elseif ($end < $start) {
        $error = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
    } elseif (!$reason_id) {
        $error = 'Pilih alasan.';
    } else {
        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE t_prodstop SET start_date=?, end_date=?, reason_id=?, note=? WHERE id=?');
            $stmt->execute([$start, $end, $reason_id, $note ?: null, (int)$id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO t_prodstop (start_date, end_date, reason_id, note, created_by, created_by_name)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$start, $end, $reason_id, $note ?: null, $me['id'] ?? null, $me['name'] ?? null]);
        }
        header('Location: production_stop.php?saved=1');
        exit;
    }
}

// ---- Add a reason ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_reason') {
    $name = trim($_POST['reason_name'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare('INSERT INTO m_prodstop_reason (name, sort_order) VALUES (?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM m_prodstop_reason m))');
        $stmt->execute([$name]);
    }
    header('Location: production_stop.php?saved=1');
    exit;
}

// ---- GET actions ----
if (($_GET['action'] ?? '') === 'delete_stop' && isset($_GET['id'])) {
    $pdo->prepare('DELETE FROM t_prodstop WHERE id = ?')->execute([(int)$_GET['id']]);
    header('Location: production_stop.php?deleted=1');
    exit;
}
if (($_GET['action'] ?? '') === 'toggle_reason' && isset($_GET['id'])) {
    $pdo->prepare('UPDATE m_prodstop_reason SET is_active = NOT is_active WHERE id = ?')->execute([(int)$_GET['id']]);
    header('Location: production_stop.php');
    exit;
}
if (($_GET['action'] ?? '') === 'delete_reason' && isset($_GET['id'])) {
    try {
        $pdo->prepare('DELETE FROM m_prodstop_reason WHERE id = ?')->execute([(int)$_GET['id']]);
        header('Location: production_stop.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Alasan ini sudah dipakai di jadwal — nonaktifkan saja.';
    }
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit_stop' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM t_prodstop WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$reasons = $pdo->query('SELECT * FROM m_prodstop_reason ORDER BY sort_order, id')->fetchAll();
$activeReasons = array_values(array_filter($reasons, fn($r) => $r['is_active']));
$stops = $pdo->query(
    'SELECT s.*, r.name AS reason_name FROM t_prodstop s
     JOIN m_prodstop_reason r ON r.id = s.reason_id
     ORDER BY s.start_date DESC'
)->fetchAll();

$base_url = '../';
$active_nav = 'config-prodstop';
$page_title = 'Stop Produksi';
$page_subtitle = 'Master Data · Tanggal produksi berhenti (PM, stock taking) — tidak dihitung "missing" di semua checksheet';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<p class="import-hint" style="margin-bottom:14px;">Tandai rentang tanggal saat produksi dihentikan (berlaku <b>seluruh plant</b>). Pada tanggal itu, semua checksheet harian <b>tidak lagi dihitung "missing"</b>.</p>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_stop">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">
    <div class="form-grid">
        <div class="form-row">
            <label>Tanggal Mulai</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($editRow['start_date'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Tanggal Selesai</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($editRow['end_date'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Alasan</label>
            <select name="reason_id" required>
                <option value="">Pilih alasan…</option>
                <?php foreach ($activeReasons as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= ($editRow && $editRow['reason_id'] == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Catatan (opsional)</label>
            <input type="text" name="note" value="<?= htmlspecialchars($editRow['note'] ?? '') ?>" placeholder="opsional">
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Tambah' ?></button>
        <?php if ($editRow): ?><a href="production_stop.php" class="btn btn-secondary">Batal</a><?php endif; ?>
    </div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr><th>Mulai</th><th>Selesai</th><th>Jml Hari</th><th>Alasan</th><th>Catatan</th><th>Oleh</th><th>Aksi</th></tr>
    </thead>
    <tbody>
        <?php foreach ($stops as $row): ?>
        <?php $days = (new DateTime($row['start_date']))->diff(new DateTime($row['end_date']))->days + 1; ?>
        <tr>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['start_date']))) ?></td>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['end_date']))) ?></td>
            <td><?= $days ?></td>
            <td><span class="badge badge-off"><?= htmlspecialchars($row['reason_name']) ?></span></td>
            <td><?= htmlspecialchars($row['note'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['created_by_name'] ?: '-') ?></td>
            <td class="row-actions">
                <a href="production_stop.php?action=edit_stop&id=<?= $row['id'] ?>">Edit</a>
                <a href="production_stop.php?action=delete_stop&id=<?= $row['id'] ?>" onclick="return confirm('Hapus jadwal stop ini?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$stops): ?><tr><td colspan="7" class="empty">Belum ada jadwal stop produksi.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<h3 style="margin:28px 0 10px;font:600 15px Inter,sans-serif;">Kelola Alasan</h3>
<form method="post" class="admin-form" style="margin-bottom:12px;">
    <input type="hidden" name="action" value="save_reason">
    <div class="form-grid">
        <div class="form-row">
            <label>Alasan Baru</label>
            <input type="text" name="reason_name" placeholder="mis. Preventive Maintenance" required>
        </div>
    </div>
    <div class="form-row"><button type="submit" class="btn btn-secondary">Tambah Alasan</button></div>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead><tr><th>Alasan</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
        <?php foreach ($reasons as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= $r['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td class="row-actions">
                <a href="production_stop.php?action=toggle_reason&id=<?= $r['id'] ?>"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                <a href="production_stop.php?action=delete_reason&id=<?= $r['id'] ?>" onclick="return confirm('Hapus alasan ini?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$reasons): ?><tr><td colspan="3" class="empty">Belum ada alasan.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

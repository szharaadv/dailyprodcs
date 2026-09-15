<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_admin();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $audience = ($_POST['audience'] ?? 'all') === 'user' ? 'user' : 'all';
        $userId = $audience === 'user' ? (int)($_POST['user_id'] ?? 0) : null;
        if ($title === '') {
            header('Location: notifications.php?err=1');
            exit;
        }
        if ($audience === 'user' && !$userId) {
            header('Location: notifications.php?err=2');
            exit;
        }
        notif_create($pdo, $title, $body, $type, $audience, $userId, current_user()['name'] ?? 'Admin');
        header('Location: notifications.php?sent=1');
        exit;
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM t_notification WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: notifications.php?deleted=1');
        exit;
    }
}

$users = $pdo->query("SELECT id, name, title FROM m_user WHERE is_active = 1 AND name <> 'Admin' ORDER BY name")->fetchAll();
$sent = $pdo->query(
    "SELECT n.*, u.name AS target_name,
            (SELECT COUNT(*) FROM t_notification_read r WHERE r.notification_id = n.id) AS read_count
     FROM t_notification n LEFT JOIN m_user u ON u.id = n.user_id
     ORDER BY n.created_at DESC, n.id DESC LIMIT 50"
)->fetchAll();

$base_url = '../';
$active_nav = 'mgmt-notifications';
$page_title = 'Notifikasi';
$page_subtitle = 'Management · Kirim pengumuman / update / reminder ke user';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (isset($_GET['sent'])): ?><div class="alert alert-ok">Notifikasi terkirim.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Notifikasi dihapus.</div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div class="alert alert-error">Judul wajib diisi<?= $_GET['err'] == 2 ? ', dan pilih user tujuan' : '' ?>.</div><?php endif; ?>

<div class="admin-form" style="margin-bottom:22px;">
    <div class="admin-form-title">&#128276; Kirim notifikasi baru</div>
    <div class="admin-form-hint">Pengumuman / update / reminder ini akan muncul di lonceng notifikasi user.</div>
    <form method="post">
        <input type="hidden" name="action" value="send">
        <div class="form-grid">
            <div class="form-row" style="grid-column:1/-1;">
                <label>Judul *</label>
                <input type="text" name="title" maxlength="200" required placeholder="mis. Update sistem / Pengingat isi checksheet">
            </div>
            <div class="form-row" style="grid-column:1/-1;">
                <label>Isi pesan</label>
                <textarea name="body" rows="3" placeholder="Detail pengumuman / reminder (opsional)"></textarea>
            </div>
            <div class="form-row">
                <label>Jenis</label>
                <select name="type">
                    <option value="info">Info / Pengumuman</option>
                    <option value="update">Update</option>
                    <option value="reminder">Reminder</option>
                </select>
            </div>
            <div class="form-row">
                <label>Tujuan</label>
                <select name="audience" id="notif-audience">
                    <option value="all">Semua user</option>
                    <option value="user">User tertentu</option>
                </select>
            </div>
            <div class="form-row" id="notif-user-row" style="display:none;">
                <label>Pilih user</label>
                <select name="user_id">
                    <option value="">— pilih —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?><?= $u['title'] ? ' (' . htmlspecialchars($u['title']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:6px;"><button type="submit" class="btn">&#128276; Kirim notifikasi</button></div>
    </form>
</div>

<h3 style="margin:22px 0 12px;font:600 15px Inter,sans-serif;color:#1f2430;">Terkirim <span style="color:#8b93a1;font-weight:500;">(50 terakhir)</span></h3>
<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr><th>Waktu</th><th>Jenis</th><th>Judul</th><th>Tujuan</th><th style="width:90px;">Dibaca</th><th style="width:70px;"></th></tr>
    </thead>
    <tbody>
        <?php foreach ($sent as $n): ?>
        <tr>
            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['created_at']))) ?></td>
            <td><span class="notif-type notif-type-<?= htmlspecialchars($n['type']) ?>"><?= htmlspecialchars(ucfirst($n['type'])) ?></span></td>
            <td>
                <strong><?= htmlspecialchars($n['title']) ?></strong>
                <?php if (!empty($n['body'])): ?><div style="font-size:12px;color:#6b7280;"><?= nl2br(htmlspecialchars($n['body'])) ?></div><?php endif; ?>
            </td>
            <td><?= $n['audience'] === 'all' ? 'Semua user' : htmlspecialchars($n['target_name'] ?? '—') ?></td>
            <td><?= (int)$n['read_count'] ?>x</td>
            <td>
                <form method="post" onsubmit="return confirm('Hapus notifikasi ini?');" style="margin:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$sent): ?><tr><td colspan="6" class="empty">Belum ada notifikasi terkirim.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<script>
(function () {
    const aud = document.getElementById('notif-audience');
    const row = document.getElementById('notif-user-row');
    function sync() { row.style.display = aud.value === 'user' ? '' : 'none'; }
    aud.addEventListener('change', sync); sync();
})();
</script>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

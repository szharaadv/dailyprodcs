<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $docTitle = $_POST['doc_title'] ?? [];
    $docNo   = $_POST['doc_no'] ?? [];
    $docRev  = $_POST['doc_rev'] ?? [];
    $docDate = $_POST['doc_date'] ?? [];
    $stmt = $pdo->prepare('UPDATE m_checksheet_section SET doc_title = ?, doc_no = ?, doc_rev = ?, doc_date = ? WHERE id = ?');
    foreach ($docNo as $sid => $_) {
        $sid = (int)$sid;
        $stmt->execute([
            trim((string)($docTitle[$sid] ?? '')) ?: null,
            trim((string)($docNo[$sid] ?? '')) ?: null,
            trim((string)($docRev[$sid] ?? '')) ?: null,
            trim((string)($docDate[$sid] ?? '')) ?: null,
            $sid,
        ]);
    }
    header('Location: section_docs.php?saved=1');
    exit;
}

$rows = $pdo->query(
    "SELECT s.id, s.name, s.route, s.doc_title, s.doc_no, s.doc_rev, s.doc_date, d.name AS dept
     FROM m_checksheet_section s JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order"
)->fetchAll();

$base_url = '../';
$active_nav = 'mgmt-section-docs';
$page_title = 'Nomor Dokumen Checksheet';
$page_subtitle = 'Management · No. Doc / Revisi untuk header export Excel';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Tersimpan.</div><?php endif; ?>
<p class="import-hint" style="margin-bottom:12px;">Nilai ini muncul di kotak header saat checksheet diekspor ke Excel (No. Doc / Revisi / Tgl).</p>

<form method="post">
    <input type="hidden" name="action" value="save">
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>Departemen</th><th>Checksheet</th><th style="width:260px;">Judul Export</th><th style="width:180px;">No. Doc</th><th style="width:90px;">Revisi</th><th style="width:120px;">Tgl</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['dept']) ?></td>
                <td><b><?= htmlspecialchars($r['name']) ?></b></td>
                <td><input type="text" name="doc_title[<?= $r['id'] ?>]" value="<?= htmlspecialchars($r['doc_title'] ?? '') ?>" placeholder="mis. PAINTING MONTHLY CHECK SHEET REPORT" style="width:100%;"></td>
                <td><input type="text" name="doc_no[<?= $r['id'] ?>]" value="<?= htmlspecialchars($r['doc_no'] ?? '') ?>" placeholder="mis. QCPC/PTG/MTC-01" style="width:100%;"></td>
                <td><input type="text" name="doc_rev[<?= $r['id'] ?>]" value="<?= htmlspecialchars($r['doc_rev'] ?? '') ?>" placeholder="00" style="width:100%;"></td>
                <td><input type="text" name="doc_date[<?= $r['id'] ?>]" value="<?= htmlspecialchars($r['doc_date'] ?? '') ?>" placeholder="01-01-2026" style="width:100%;"></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="modal-actions" style="margin-top:14px;">
        <button type="submit" class="btn">Simpan Semua</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

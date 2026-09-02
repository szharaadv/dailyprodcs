<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$id = (int)($_GET['header_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.*, m.name AS model_name, ck.name AS checker_name
     FROM t_assy_header h
     JOIN m_assy_model m ON m.id = h.model_id
     LEFT JOIN m_user ck ON ck.id = h.checker_id
     WHERE h.id = ?'
);
$stmt->execute([$id]);
$header = $stmt->fetch();
if (!$header) { header('Location: assy_engine_revision.php'); exit; }

$rev = $pdo->prepare(
    'SELECT r.*, i.checking_item, i.standard_min, i.standard_max
     FROM t_assy_revision r
     JOIN m_assy_checklist_item i ON i.id = r.checklist_item_id
     WHERE r.header_id = ? ORDER BY r.revised_at ASC, r.id ASC'
);
$rev->execute([$id]);
$rows = $rev->fetchAll();

function e($v) { return htmlspecialchars((string)$v); }
$printed_by = current_user()['name'] ?? '-';
$printed_stamp = date('d/m/Y H:i');   // actual print time
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Revisi Engine — <?= e($header['model_name']) ?> — <?= e($header['no_engine'] ?: $header['id']) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; color: #1a1a1a; margin: 0; background: #f2f2f4; }
    .toolbar { position: sticky; top: 0; display: flex; gap: 10px; justify-content: center;
        padding: 12px; background: #fff; border-bottom: 1px solid #e3e3e6; }
    .toolbar .btn { padding: 8px 16px; border-radius: 8px; border: 1px solid transparent;
        font-size: 14px; cursor: pointer; text-decoration: none; }
    .btn-print { background: #9a342c; color: #fff; }
    .btn-back { background: #fff; color: #333; border-color: #d0d0d4; }
    .sheet { max-width: 900px; margin: 20px auto; background: #fff; padding: 34px 40px;
        box-shadow: 0 1px 4px rgba(0,0,0,.08); }
    .doc-head { display: flex; justify-content: space-between; align-items: flex-start;
        border-bottom: 2px solid #1a1a1a; padding-bottom: 12px; margin-bottom: 18px; }
    .doc-title { font-size: 20px; font-weight: 800; letter-spacing: .5px; }
    .doc-sub { color: #666; font-size: 12.5px; margin-top: 2px; }
    .doc-meta-right { text-align: right; font-size: 12px; color: #555; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 28px; margin-bottom: 20px; }
    .info-row { display: flex; font-size: 13px; }
    .info-row .k { width: 130px; color: #666; }
    .info-row .v { font-weight: 600; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    th, td { border: 1px solid #cfcfd4; padding: 7px 9px; text-align: left; vertical-align: top; }
    thead th { background: #f2f2f4; font-weight: 700; }
    td.old { color: #b3261e; font-weight: 700; white-space: nowrap; }
    td.arrow { text-align: center; color: #888; }
    td.new { color: #1e7d34; font-weight: 700; white-space: nowrap; }
    .empty { text-align: center; color: #888; padding: 26px; }
    .sign { display: flex; justify-content: flex-end; gap: 70px; margin-top: 46px; }
    .sign .box { text-align: center; font-size: 12px; color: #444; }
    .sign .line { margin-top: 52px; border-top: 1px solid #333; padding-top: 4px; min-width: 160px; }
    .sign .signer { font-size: 13px; color: #1a1a1a; }
    .sign .role { font-size: 11px; color: #888; margin-top: 2px; }
    .foot { margin-top: 26px; font-size: 11px; color: #999; text-align: center; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0 6mm; }
        @page { size: A4; margin: 14mm; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <a class="btn btn-back" href="assy_engine_revision.php?header_id=<?= (int)$id ?>">&larr; Kembali</a>
    <button class="btn btn-print" onclick="window.print()">&#128424; Cetak / Simpan PDF</button>
</div>

<div class="sheet">
    <div class="doc-head">
        <div>
            <div class="doc-title">LAPORAN REVISI ENGINE</div>
            <div class="doc-sub">Torque / Assembling &middot; Rework &amp; Re-inspection</div>
        </div>
        <div class="doc-meta-right">
            No. Engine: <b><?= e($header['no_engine'] ?: '-') ?></b><br>
            Dicetak: <?= e($printed_stamp) ?><br>
            Oleh: <?= e($printed_by) ?>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-row"><span class="k">Tanggal Checksheet</span><span class="v"><?= e(date('d/m/Y', strtotime($header['tanggal']))) ?></span></div>
        <div class="info-row"><span class="k">Model</span><span class="v"><?= e($header['model_name']) ?></span></div>
        <div class="info-row"><span class="k">No. Engine</span><span class="v"><?= e($header['no_engine'] ?: '-') ?></span></div>
        <div class="info-row"><span class="k">No. Cyl Block</span><span class="v"><?= e($header['no_cyl_block'] ?: '-') ?></span></div>
        <div class="info-row"><span class="k">Checker</span><span class="v"><?= e($header['checker_name'] ?: '-') ?></span></div>
        <div class="info-row"><span class="k">Detail Model</span><span class="v"><?= e($header['detail_model'] ?: '-') ?></span></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:26px;">No</th>
                <th>Checking Item</th>
                <th style="width:70px;">Std Min</th>
                <th style="width:70px;">Std Max</th>
                <th style="width:80px;">Nilai Lama</th>
                <th style="width:24px;"></th>
                <th style="width:80px;">Nilai Baru</th>
                <th>Catatan</th>
                <th style="width:110px;">Direvisi Oleh</th>
                <th style="width:96px;">Waktu</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 1; foreach ($rows as $r): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><?= e($r['checking_item']) ?></td>
                <td><?= e($r['standard_min'] ?? '-') ?></td>
                <td><?= e($r['standard_max'] ?? '-') ?></td>
                <td class="old"><?= e($r['old_value'] ?? '-') ?></td>
                <td class="arrow">&rarr;</td>
                <td class="new"><?= e($r['new_value'] ?? '-') ?></td>
                <td><?= e($r['note'] ?? '') ?></td>
                <td><?= e($r['revised_by_name'] ?? '-') ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($r['revised_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="10" class="empty">Belum ada revisi untuk engine ini.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <div class="sign">
        <div class="box"><div>Dibuat oleh</div><div class="line"><b class="signer"><?= e($printed_by) ?></b><div class="role">Operator / Checker</div></div></div>
        <div class="box"><div>Disetujui oleh</div><div class="line">Foreman / Supervisor</div></div>
    </div>

    <div class="foot">Dokumen ini dibuat otomatis dari sistem Daily Production Check Sheet.</div>
</div>

<?php if (($_GET['print'] ?? '') === '1'): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>

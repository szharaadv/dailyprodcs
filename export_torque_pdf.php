<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/standard_verdict.php';
require_login();
$pdo = get_db();

$section_id = (int)($_GET['section_id'] ?? 0);
$month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$year  = (int)($_GET['year'] ?? date('Y'));

// Torque section (fall back to the assembly section if id not given).
if ($section_id) {
    $s = $pdo->prepare("SELECT * FROM m_checksheet_section WHERE id=? AND is_active=1");
    $s->execute([$section_id]);
    $section = $s->fetch();
}
if (empty($section)) {
    $section = $pdo->query("SELECT * FROM m_checksheet_section WHERE route='assembly_list.php' AND is_active=1 ORDER BY id LIMIT 1")->fetch();
}
if (!$section) { http_response_code(404); exit('Section Torque tidak ditemukan.'); }
$department_id = (int)$section['department_id'];

// Doc-box metadata (fall back to the values on the paper form).
$doc_title = trim((string)($section['doc_title'] ?? '')) ?: 'TORQUE CHECK SHEET REPORT';
$doc_no    = trim((string)($section['doc_no'] ?? ''))   ?: 'F - AS - 05';
$doc_rev   = ($section['doc_rev'] ?? '') !== '' ? $section['doc_rev'] : '0';
$doc_date  = trim((string)($section['doc_date'] ?? '')) ?: '01 April 2026';

$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$monthLabel = $monthNames[$month] . ' ' . $year;

// Logo embedded as data URI (works in the browser's print-to-PDF).
$logoData = '';
foreach (['logo.png', 'yanmar-seeklogo.png'] as $f) {
    if (is_file(__DIR__ . '/assets/img/' . $f)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/img/' . $f));
        break;
    }
}

// Engines for the month.
$hq = $pdo->prepare(
    "SELECT h.id, h.tanggal, h.created_at, h.no_engine, h.no_cyl_block, m.name AS model_name, ck.name AS checker
     FROM t_assy_header h JOIN m_assy_model m ON m.id=h.model_id
     LEFT JOIN m_user ck ON ck.id=h.checker_id
     WHERE h.department_id=? AND h.status='submitted' AND MONTH(h.tanggal)=? AND YEAR(h.tanggal)=?
     ORDER BY h.tanggal, h.id"
);
$hq->execute([$department_id, $month, $year]);
$engines = $hq->fetchAll();

$dq = $pdo->prepare(
    "SELECT i.checking_item, i.standard, i.standard_min, i.standard_max, d.actual_result, d.consumable_item, d.checklist_item_id
     FROM t_assy_detail d JOIN m_assy_checklist_item i ON i.id=d.checklist_item_id
     WHERE d.header_id=? ORDER BY i.sort_order, i.id"
);
$rq = $pdo->prepare("SELECT DISTINCT checklist_item_id FROM t_assy_revision WHERE header_id=?");

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$total = count($engines);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Torque Check Sheet Report — <?= $e($monthLabel) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Calibri, "Segoe UI", Arial, sans-serif; margin: 0; background: #edeef0; color: #111; font-size: 11px; }
    .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; justify-content: center;
        padding: 12px; background: #fff; border-bottom: 1px solid #e3e3e6; }
    .toolbar .btn { padding: 8px 16px; border-radius: 8px; border: 1px solid transparent; font-size: 14px; cursor: pointer; text-decoration: none; }
    .btn-print { background: #9a342c; color: #fff; }
    .btn-back { background: #fff; color: #333; border-color: #d0d0d4; }

    .page { width: 210mm; min-height: 296mm; background: #fff; margin: 16px auto; padding: 12mm 10mm;
        box-shadow: 0 1px 4px rgba(0,0,0,.12); }
    table { border-collapse: collapse; width: 100%; }

    .head td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
    .head .logo { width: 96px; text-align: center; }
    .head .logo img { max-width: 88px; max-height: 44px; }
    .head .title { text-align: center; font-size: 16px; font-weight: 800; }
    .head .month { text-align: center; font-size: 11px; font-weight: 700; }
    .head .dl { font-weight: 700; white-space: nowrap; width: 70px; }
    .head .dv { width: 150px; }

    .banner { background: #2c3e50; color: #fff; font-weight: 700; padding: 6px 8px; margin-top: 12px; font-size: 11px; }

    .data { margin-top: 0; }
    .data th { background: #1a9e7a; color: #fff; border: 1px solid #148f6e; padding: 5px 6px; font-size: 10.5px; }
    .data td { border: 1px solid #bfbfbf; padding: 3px 6px; }
    .data td.c { text-align: center; }
    .data td.ok { background: #e8f7ee; color: #1f7a3d; font-weight: 700; text-align: center; }
    .data td.ng { background: #fdecea; color: #c0392b; font-weight: 700; text-align: center; }
    .data td.edited { color: #3538cd; font-weight: 700; text-align: center; }

    .summary { background: #f2f2f2; font-style: italic; padding: 5px 8px; border: 1px solid #bfbfbf; border-top: none; font-size: 10.5px; }
    .signs { display: flex; justify-content: space-around; margin-top: 30px; }
    .signs .box { text-align: center; font-weight: 700; }
    .signs .line { margin-top: 46px; border-top: 1px solid #333; padding-top: 4px; min-width: 170px; }
    .empty { padding: 40px; text-align: center; color: #888; }

    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none;
            page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        @page { size: A4 portrait; margin: 12mm 10mm; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <a class="btn btn-back" href="view_assy_checksheets.php">&larr; Kembali</a>
    <button class="btn btn-print" onclick="window.print()">&#128424; Cetak / Simpan PDF — <?= $e($monthLabel) ?> (<?= (int)$total ?> engine)</button>
</div>

<?php if (!$engines): ?>
    <div class="page"><div class="empty">Tidak ada data Torque untuk <?= $e($monthLabel) ?>.</div></div>
<?php endif; ?>

<?php $pageNo = 0; foreach ($engines as $h): $pageNo++;
    $dq->execute([$h['id']]);
    $items = $dq->fetchAll();
    $rq->execute([$h['id']]);
    $revised = array_map('intval', $rq->fetchAll(PDO::FETCH_COLUMN));
    $ok = 0; $ng = 0; $n = 0; $body = '';
    foreach ($items as $it) {
        $n++;
        $verdict = std_verdict($it['standard_min'], $it['standard_max'], $it['actual_result']);
        $isng = $verdict === 'NG';
        $isng ? $ng++ : $ok++;
        $std = $it['standard'] ?: trim(($it['standard_min'] ?? '') . ' ~ ' . ($it['standard_max'] ?? ''), ' ~');
        $edited = in_array((int)$it['checklist_item_id'], $revised, true);
        $body .= '<tr>'
            . '<td class="c">' . $n . '</td>'
            . '<td>' . $e($h['model_name']) . '</td>'
            . '<td>' . $e($it['checking_item']) . '</td>'
            . '<td class="c">' . $e($std ?: '-') . '</td>'
            . '<td class="' . ($isng ? 'ng' : 'ok') . '">' . $e(($it['actual_result'] ?? '') !== '' ? $it['actual_result'] : '-') . '</td>'
            . '<td class="c">' . $e($it['consumable_item'] ?: '-') . '</td>'
            . '<td class="' . ($edited ? 'edited' : 'c') . '">' . ($edited ? '&#8635; Revised' : '-') . '</td>'
            . '</tr>';
    }
    $eng = $h['no_engine'] ? ' &middot; Eng ' . $e($h['no_engine']) : '';
?>
<div class="page">
    <table class="head">
        <tr>
            <td rowspan="4" class="logo"><?= $logoData ? '<img src="' . $logoData . '" alt="logo">' : '' ?></td>
            <td colspan="3" rowspan="2" class="title"><?= $e($doc_title) ?></td>
            <td class="dl">No. Doc</td><td class="dv"><?= $e($doc_no) ?></td>
        </tr>
        <tr><td class="dl">Revisi</td><td class="dv"><?= $e($doc_rev) ?></td></tr>
        <tr>
            <td colspan="3" rowspan="2" class="month">Bulan : <?= $e($monthLabel) ?></td>
            <td class="dl">Tgl</td><td class="dv"><?= $e($doc_date) ?></td>
        </tr>
        <tr><td class="dl">Halaman</td><td class="dv"><?= $pageNo ?> / <?= (int)$total ?></td></tr>
    </table>

    <div class="banner">
        Checker: <?= $e($h['checker'] ?? '-') ?> &nbsp;|&nbsp;
        Model: <?= $e($h['model_name']) . $eng ?> &nbsp;|&nbsp;
        Tanggal Cek: <?= $e($h['tanggal']) ?> &nbsp;|&nbsp;
        Submitted: <?= $e($h['created_at']) ?>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:34px;">No</th><th style="width:70px;">Model</th><th>Checking Item</th>
                <th style="width:130px;">Standard</th><th style="width:60px;">Actual</th>
                <th style="width:80px;">Consumable</th><th style="width:78px;">Diedit</th>
            </tr>
        </thead>
        <tbody>
            <?= $items ? $body : '<tr><td colspan="7" class="c">Tidak ada item.</td></tr>' ?>
        </tbody>
    </table>
    <div class="summary">Summary: <?= $n ?> item &mdash; Checked: <?= $n ?> &nbsp; OK: <?= $ok ?> &nbsp; NG: <?= $ng ?></div>

    <div class="signs">
        <div class="box">Checked By,<div class="line">(&nbsp;&nbsp; <?= $e($h['checker'] ?? '') ?> &nbsp;&nbsp;)</div></div>
        <div class="box">Approved By,<div class="line">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</div></div>
    </div>
</div>
<?php endforeach; ?>

<?php if (($_GET['print'] ?? '') === '1' && $engines): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>

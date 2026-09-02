<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/excel_lib.php';
require_once __DIR__ . '/includes/export_builders.php';
require_login();
$pdo = get_db();

$section_id = (int)($_GET['section_id'] ?? 0);
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year'] ?? date('Y'));
$month = max(1, min(12, $month));

$stmt = $pdo->prepare(
    'SELECT s.*, d.name AS dept_name FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id WHERE s.id = ? AND s.is_active = 1'
);
$stmt->execute([$section_id]);
$section = $stmt->fetch();
if (!$section) { http_response_code(404); exit('Section tidak ditemukan.'); }

$title = trim((string)($section['doc_title'] ?? '')) !== ''
    ? $section['doc_title']
    : strtoupper(trim($section['name'])) . ' MONTHLY CHECK SHEET REPORT';
$monthLabel = excel_month_label($month, $year);
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Logo: Excel can't render base64 data URIs (shows "linked image"), so point
// to the logo by an absolute URL on this server — Excel fetches it on open.
$logoTag = '';
foreach (['png', 'jpg', 'jpeg'] as $ext) {
    if (is_file(__DIR__ . '/assets/img/logo.' . $ext)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $logoUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $baseDir . '/assets/img/logo.' . $ext;
        $logoTag = '<img src="' . $e($logoUrl) . '" height="36">';
        break;
    }
}

// Section-specific body + its column count.
$built  = export_section_blocks($pdo, $section, $month, $year);
$COLS   = max(5, (int)$built['cols']);   // keep room for logo + title + doc box
$blocks = $built['blocks'];

$fLeft  = intdiv($COLS - 1, 2);
$fRight = $COLS - 1 - $fLeft;

$fname = preg_replace('/[^A-Za-z0-9_\- ]/', '', $section['name']) . ' ' . $monthLabel . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8">
<style>
    td { font-family: Calibri, Arial, sans-serif; font-size: 10.5pt; vertical-align: middle; mso-number-format:"\@"; }
    .b { border: 1px solid #b7b7b7; }
    .bold { font-weight: bold; }
    .center { text-align: center; }
    .title { font-size: 13pt; font-weight: bold; text-align: center; }
    .month { font-size: 10pt; font-weight: bold; text-align: center; }
    .logobox { border: 1px solid #000; text-align: center; }
    .docb-label { border: 1px solid #000; font-size: 9.5pt; font-weight: bold; white-space: nowrap; }
    .docb-val { border: 1px solid #000; font-size: 9.5pt; }
    .banner { background: #2c3e50; color: #ffffff; font-weight: bold; padding: 6px; }
    .hdr { background: #1a9e7a; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #148f6e; }
    .ok { background: #e8f7ee; color: #1f7a3d; font-weight: bold; }
    .ng { background: #fdecea; color: #c0392b; font-weight: bold; }
    .summary { background: #f2f2f2; font-style: italic; }
    .sign { height: 60px; vertical-align: bottom; font-weight: bold; }
</style>
</head>
<body>
<!-- Fixed 6-column branded header (same layout for every section) -->
<table cellspacing="0" cellpadding="3" style="border-collapse:collapse;">
    <colgroup>
        <col style="width:92px;"><col style="width:70px;"><col style="width:70px;">
        <col style="width:175px;"><col style="width:80px;"><col style="width:150px;">
    </colgroup>
    <tr>
        <td rowspan="4" class="logobox"><?= $logoTag ?></td>
        <td colspan="3" rowspan="2" class="title"><?= $e($title) ?></td>
        <td class="docb-label">No. Doc</td><td class="docb-val"><?= $e($section['doc_no']) ?></td>
    </tr>
    <tr><td class="docb-label">Revisi</td><td class="docb-val"><?= $e($section['doc_rev']) ?></td></tr>
    <tr>
        <td colspan="3" rowspan="2" class="month">Bulan : <?= $e($monthLabel) ?></td>
        <td class="docb-label">Tgl</td><td class="docb-val"><?= $e($section['doc_date']) ?></td>
    </tr>
    <tr><td class="docb-label">Halaman</td><td class="docb-val">1 / 1</td></tr>
</table>

<br>

<!-- Data table (columns per section) -->
<table cellspacing="0" cellpadding="3" style="border-collapse:collapse;">
    <?= $blocks ?>
    <tr><td colspan="<?= $COLS ?>" style="height:14px;"></td></tr>
    <tr>
        <td colspan="<?= $fLeft ?>" class="center bold">Checked By,</td>
        <td></td>
        <td colspan="<?= $fRight ?>" class="center bold">Approved By,</td>
    </tr>
    <tr>
        <td colspan="<?= $fLeft ?>" class="sign center">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
        <td></td>
        <td colspan="<?= $fRight ?>" class="sign center">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
    </tr>
</table>
</body>
</html>
<?php
exit;

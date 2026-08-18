<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$tanggal = $_GET['tanggal'] ?? '';

if (!$department_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    echo json_encode(['error' => 'department_id and a valid tanggal are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM t_fopump_header WHERE department_id = ? AND tanggal = ?');
$stmt->execute([$department_id, $tanggal]);
$header = $stmt->fetch() ?: null;

$lines = [];
if ($header) {
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_line WHERE header_id = ? ORDER BY line_no');
    $stmt->execute([$header['id']]);
    $lines = $stmt->fetchAll();
}

// Cumulative totals for every day strictly BEFORE this date within the same
// calendar month (resets naturally every month) — the current day's own
// total gets added client-side once the user starts typing quantities.
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(l.production_qty), 0) AS prod,
        COALESCE(SUM(l.assembly_qty), 0) AS assy,
        COALESCE(SUM(l.export_qty), 0) AS exp
     FROM t_fopump_header h
     JOIN t_fopump_line l ON l.header_id = h.id
     WHERE h.department_id = ?
       AND YEAR(h.tanggal) = YEAR(?) AND MONTH(h.tanggal) = MONTH(?)
       AND h.tanggal < ?"
);
$stmt->execute([$department_id, $tanggal, $tanggal, $tanggal]);
$priorAccum = $stmt->fetch();

echo json_encode([
    'header' => $header,
    'lines' => $lines,
    'prior_accum' => [
        'production' => (int)$priorAccum['prod'],
        'assembly' => (int)$priorAccum['assy'],
        'export' => (int)$priorAccum['exp'],
    ],
]);

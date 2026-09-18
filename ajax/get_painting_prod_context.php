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

$stmt = $pdo->prepare('SELECT * FROM t_painting_prod_header WHERE department_id = ? AND tanggal = ?');
$stmt->execute([$department_id, $tanggal]);
$header = $stmt->fetch() ?: null;

$lines = [];
if ($header) {
    $stmt = $pdo->prepare('SELECT * FROM t_painting_prod_line WHERE header_id = ? ORDER BY line_no');
    $stmt->execute([$header['id']]);
    $lines = $stmt->fetchAll();
}

// Cumulative totals for every day strictly BEFORE this date within the same
// calendar month (resets naturally every month) — the current day's own total
// gets added client-side once the user starts typing quantities.
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(l.cb), 0)     AS cb,
        COALESCE(SUM(l.fot), 0)    AS fot,
        COALESCE(SUM(l.fw), 0)     AS fw,
        COALESCE(SUM(l.part), 0)   AS part,
        COALESCE(SUM(l.others), 0) AS others
     FROM t_painting_prod_header h
     JOIN t_painting_prod_line l ON l.header_id = h.id
     WHERE h.department_id = ?
       AND YEAR(h.tanggal) = YEAR(?) AND MONTH(h.tanggal) = MONTH(?)
       AND h.tanggal < ?"
);
$stmt->execute([$department_id, $tanggal, $tanggal, $tanggal]);
$prior = $stmt->fetch();

echo json_encode([
    'header' => $header,
    'lines' => $lines,
    'prior_accum' => [
        'cb' => (int)$prior['cb'],
        'fot' => (int)$prior['fot'],
        'fw' => (int)$prior['fw'],
        'part' => (int)$prior['part'],
        'others' => (int)$prior['others'],
    ],
]);

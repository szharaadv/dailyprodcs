<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$department_id || $month < 1 || $month > 12 || !$year) {
    echo json_encode(['error' => 'department_id, month and year are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM t_fopump_reject_header WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $month, $year]);
$header = $stmt->fetch() ?: null;

$lines = [];
if ($header) {
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_reject_line WHERE header_id = ? ORDER BY line_no');
    $stmt->execute([$header['id']]);
    $lines = $stmt->fetchAll();
}

echo json_encode(['header' => $header, 'lines' => $lines]);

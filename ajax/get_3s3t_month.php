<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$line = trim((string)($_GET['line'] ?? ''));
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$department_id || !$month || !$year) {
    echo json_encode(['items' => [], 'header' => null, 'details' => new stdClass()]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, category, item_pemeriksaan, standar_kriteria
     FROM m_3s3t_item WHERE department_id = ? AND is_active = 1 ORDER BY sort_order, id'
);
$stmt->execute([$department_id]);
$items = $stmt->fetchAll();

$header = null;
if ($line !== '') {
    $stmt = $pdo->prepare('SELECT * FROM t_3s3t_header WHERE department_id = ? AND line = ? AND month = ? AND year = ?');
    $stmt->execute([$department_id, $line, $month, $year]);
    $header = $stmt->fetch() ?: null;
}

$details = new stdClass();
if ($header) {
    $stmt = $pdo->prepare('SELECT * FROM t_3s3t_detail WHERE header_id = ?');
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $d) {
        $details->{$d['item_id']} = $d;
    }
}

echo json_encode(['items' => $items, 'header' => $header, 'details' => $details]);

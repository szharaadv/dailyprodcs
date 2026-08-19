<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$line = trim((string)($_GET['line'] ?? ''));
$item_id = (int)($_GET['item_id'] ?? 0);

if (!$department_id || $line === '' || !$item_id) {
    echo json_encode(['history' => []]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT h.month, h.year, d.remarks
     FROM t_3s3t_detail d
     JOIN t_3s3t_header h ON h.id = d.header_id
     WHERE h.department_id = ? AND h.line = ? AND d.item_id = ?
       AND d.remarks IS NOT NULL AND d.remarks <> ""
     ORDER BY h.year DESC, h.month DESC
     LIMIT 12'
);
$stmt->execute([$department_id, $line, $item_id]);
$history = $stmt->fetchAll();

echo json_encode(['history' => $history]);

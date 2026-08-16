<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$condition_id = (int)($_GET['condition_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT id, checking_item, metode_pengecekan, standard_min, standard_max,
            tank_tube, satuan, actual_input_type, actual_options, category_options
     FROM m_checklist_item
     WHERE condition_id = ? AND is_active = 1
     ORDER BY sort_order, id'
);
$stmt->execute([$condition_id]);
$items = $stmt->fetchAll();

echo json_encode(['items' => $items]);

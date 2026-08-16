<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$model_id = (int)($_GET['model_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT id, checking_item, standard, standard_min, standard_max
     FROM m_assy_checklist_item
     WHERE model_id = ? AND is_active = 1
     ORDER BY sort_order, id'
);
$stmt->execute([$model_id]);
$items = $stmt->fetchAll();

echo json_encode(['items' => $items]);

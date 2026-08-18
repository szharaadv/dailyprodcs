<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$model_id = (int)($_GET['model_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, name, fop_code, part_no FROM m_fopump_check_model WHERE id = ?');
$stmt->execute([$model_id]);
$model = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT id, checking_item, standard, result_type, expected_value
     FROM m_fopump_check_item
     WHERE model_id = ? AND is_active = 1
     ORDER BY sort_order, id'
);
$stmt->execute([$model_id]);
$items = $stmt->fetchAll();

echo json_encode(['items' => $items, 'model' => $model ?: null]);

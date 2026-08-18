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

$header = null;
$samples = [];
$values = [];
if ($model_id) {
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_check_header WHERE model_id = ?');
    $stmt->execute([$model_id]);
    $header = $stmt->fetch() ?: null;

    if ($header) {
        $stmt = $pdo->prepare('SELECT id, sample_no FROM t_fopump_check_sample WHERE header_id = ? ORDER BY sort_order, id');
        $stmt->execute([$header['id']]);
        $samples = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT checklist_item_id, sample_id, actual_result FROM t_fopump_check_detail WHERE header_id = ?');
        $stmt->execute([$header['id']]);
        foreach ($stmt->fetchAll() as $d) {
            $values[$d['checklist_item_id']][$d['sample_id']] = $d['actual_result'];
        }
    }
}

echo json_encode(['items' => $items, 'model' => $model ?: null, 'header' => $header, 'samples' => $samples, 'values' => $values]);

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$model_id = (int)($_GET['model_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, name, fop_code, part_no FROM m_fopump_check_model WHERE id = ?');
$stmt->execute([$model_id]);
$model = $stmt->fetch();

// Checking items are a single GLOBAL master list (model_id IS NULL), shared by
// every model. The two "Label check" rows carry their value from the selected
// model itself (standard_source), so each model shows its own Part No / Model
// code without any per-model item setup.
$stmt = $pdo->prepare(
    'SELECT id, checking_item, standard, standard_source, result_type, expected_value
     FROM m_fopump_check_item
     WHERE model_id IS NULL AND is_active = 1
     ORDER BY sort_order, id'
);
$stmt->execute();
$items = $stmt->fetchAll();

foreach ($items as &$it) {
    if ($it['standard_source'] === 'part_no') {
        $it['standard'] = $model['part_no'] ?? null;
    } elseif ($it['standard_source'] === 'fop_code') {
        $it['standard'] = $model['fop_code'] ?? null;
    }
}
unset($it);

$header = null;
$samples = [];
$values = [];
if ($model_id) {
    $stmt = $pdo->prepare(
        'SELECT h.*, c.name AS checker_name, f.name AS foreman_name, s.name AS supervisor_name
         FROM t_fopump_check_header h
         LEFT JOIN m_user c ON c.id = h.checker_id
         LEFT JOIN m_user f ON f.id = h.foreman_id
         LEFT JOIN m_user s ON s.id = h.supervisor_id
         WHERE h.model_id = ? AND h.tanggal = CURDATE()'
    );
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

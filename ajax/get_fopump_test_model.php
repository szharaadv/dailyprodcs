<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$model_id = (int)($_GET['model_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, name, fop_code, standard_cc_sec, rpm, master_test, default_shim FROM m_fopump_test_model WHERE id = ?');
$stmt->execute([$model_id]);
$model = $stmt->fetch();

$header = null;
$rows = [];
if ($model_id) {
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_test_header WHERE model_id = ?');
    $stmt->execute([$model_id]);
    $header = $stmt->fetch() ?: null;

    if ($header) {
        $stmt = $pdo->prepare('SELECT rpm, cc_sec, shim FROM t_fopump_test_row WHERE header_id = ? ORDER BY sort_order, id');
        $stmt->execute([$header['id']]);
        $rows = $stmt->fetchAll();
    }
}

echo json_encode(['model' => $model ?: null, 'header' => $header, 'rows' => $rows]);

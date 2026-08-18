<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$model_id = (int)($_GET['model_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, name, fop_code, standard_cc_sec, rpm, master_test, default_shim FROM m_fopump_test_model WHERE id = ?');
$stmt->execute([$model_id]);
$model = $stmt->fetch();

echo json_encode(['model' => $model ?: null]);

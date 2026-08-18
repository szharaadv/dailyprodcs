<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$year = (int)($_GET['year'] ?? date('Y'));

$stmt = $pdo->prepare("SELECT tanggal, label, is_workday FROM m_holiday WHERE YEAR(tanggal) = ?");
$stmt->execute([$year]);

$out = [];
foreach ($stmt->fetchAll() as $r) {
    $out[$r['tanggal']] = [
        'label' => $r['label'],
        'is_workday' => (bool)$r['is_workday'],
    ];
}

echo json_encode($out);

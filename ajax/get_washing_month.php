<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$department_id || !$month || !$year) {
    echo json_encode(['rows' => new stdClass()]);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_washing_header WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $month, $year]);
$header_id = $stmt->fetchColumn();

$rows = new stdClass();
if ($header_id) {
    $stmt = $pdo->prepare('SELECT * FROM t_washing_detail WHERE header_id = ?');
    $stmt->execute([$header_id]);
    foreach ($stmt->fetchAll() as $d) {
        $rows->{$d['day']} = [
            'ganti_air' => $d['ganti_air'],
            'temperatur_air' => $d['temperatur_air'],
            'penambahan_gildaon' => $d['penambahan_gildaon'],
            'total_acid' => $d['total_acid'],
            'checker_id' => $d['checker_id'],
            'control_id' => $d['control_id'],
        ];
    }
}

echo json_encode(['rows' => $rows]);

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/calendar_lib.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$department_id = (int)($input['department_id'] ?? 0);
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);
$day = (int)($input['day'] ?? 0);
$field = $input['field'] ?? '';
$value = trim((string)($input['value'] ?? ''));

$allowed = ['ganti_air', 'temperatur_air', 'penambahan_gildaon', 'total_acid', 'checker_id', 'control_id'];

if (!$department_id || $month < 1 || $month > 12 || !$year || $day < 1 || $day > 31 || !in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}
if (!is_today_ymd($day, $month, $year)) {
    http_response_code(409);
    echo json_encode(['error' => 'Tanggal ini sudah lewat/belum terjadi dan tidak bisa diubah.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_washing_header WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $month, $year]);
$header_id = $stmt->fetchColumn();

if (!$header_id) {
    $ins = $pdo->prepare('INSERT INTO t_washing_header (department_id, month, year) VALUES (?, ?, ?)');
    $ins->execute([$department_id, $month, $year]);
    $header_id = (int)$pdo->lastInsertId();
}

$isIdField = str_ends_with($field, '_id');
$stored = $isIdField ? ($value !== '' ? (int)$value : null) : ($value !== '' ? $value : null);

$stmt = $pdo->prepare(
    "INSERT INTO t_washing_detail (header_id, day, `$field`) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `$field` = VALUES(`$field`)"
);
$stmt->execute([$header_id, $day, $stored]);

echo json_encode(['ok' => true, 'header_id' => $header_id]);

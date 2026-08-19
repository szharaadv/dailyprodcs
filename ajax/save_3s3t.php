<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/calendar_lib.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$department_id = (int)($input['department_id'] ?? 0);
$line = trim((string)($input['line'] ?? ''));
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);

if (!$department_id || $line === '' || !$month || !$year || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'department_id, line, month and year are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_3s3t_header WHERE department_id = ? AND line = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $line, $month, $year]);
$header_id = $stmt->fetchColumn();

if (!$header_id) {
    $ins = $pdo->prepare('INSERT INTO t_3s3t_header (department_id, line, month, year) VALUES (?, ?, ?, ?)');
    $ins->execute([$department_id, $line, $month, $year]);
    $header_id = (int)$pdo->lastInsertId();
}

// Header-level field (Operator).
if (isset($input['field'])) {
    $allowed = ['operator_id' => true];
    $field = $input['field'];
    if (!isset($allowed[$field])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid field.']);
        exit;
    }
    if (!is_current_period($month, $year)) {
        http_response_code(409);
        echo json_encode(['error' => 'Bulan ini sudah lewat dan tidak bisa diubah lagi.']);
        exit;
    }
    $value = trim((string)($input['value'] ?? ''));
    $value = $value !== '' ? (int)$value : null;
    $stmt = $pdo->prepare("UPDATE t_3s3t_header SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $header_id]);
    echo json_encode(['ok' => true, 'header_id' => $header_id]);
    exit;
}

// Item row cell (week1..week5, remarks, pic_id).
$item_id = (int)($input['item_id'] ?? 0);
$field = (string)($input['cell_field'] ?? '');
$allowedCellFields = ['week1', 'week2', 'week3', 'week4', 'week5', 'remarks', 'pic_id'];

if (!$item_id || !in_array($field, $allowedCellFields, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'item_id and a valid cell_field are required.']);
    exit;
}

// week1..week5 are only writable for the *current* week of the *current*
// month; remarks/pic_id are writable for the whole current month.
if (!is_current_period($month, $year)) {
    http_response_code(409);
    echo json_encode(['error' => 'Bulan ini sudah lewat dan tidak bisa diubah lagi.']);
    exit;
}
if (preg_match('/^week([1-5])$/', $field, $m) && (int)$m[1] !== current_week_of_month()) {
    http_response_code(409);
    echo json_encode(['error' => 'Minggu ini bukan minggu berjalan dan tidak bisa diubah.']);
    exit;
}

$value = trim((string)($input['value'] ?? ''));
if ($field === 'pic_id') {
    $value = $value !== '' ? (int)$value : null;
} else {
    $value = $value !== '' ? $value : null;
}

$stmt = $pdo->prepare('SELECT id FROM t_3s3t_detail WHERE header_id = ? AND item_id = ?');
$stmt->execute([$header_id, $item_id]);
$detailId = $stmt->fetchColumn();

if ($detailId) {
    $stmt = $pdo->prepare("UPDATE t_3s3t_detail SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $detailId]);
} else {
    $stmt = $pdo->prepare("INSERT INTO t_3s3t_detail (header_id, item_id, `$field`) VALUES (?, ?, ?)");
    $stmt->execute([$header_id, $item_id, $value]);
}

echo json_encode(['ok' => true, 'header_id' => $header_id]);

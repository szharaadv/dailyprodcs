<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$department_id = (int)($input['department_id'] ?? 0);
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);

if (!$department_id || !$month || !$year || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'department_id, month and year are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_paint_viscosity_header WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $month, $year]);
$header_id = $stmt->fetchColumn();

if (!$header_id) {
    $ins = $pdo->prepare('INSERT INTO t_paint_viscosity_header (department_id, month, year) VALUES (?, ?, ?)');
    $ins->execute([$department_id, $month, $year]);
    $header_id = (int)$pdo->lastInsertId();
}

// Header-level field (Checker / Foreman / Supervisor / Catatan).
if (isset($input['field'])) {
    $allowed = ['foreman_id' => true, 'supervisor_id' => true, 'notes' => true];
    $field = $input['field'];
    if (!isset($allowed[$field])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid field.']);
        exit;
    }
    $value = trim((string)($input['value'] ?? ''));
    if ($field === 'notes') {
        $value = $value !== '' ? $value : null;
    } else {
        $value = $value !== '' ? (int)$value : null;
    }
    $stmt = $pdo->prepare("UPDATE t_paint_viscosity_header SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $header_id]);
    echo json_encode(['ok' => true, 'header_id' => $header_id]);
    exit;
}

// Result cell (Item + Day).
$item_id = (int)($input['item_id'] ?? 0);
$day = (int)($input['day'] ?? 0);
$actual_result = trim((string)($input['actual_result'] ?? ''));

if (!$item_id || $day < 1 || $day > 31) {
    http_response_code(400);
    echo json_encode(['error' => 'item_id and a valid day are required.']);
    exit;
}

if ($actual_result === '') {
    $pdo->prepare('DELETE FROM t_paint_viscosity_detail WHERE header_id = ? AND item_id = ? AND day = ?')->execute([$header_id, $item_id, $day]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO t_paint_viscosity_detail (header_id, item_id, day, actual_result) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE actual_result = VALUES(actual_result)'
    );
    $stmt->execute([$header_id, $item_id, $day, $actual_result]);
}

echo json_encode(['ok' => true, 'header_id' => $header_id]);

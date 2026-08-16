<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$bakeoven_id = (int)($input['bakeoven_id'] ?? 0);
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);

if (!$bakeoven_id || !$month || !$year || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'bakeoven_id, month and year are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_bakeoven_header WHERE bakeoven_id = ? AND month = ? AND year = ?');
$stmt->execute([$bakeoven_id, $month, $year]);
$header_id = $stmt->fetchColumn();

if (!$header_id) {
    $ins = $pdo->prepare('INSERT INTO t_bakeoven_header (bakeoven_id, month, year) VALUES (?, ?, ?)');
    $ins->execute([$bakeoven_id, $month, $year]);
    $header_id = (int)$pdo->lastInsertId();
}

// Header-level field (Asst. Foreman / Foreman / Supervisor / Keterangan).
if (isset($input['field'])) {
    $allowed = ['asst_foreman_id' => true, 'foreman_id' => true, 'supervisor_id' => true, 'notes' => true];
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
    $stmt = $pdo->prepare("UPDATE t_bakeoven_header SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $header_id]);
    echo json_encode(['ok' => true, 'header_id' => $header_id]);
    exit;
}

// Daily Paraf (who checked that day).
if (isset($input['paraf_day'])) {
    $day = (int)$input['paraf_day'];
    $user_id = trim((string)($input['user_id'] ?? ''));
    if ($day < 1 || $day > 31) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid day.']);
        exit;
    }
    if ($user_id === '') {
        $pdo->prepare('DELETE FROM t_bakeoven_paraf WHERE header_id = ? AND day = ?')->execute([$header_id, $day]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_bakeoven_paraf (header_id, day, user_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );
        $stmt->execute([$header_id, $day, (int)$user_id]);
    }
    echo json_encode(['ok' => true, 'header_id' => $header_id]);
    exit;
}

// Temperature cell (Time + Day).
$time_id = (int)($input['time_id'] ?? 0);
$day = (int)($input['day'] ?? 0);
$actual_temp = trim((string)($input['actual_temp'] ?? ''));

if (!$time_id || $day < 1 || $day > 31) {
    http_response_code(400);
    echo json_encode(['error' => 'time_id and a valid day are required.']);
    exit;
}

if ($actual_temp === '') {
    $pdo->prepare('DELETE FROM t_bakeoven_detail WHERE header_id = ? AND time_id = ? AND day = ?')->execute([$header_id, $time_id, $day]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO t_bakeoven_detail (header_id, time_id, day, actual_temp) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE actual_temp = VALUES(actual_temp)'
    );
    $stmt->execute([$header_id, $time_id, $day, $actual_temp]);
}

echo json_encode(['ok' => true, 'header_id' => $header_id]);

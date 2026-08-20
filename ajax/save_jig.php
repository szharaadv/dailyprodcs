<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/calendar_lib.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$jig_id = (int)($input['jig_id'] ?? 0);
$month = (int)($input['month'] ?? 0);
$year = (int)($input['year'] ?? 0);

if (!$jig_id || !$month || !$year || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'jig_id, month and year are required.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM t_jigheader WHERE jig_id = ? AND month = ? AND year = ?');
$stmt->execute([$jig_id, $month, $year]);
$header_id = $stmt->fetchColumn();

if (!$header_id) {
    $ins = $pdo->prepare('INSERT INTO t_jigheader (jig_id, month, year) VALUES (?, ?, ?)');
    $ins->execute([$jig_id, $month, $year]);
    $header_id = (int)$pdo->lastInsertId();
}
$header_id = (int)$header_id;
$unlocked = has_active_unlock($pdo, 'jig', $header_id);

if (isset($input['field'])) {
    $allowed = ['supervisor_id' => true, 'foreman_id' => true, 'checker_id' => true];
    $field = $input['field'];
    if (!isset($allowed[$field])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid field.']);
        exit;
    }
    if (!$unlocked && !is_current_period($month, $year)) {
        http_response_code(409);
        echo json_encode(['error' => 'Bulan ini sudah lewat dan tidak bisa diubah lagi.']);
        exit;
    }
    $value = trim((string)($input['value'] ?? ''));
    $value = $value !== '' ? (int)$value : null;
    $stmt = $pdo->prepare("UPDATE t_jigheader SET `$field` = ? WHERE id = ?");
    $stmt->execute([$value, $header_id]);
    echo json_encode(['ok' => true, 'header_id' => $header_id]);
    exit;
}

$jig_item_id = (int)($input['jig_item_id'] ?? 0);
$day = (int)($input['day'] ?? 0);
$result = $input['result'] ?? '';

if (!$jig_item_id || $day < 1 || $day > 31 || !in_array($result, ['OK', 'NG'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'jig_item_id, day and a valid result (OK/NG) are required.']);
    exit;
}
if (!$unlocked && !is_today_ymd($day, $month, $year)) {
    $stmt = $pdo->prepare('SELECT 1 FROM t_jig_detail WHERE header_id = ? AND jig_item_id = ? AND day = ?');
    $stmt->execute([$header_id, $jig_item_id, $day]);
    $isEmpty = !$stmt->fetchColumn();
    if (!is_catchup_writable($isEmpty, $day, $month, $year)) {
        http_response_code(409);
        echo json_encode(['error' => 'Tanggal ini sudah lewat/belum terjadi dan tidak bisa diubah.']);
        exit;
    }
}

$stmt = $pdo->prepare(
    'INSERT INTO t_jig_detail (header_id, jig_item_id, day, result) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE result = VALUES(result)'
);
$stmt->execute([$header_id, $jig_item_id, $day, $result]);

echo json_encode(['ok' => true, 'header_id' => $header_id]);

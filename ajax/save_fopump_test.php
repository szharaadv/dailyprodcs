<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$department_id   = (int)($input['department_id'] ?? 0);
$model_id        = (int)($input['model_id'] ?? 0);
$destination     = ($input['destination'] ?? 'local') === 'export' ? 'export' : 'local';
$oil_pressure    = $input['oil_pressure'] ?? null;
$oil_temp        = $input['oil_temp'] ?? null;
$room_temp       = $input['room_temp'] ?? null;
$start_test_time = $input['start_test_time'] ?? null;
$checker_id      = (int)($input['checker_id'] ?? 0);
$foreman_id      = (int)($input['foreman_id'] ?? 0) ?: null;
$supervisor_id   = (int)($input['supervisor_id'] ?? 0) ?: null;
$status          = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$rows            = $input['rows'] ?? [];

if (!$department_id || !$model_id || !$checker_id || empty($rows)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // One record per model: reuse the existing header for this model if
    // there is one, regardless of what header_id the client sent.
    $stmt = $pdo->prepare('SELECT id FROM t_fopump_test_header WHERE model_id = ?');
    $stmt->execute([$model_id]);
    $header_id = $stmt->fetchColumn() ?: null;

    $params = [
        $department_id, $model_id, $destination,
        $oil_pressure ?: null, $oil_temp ?: null, $room_temp ?: null, $start_test_time ?: null,
        $checker_id, $foreman_id, $supervisor_id, $status,
    ];

    if ($header_id) {
        $stmt = $pdo->prepare(
            'UPDATE t_fopump_test_header
             SET department_id=?, model_id=?, destination=?, oil_pressure=?, oil_temp=?, room_temp=?, start_test_time=?,
                 checker_id=?, foreman_id=?, supervisor_id=?, status=?
             WHERE id=?'
        );
        $stmt->execute(array_merge($params, [$header_id]));

        $pdo->prepare('DELETE FROM t_fopump_test_row WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fopump_test_header
             (department_id, model_id, destination, oil_pressure, oil_temp, room_temp, start_test_time, checker_id, foreman_id, supervisor_id, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $header_id = $pdo->lastInsertId();
    }

    $rowStmt = $pdo->prepare('INSERT INTO t_fopump_test_row (header_id, row_no, rpm, cc_sec, shim, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $idx => $r) {
        $rowStmt->execute([$header_id, $idx + 1, $r['rpm'] ?? null, $r['cc_sec'] ?? null, $r['shim'] ?? null, $idx]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

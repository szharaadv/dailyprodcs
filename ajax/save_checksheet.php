<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$header_id      = (int)($input['header_id'] ?? 0);
$tanggal        = $input['tanggal'] ?? date('Y-m-d');
// Validate date; reject invalid or future dates (backdate allowed).
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
    $tanggal = date('Y-m-d');
}
$department_id  = (int)($input['department_id'] ?? 0);
$condition_id   = (int)($input['condition_id'] ?? 0);
$checker_id     = (int)($input['checker_id'] ?? 0);
$jam            = $input['jam'] ?? null;
$shift_id       = (int)($input['shift_id'] ?? 0);
$status         = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$rows           = $input['rows'] ?? [];

if (!$tanggal || !$department_id || !$condition_id || !$checker_id || !$jam || !$shift_id || empty($rows)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($header_id) {
        $stmt = $pdo->prepare(
            'UPDATE t_checksheet_header
             SET tanggal = ?, department_id = ?, condition_id = ?, checker_id = ?, jam = ?, shift_id = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([$tanggal, $department_id, $condition_id, $checker_id, $jam, $shift_id, $status, $header_id]);

        $pdo->prepare('DELETE FROM t_checksheet_detail WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_checksheet_header (tanggal, department_id, condition_id, checker_id, jam, shift_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tanggal, $department_id, $condition_id, $checker_id, $jam, $shift_id, $status]);
        $header_id = $pdo->lastInsertId();
    }

    $detailStmt = $pdo->prepare(
        'INSERT INTO t_checksheet_detail (header_id, checklist_item_id, actual_result, category)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($rows as $row) {
        $detailStmt->execute([
            $header_id,
            (int)$row['checklist_item_id'],
            $row['actual_result'] ?? null,
            $row['category'] ?? null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$header_id = (int)($input['header_id'] ?? 0);
// No backdating, no future-dating — always today, never the client's value.
$tanggal = date('Y-m-d');
$department_id = (int)($input['department_id'] ?? 0);
$employee = $input['employee_count'] ?? null;
$workingMinutes = $input['working_minutes'] ?? null;
$shift = trim((string)($input['shift_label'] ?? ''));
$operator = $input['operator_id'] ?? null;
$foreman = $input['foreman_id'] ?? null;
$supervisor = $input['supervisor_id'] ?? null;
$convProd = $input['convert_production'] ?? null;
$convAssy = $input['convert_assembly'] ?? null;
$convExp = $input['convert_export'] ?? null;
$status = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$lines = $input['lines'] ?? [];

if (!$department_id) {
    http_response_code(400);
    echo json_encode(['error' => 'department_id is required.']);
    exit;
}

function nz($v) {
    if ($v === null || $v === '') return null;
    return $v;
}

try {
    $pdo->beginTransaction();

    if (!$header_id) {
        // Only reuse an existing header for today if it's still a draft —
        // a submitted one is locked and must not be silently overwritten.
        $stmt = $pdo->prepare('SELECT id, status FROM t_fopump_header WHERE department_id = ? AND tanggal = ?');
        $stmt->execute([$department_id, $tanggal]);
        $existing = $stmt->fetch();
        if ($existing && $existing['status'] === 'submitted') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Laporan untuk hari ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }
        $header_id = $existing ? (int)$existing['id'] : 0;
    }

    $params = [
        $tanggal, $department_id,
        nz($employee) !== null ? (int)$employee : null,
        nz($workingMinutes) !== null ? (int)$workingMinutes : null,
        $shift !== '' ? $shift : null,
        nz($operator) !== null ? (int)$operator : null,
        nz($foreman) !== null ? (int)$foreman : null,
        nz($supervisor) !== null ? (int)$supervisor : null,
        nz($convProd) !== null ? (int)$convProd : null,
        nz($convAssy) !== null ? (int)$convAssy : null,
        nz($convExp) !== null ? (int)$convExp : null,
        $status,
    ];

    if ($header_id) {
        // Once submitted, a record is locked — only a still-draft record
        // can be updated (this is how a draft transitions to submitted).
        $stmt = $pdo->prepare(
            'UPDATE t_fopump_header SET tanggal=?, department_id=?, employee_count=?, working_minutes=?, shift_label=?,
                operator_id=?, foreman_id=?, supervisor_id=?, convert_production=?, convert_assembly=?, convert_export=?, status=?
             WHERE id=? AND status="draft"'
        );
        $stmt->execute(array_merge($params, [$header_id]));

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Checksheet ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }
        $pdo->prepare('DELETE FROM t_fopump_line WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fopump_header (tanggal, department_id, employee_count, working_minutes, shift_label,
                operator_id, foreman_id, supervisor_id, convert_production, convert_assembly, convert_export, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $header_id = (int)$pdo->lastInsertId();
    }

    $insL = $pdo->prepare(
        'INSERT INTO t_fopump_line (header_id, line_no, production_model, production_qty, assembly_model, assembly_qty, export_model, export_qty)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($lines as $line) {
        $prodModel = trim((string)($line['production_model'] ?? ''));
        $assyModel = trim((string)($line['assembly_model'] ?? ''));
        $expModel = trim((string)($line['export_model'] ?? ''));
        if ($prodModel === '' && $assyModel === '' && $expModel === '') continue;
        $insL->execute([
            $header_id, (int)$line['line_no'],
            $prodModel !== '' ? $prodModel : null, nz($line['production_qty'] ?? null) !== null ? (int)$line['production_qty'] : null,
            $assyModel !== '' ? $assyModel : null, nz($line['assembly_qty'] ?? null) !== null ? (int)$line['assembly_qty'] : null,
            $expModel !== '' ? $expModel : null, nz($line['export_qty'] ?? null) !== null ? (int)$line['export_qty'] : null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$header_id     = (int)($input['header_id'] ?? 0);
$department_id = (int)($input['department_id'] ?? 0);
$month         = (int)($input['month'] ?? 0);
$year          = (int)($input['year'] ?? 0);
$target        = $input['target'] ?? null;
$status        = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$lines         = $input['lines'] ?? [];

if (!$department_id || $month < 1 || $month > 12 || !$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

// Only keep rows where the user actually entered something.
$lines = array_values(array_filter($lines, function ($l) {
    return trim($l['model'] ?? '') !== '' || trim((string)($l['quantity'] ?? '')) !== '' || trim($l['remarks'] ?? '') !== '';
}));

try {
    $pdo->beginTransaction();

    $params = [$department_id, $month, $year, ($target !== null && $target !== '') ? (int)$target : null, $status];

    if ($header_id) {
        $stmt = $pdo->prepare('UPDATE t_fopump_reject_header SET department_id=?, month=?, year=?, target=?, status=? WHERE id=?');
        $stmt->execute(array_merge($params, [$header_id]));
        $pdo->prepare('DELETE FROM t_fopump_reject_line WHERE header_id = ?')->execute([$header_id]);
    } else {
        // A header may already exist for this department+month+year (created
        // on a previous save); reuse it instead of violating the unique key.
        $stmt = $pdo->prepare('SELECT id FROM t_fopump_reject_header WHERE department_id = ? AND month = ? AND year = ?');
        $stmt->execute([$department_id, $month, $year]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $header_id = $existingId;
            $stmt = $pdo->prepare('UPDATE t_fopump_reject_header SET target=?, status=? WHERE id=?');
            $stmt->execute([$params[3], $status, $header_id]);
            $pdo->prepare('DELETE FROM t_fopump_reject_line WHERE header_id = ?')->execute([$header_id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO t_fopump_reject_header (department_id, month, year, target, status) VALUES (?,?,?,?,?)');
            $stmt->execute($params);
            $header_id = $pdo->lastInsertId();
        }
    }

    $lineStmt = $pdo->prepare('INSERT INTO t_fopump_reject_line (header_id, line_no, model, quantity, remarks) VALUES (?, ?, ?, ?, ?)');
    foreach ($lines as $idx => $l) {
        $qty = trim((string)($l['quantity'] ?? ''));
        $lineStmt->execute([
            $header_id,
            $idx + 1,
            trim($l['model'] ?? '') ?: null,
            $qty !== '' ? (int)$qty : null,
            trim($l['remarks'] ?? '') ?: null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

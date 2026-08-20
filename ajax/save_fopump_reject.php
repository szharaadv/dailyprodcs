<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$header_id     = (int)($input['header_id'] ?? 0);
$department_id = (int)($input['department_id'] ?? 0);
// No backdating, no future-dating — this log has no day, only month/year,
// so the rule maps to "current month only" — unless this record has an
// Admin-approved edit-request unlock, in which case we keep its original
// month/year instead.
$month         = (int) date('n');
$year          = (int) date('Y');
$unlockedEdit  = $header_id && has_active_unlock($pdo, 'fopump_reject', $header_id);
if ($unlockedEdit) {
    $stmt = $pdo->prepare('SELECT month, year FROM t_fopump_reject_header WHERE id = ?');
    $stmt->execute([$header_id]);
    if ($orig = $stmt->fetch()) {
        $month = (int)$orig['month'];
        $year = (int)$orig['year'];
    }
}
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
        // Once submitted, a record is locked — only a still-draft record
        // can be updated (this is how a draft transitions to submitted),
        // unless an Admin has approved an edit request for this record.
        $lockClause = $unlockedEdit ? '' : ' AND status="draft"';
        $stmt = $pdo->prepare('UPDATE t_fopump_reject_header SET department_id=?, month=?, year=?, target=?, status=? WHERE id=?' . $lockClause);
        $stmt->execute(array_merge($params, [$header_id]));

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Checksheet ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }
        $pdo->prepare('DELETE FROM t_fopump_reject_line WHERE header_id = ?')->execute([$header_id]);
    } else {
        // A draft header may already exist for this department+month+year
        // (created on a previous save); reuse it instead of violating the
        // unique key. A submitted one, however, is locked.
        $stmt = $pdo->prepare('SELECT id, status FROM t_fopump_reject_header WHERE department_id = ? AND month = ? AND year = ?');
        $stmt->execute([$department_id, $month, $year]);
        $existing = $stmt->fetch();

        if ($existing && $existing['status'] === 'submitted') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Laporan reject bulan ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }

        if ($existing) {
            $header_id = $existing['id'];
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

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$header_id      = (int)($input['header_id'] ?? 0);
$tanggal        = $input['tanggal'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $tanggal);
if (!$d || $d->format('Y-m-d') !== $tanggal || $tanggal > date('Y-m-d')) {
    $tanggal = date('Y-m-d');
}
$department_id  = (int)($input['department_id'] ?? 0);
$model_id       = (int)($input['model_id'] ?? 0);
$prod_date_code = $input['prod_date_code'] ?? null;
$checker_id     = (int)($input['checker_id'] ?? 0);
$foreman_id     = (int)($input['foreman_id'] ?? 0) ?: null;
$supervisor_id  = (int)($input['supervisor_id'] ?? 0) ?: null;
$status         = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$sampleNos      = $input['samples'] ?? [];
$rows           = $input['rows'] ?? [];

if (!$tanggal || !$department_id || !$model_id || !$checker_id || empty($rows)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $params = [$tanggal, $department_id, $model_id, $prod_date_code ?: null, $checker_id, $foreman_id, $supervisor_id, $status];

    if ($header_id) {
        $stmt = $pdo->prepare(
            'UPDATE t_fopump_check_header
             SET tanggal=?, department_id=?, model_id=?, prod_date_code=?, checker_id=?, foreman_id=?, supervisor_id=?, status=?
             WHERE id=?'
        );
        $stmt->execute(array_merge($params, [$header_id]));

        // Cascades to t_fopump_check_detail via FK.
        $pdo->prepare('DELETE FROM t_fopump_check_sample WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fopump_check_header
             (tanggal, department_id, model_id, prod_date_code, checker_id, foreman_id, supervisor_id, status)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $header_id = $pdo->lastInsertId();
    }

    $sampleIds = [];
    $sampleStmt = $pdo->prepare('INSERT INTO t_fopump_check_sample (header_id, sample_no, sort_order) VALUES (?, ?, ?)');
    foreach ($sampleNos as $idx => $sampleNo) {
        $sampleStmt->execute([$header_id, $sampleNo !== '' ? $sampleNo : ($idx + 1), $idx]);
        $sampleIds[] = $pdo->lastInsertId();
    }

    $detailStmt = $pdo->prepare(
        'INSERT INTO t_fopump_check_detail (header_id, checklist_item_id, sample_id, actual_result) VALUES (?, ?, ?, ?)'
    );
    foreach ($rows as $row) {
        $actuals = $row['actuals'] ?? [];
        foreach ($sampleIds as $idx => $sampleId) {
            $val = $actuals[$idx] ?? '';
            if ($val === '') continue;
            $detailStmt->execute([$header_id, (int)$row['checklist_item_id'], $sampleId, $val]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

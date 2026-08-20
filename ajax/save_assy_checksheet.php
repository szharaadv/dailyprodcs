<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$header_id        = (int)($input['header_id'] ?? 0);
// No backdating, no future-dating — always today, never the client's value —
// unless this record has an Admin-approved edit-request unlock, in which
// case we keep its original date instead.
$tanggal           = date('Y-m-d');
$unlockedEdit      = $header_id && has_active_unlock($pdo, 'assy', $header_id);
if ($unlockedEdit) {
    $stmt = $pdo->prepare('SELECT tanggal FROM t_assy_header WHERE id = ?');
    $stmt->execute([$header_id]);
    $origTanggal = $stmt->fetchColumn();
    if ($origTanggal) $tanggal = $origTanggal;
}
$department_id     = (int)($input['department_id'] ?? 0);
$model_id          = (int)($input['model_id'] ?? 0);
$checker_id        = (int)($input['checker_id'] ?? 0);
$mark_crank_shaft  = $input['mark_crank_shaft'] ?? null;
$mark_conrod       = $input['mark_conrod'] ?? null;
$mark_fo_pump      = $input['mark_fo_pump'] ?? null;
$no_cyl_block      = $input['no_cyl_block'] ?? null;
$no_engine         = $input['no_engine'] ?? null;
$detail_model      = $input['detail_model'] ?? null;
$status            = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$rows              = $input['rows'] ?? [];

if (!$tanggal || !$department_id || !$model_id || !$checker_id || empty($rows)) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $params = [
        $tanggal, $department_id, $model_id,
        $mark_crank_shaft ?: null, $mark_conrod ?: null, $mark_fo_pump ?: null,
        $no_cyl_block ?: null, $no_engine ?: null, $detail_model ?: null,
        $checker_id, $status,
    ];

    if ($header_id) {
        // Once submitted, a record is locked — only a still-draft record
        // can be updated (this is how a draft transitions to submitted),
        // unless an Admin has approved an edit request for this record.
        $lockClause = $unlockedEdit ? '' : ' AND status="draft"';
        $stmt = $pdo->prepare(
            'UPDATE t_assy_header
             SET tanggal=?, department_id=?, model_id=?, mark_crank_shaft=?, mark_conrod=?, mark_fo_pump=?,
                 no_cyl_block=?, no_engine=?, detail_model=?, checker_id=?, status=?
             WHERE id=?' . $lockClause
        );
        $stmt->execute(array_merge($params, [$header_id]));

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Checksheet ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }

        $pdo->prepare('DELETE FROM t_assy_detail WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_assy_header
             (tanggal, department_id, model_id, mark_crank_shaft, mark_conrod, mark_fo_pump,
              no_cyl_block, no_engine, detail_model, checker_id, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $header_id = $pdo->lastInsertId();
    }

    $detailStmt = $pdo->prepare(
        'INSERT INTO t_assy_detail (header_id, checklist_item_id, actual_result, consumable_item)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($rows as $row) {
        $detailStmt->execute([
            $header_id,
            (int)$row['checklist_item_id'],
            $row['actual_result'] ?? null,
            $row['consumable_item'] ?? null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$header_id = (int)($input['header_id'] ?? 0);
// No backdating, no future-dating — always today, never the client's value —
// unless this record has an Admin-approved edit-request unlock, in which case
// we keep its original date instead.
$tanggal = date('Y-m-d');
$unlockedEdit = $header_id && has_active_unlock($pdo, 'painting_prod', $header_id);
// Updating an existing record must keep that record's OWN date — never rewrite
// it to today. This matters for a draft that belongs to a past date (e.g. a
// missed-day "fill" draft loaded via fill_date): forcing today would move it
// onto today's row and hit the unique department+date key (duplicate entry).
if ($header_id) {
    $stmt = $pdo->prepare('SELECT tanggal FROM t_painting_prod_header WHERE id = ?');
    $stmt->execute([$header_id]);
    $origTanggal = $stmt->fetchColumn();
    if ($origTanggal) $tanggal = $origTanggal;
}
$department_id = (int)($input['department_id'] ?? 0);

// Catch-up on a missed day: a brand-new record (no header_id yet) may be dated
// yesterday, or an Admin-approved older date. The department+date lookup below
// still blocks overwriting a day that was already submitted.
if (!$header_id && !$unlockedEdit) {
    $requestedTanggal = $input['tanggal'] ?? null;
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    // Admin can backdate to ANY valid past-or-today date (for trials); the
    // department+date lookup below still blocks overwriting a submitted day.
    $adminBackdate = is_admin() && $requestedTanggal
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedTanggal)
        && $requestedTanggal <= date('Y-m-d');
    if ($requestedTanggal && ($adminBackdate
        || ($requestedTanggal < date('Y-m-d')
            && ($requestedTanggal === $yesterday
                || ($department_id && has_active_fill_unlock($pdo, 'painting_prod', $department_id, null, $requestedTanggal)))))) {
        $tanggal = $requestedTanggal;
    }
}

$checker = $input['checker_id'] ?? null;
$shift = $input['shift_id'] ?? null;
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
        // Only reuse an existing header for the date if it's still a draft —
        // a submitted one is locked and must not be silently overwritten.
        $stmt = $pdo->prepare('SELECT id, status FROM t_painting_prod_header WHERE department_id = ? AND tanggal = ?');
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

    // Checker sign-off timestamp: stamped on submit; Foreman/Supervisor sign
    // later from "Persetujuan Saya" (see includes/signoff.php).
    $checker_at = $status === 'submitted' ? date('Y-m-d H:i:s') : null;

    $params = [
        $tanggal, $department_id,
        nz($checker) !== null ? (int)$checker : null,
        nz($shift) !== null ? (int)$shift : null,
        $checker_at,
        $status,
    ];

    if ($header_id) {
        // Once submitted, a record is locked — only a still-draft record can be
        // updated (this is how a draft transitions to submitted), unless an
        // Admin has approved an edit request for this record.
        $lockClause = $unlockedEdit ? '' : ' AND status="draft"';
        $stmt = $pdo->prepare(
            'UPDATE t_painting_prod_header SET tanggal=?, department_id=?, checker_id=?, shift_id=?, checker_at=?, status=?
             WHERE id=?' . $lockClause
        );
        $stmt->execute(array_merge($params, [$header_id]));

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Laporan ini sudah disubmit dan tidak bisa diubah lagi.']);
            exit;
        }
        $pdo->prepare('DELETE FROM t_painting_prod_line WHERE header_id = ?')->execute([$header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_painting_prod_header (tanggal, department_id, checker_id, shift_id, checker_at, status)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute($params);
        $header_id = (int)$pdo->lastInsertId();
    }

    $insL = $pdo->prepare(
        'INSERT INTO t_painting_prod_line (header_id, line_no, model, cb, fot, fw, part, others, keterangan)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($lines as $line) {
        $model = trim((string)($line['model'] ?? ''));
        $keterangan = trim((string)($line['keterangan'] ?? ''));
        $cb = nz($line['cb'] ?? null);
        $fot = nz($line['fot'] ?? null);
        $fw = nz($line['fw'] ?? null);
        $part = nz($line['part'] ?? null);
        $others = nz($line['others'] ?? null);
        // Skip fully-empty rows (no model, no quantities, no remark).
        if ($model === '' && $keterangan === ''
            && $cb === null && $fot === null && $fw === null && $part === null && $others === null) continue;
        $insL->execute([
            $header_id, (int)$line['line_no'],
            $model !== '' ? $model : null,
            $cb !== null ? (int)$cb : null,
            $fot !== null ? (int)$fot : null,
            $fw !== null ? (int)$fw : null,
            $part !== null ? (int)$part : null,
            $others !== null ? (int)$others : null,
            $keterangan !== '' ? $keterangan : null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$type       = (string)($input['checksheet_type'] ?? '');
$deptId     = (int)($input['department_id'] ?? 0);
$conditionId = ($input['condition_id'] ?? '') === '' ? null : (int)$input['condition_id'];
$tanggal    = (string)($input['tanggal'] ?? '');
$reasonId   = (int)($input['reason_id'] ?? 0);
$note       = trim((string)($input['note'] ?? ''));
$createdBy  = (int)($input['created_by'] ?? 0);

// Header table (for the "was it already filled?" guard) per checksheet type.
$headerTable = [
    'painting'      => 't_checksheet_header',
    'painting_prod' => 't_painting_prod_header',
    'assy'          => 't_assy_header',
    'fopump'        => 't_fopump_header',
][$type] ?? null;

$dt = DateTime::createFromFormat('Y-m-d', $tanggal);
$validDate = $dt && $dt->format('Y-m-d') === $tanggal;

if (!$headerTable || !$deptId || !$reasonId || !$validDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap atau jenis checksheet tidak dikenal.']);
    exit;
}
if ($tanggal > date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['error' => 'Tidak bisa memberi keterangan untuk tanggal yang belum terjadi.']);
    exit;
}

// Reason must be a real, active reason.
$chk = $pdo->prepare('SELECT 1 FROM m_offday_reason WHERE id = ? AND is_active = 1');
$chk->execute([$reasonId]);
if (!$chk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Alasan tidak valid.']);
    exit;
}

// Can't excuse a day that already has a submitted checksheet.
$sql = "SELECT 1 FROM `$headerTable` WHERE department_id = ? AND tanggal = ? AND status = 'submitted'";
$params = [$deptId, $tanggal];
if ($conditionId !== null && $type === 'painting') {
    $sql .= ' AND condition_id = ?';
    $params[] = $conditionId;
}
$sql .= ' LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
if ($stmt->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['error' => 'Tanggal ini sudah ada checksheet yang disubmit, tidak perlu keterangan.']);
    exit;
}

$createdByName = null;
if ($createdBy) {
    $u = $pdo->prepare('SELECT name FROM m_user WHERE id = ?');
    $u->execute([$createdBy]);
    $createdByName = $u->fetchColumn() ?: null;
}

try {
    // One excuse per type+dept+condition+date — re-submitting updates the reason.
    $stmt = $pdo->prepare(
        'INSERT INTO t_checksheet_offday
            (checksheet_type, department_id, condition_id, tanggal, reason_id, note, created_by, created_by_name)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE reason_id = VALUES(reason_id), note = VALUES(note),
             created_by = VALUES(created_by), created_by_name = VALUES(created_by_name)'
    );
    $stmt->execute([
        $type, $deptId, $conditionId, $tanggal, $reasonId,
        $note !== '' ? $note : null,
        $createdBy ?: null, $createdByName,
    ]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

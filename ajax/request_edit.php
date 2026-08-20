<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$allowedTypes = ['painting', 'assy', 'fopump', 'fopump_reject', 'jig', 'bakeoven', 'washing', 'paint_viscosity', '3s3t'];

$type = (string)($input['checksheet_type'] ?? '');
$header_id = (int)($input['header_id'] ?? 0);
$label = trim((string)($input['label'] ?? ''));
$requested_by = (int)($input['requested_by'] ?? 0);
$reason = trim((string)($input['reason'] ?? ''));

if (!in_array($type, $allowedTypes, true) || !$header_id || !$requested_by || $reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Pilih nama kamu dan isi alasan edit-nya.']);
    exit;
}

// One open (pending or already-approved-and-still-unlocked) request per record at a time.
$stmt = $pdo->prepare(
    "SELECT id FROM t_edit_request
     WHERE checksheet_type = ? AND header_id = ?
       AND (status = 'pending' OR (status = 'approved' AND unlock_expires_at > NOW()))
     LIMIT 1"
);
$stmt->execute([$type, $header_id]);
if ($stmt->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['error' => 'Sudah ada request edit yang masih aktif untuk data ini.']);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO t_edit_request (checksheet_type, header_id, label, requested_by, reason, status)
     VALUES (?, ?, ?, ?, ?, "pending")'
);
$stmt->execute([$type, $header_id, $label ?: null, $requested_by, $reason]);

echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

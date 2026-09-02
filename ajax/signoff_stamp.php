<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/signoff.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$type      = (string)($input['type'] ?? '');
$header_id = (int)($input['header_id'] ?? 0);

$role = user_signoff_role(); // checker | foreman | supervisor | admin | null
// Admin can act on any line; map to the role being requested.
if ($role === 'admin') {
    $role = in_array($input['role'] ?? '', ['checker', 'foreman', 'supervisor'], true) ? $input['role'] : 'foreman';
}
// 'checker' here = "Selesaikan bulan ini" (finalize) on a monthly grid.
if (!in_array($role, ['checker', 'foreman', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Peran Anda tidak bisa menandatangani.']);
    exit;
}
if (!$header_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

$res = signoff_stamp($pdo, $type, $header_id, $role);
if (!empty($res['ok'])) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(409);
    echo json_encode(['error' => $res['error'] ?? 'Sudah ditandatangani atau bukan record hari ini.']);
}

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$type = $input['type'] ?? '';
$id = (int)($input['id'] ?? 0);
$pin = trim((string)($input['pin'] ?? ''));

// Maps a checksheet "type" (as used by the Delete button on each View
// Checksheets page) to its header table — deleting the header row cascades
// to its detail rows via the existing FK ON DELETE CASCADE constraints.
$tables = [
    'painting'      => 't_checksheet_header',
    'assy'          => 't_assy_header',
    'jig'           => 't_jigheader',
    'bakeoven'      => 't_bakeoven_header',
    'fopump'        => 't_fopump_header',
    'fopump_check'  => 't_fopump_check_header',
    'fopump_test'   => 't_fopump_test_header',
    'fopump_reject' => 't_fopump_reject_header',
];

if (!isset($tables[$type]) || !$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$stmt = $pdo->prepare('SELECT value FROM m_setting WHERE setting_key = "delete_pin"');
$stmt->execute();
$expectedPin = $stmt->fetchColumn() ?: '1234';

if ($pin === '' || $pin !== $expectedPin) {
    http_response_code(403);
    echo json_encode(['error' => 'Incorrect PIN.']);
    exit;
}

$table = $tables[$type];
$stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Checksheet not found (already deleted?).']);
    exit;
}

echo json_encode(['success' => true]);

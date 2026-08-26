<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$allowedTypes = ['painting', 'assy', 'fopump', 'fopump_reject', 'jig', 'bakeoven', 'washing', 'paint_viscosity', '3s3t'];
$typeLabels = [
    'painting' => 'Painting', 'assy' => 'Torque (Assembling)', 'fopump' => 'FO Pump Daily Report',
    'fopump_reject' => 'FO Pump Daily Reject', 'jig' => 'Sub Assembly (Jig)', 'bakeoven' => 'Bake Oven',
    'washing' => 'Washing Machine', 'paint_viscosity' => 'Paint Viscosity', '3s3t' => 'Checksheet 3S-3T',
];

$mode         = ($input['mode'] ?? 'edit') === 'fill' ? 'fill' : 'edit';
$type         = (string)($input['checksheet_type'] ?? '');
$label        = trim((string)($input['label'] ?? ''));
$requested_by = (int)($input['requested_by'] ?? 0);
$reason       = trim((string)($input['reason'] ?? ''));

if (!in_array($type, $allowedTypes, true) || !$requested_by || $reason === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Pilih nama kamu dan isi alasan edit-nya.']);
    exit;
}

if ($mode === 'fill') {
    // Request to fill a day that was never submitted — no existing record.
    $target_date   = (string)($input['target_date'] ?? '');
    $department_id = (int)($input['department_id'] ?? 0);
    $condition_id  = (int)($input['condition_id'] ?? 0) ?: null;

    $dt = DateTime::createFromFormat('Y-m-d', $target_date);
    $validDate = $dt && $dt->format('Y-m-d') === $target_date;
    if (!$validDate || !$department_id || $target_date >= date('Y-m-d')) {
        http_response_code(400);
        echo json_encode(['error' => 'Tanggal yang diminta tidak valid.']);
        exit;
    }

    // One open (pending or still-unlocked) fill request per day/scope.
    $dupSql = "SELECT id FROM t_edit_request
               WHERE checksheet_type = ? AND header_id IS NULL AND target_date = ? AND department_id = ?
                 AND (status = 'pending' OR (status = 'approved' AND unlock_expires_at > NOW()))";
    $dupParams = [$type, $target_date, $department_id];
    if ($condition_id) { $dupSql .= ' AND condition_id = ?'; $dupParams[] = $condition_id; }
    else { $dupSql .= ' AND condition_id IS NULL'; }
    $dupSql .= ' LIMIT 1';
    $stmt = $pdo->prepare($dupSql);
    $stmt->execute($dupParams);
    if ($stmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['error' => 'Sudah ada request untuk tanggal ini yang masih aktif.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO t_edit_request (checksheet_type, header_id, target_date, department_id, condition_id, label, requested_by, reason, status)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$type, $target_date, $department_id, $condition_id, $label ?: null, $requested_by, $reason]);
    $newId = $pdo->lastInsertId();
} else {
    // Edit an existing submitted record.
    $header_id = (int)($input['header_id'] ?? 0);
    if (!$header_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Data tidak lengkap.']);
        exit;
    }

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
    $newId = $pdo->lastInsertId();
}

// Notify the Admin. Never let an email hiccup fail the request itself.
try {
    $requesterName = '';
    $stmt = $pdo->prepare('SELECT name FROM m_user WHERE id = ?');
    $stmt->execute([$requested_by]);
    $requesterName = (string)($stmt->fetchColumn() ?: ('User #' . $requested_by));

    $typeLabel = $typeLabels[$type] ?? $type;
    $kindText  = $mode === 'fill' ? 'Isi tanggal terlewat' : 'Edit record';
    $recordText = $mode === 'fill'
        ? ('Tanggal ' . ($input['target_date'] ?? '?') . ($label ? ' — ' . $label : ''))
        : ($label ?: ('#' . ($input['header_id'] ?? '')));

    $subject = "[Checksheet] Request $kindText — $typeLabel";
    $safe = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $html = "<p>Ada request baru menunggu persetujuan.</p>"
        . "<table cellpadding='6' style='border-collapse:collapse'>"
        . "<tr><td><b>Jenis</b></td><td>{$safe($kindText)}</td></tr>"
        . "<tr><td><b>Checksheet</b></td><td>{$safe($typeLabel)}</td></tr>"
        . "<tr><td><b>Detail</b></td><td>{$safe($recordText)}</td></tr>"
        . "<tr><td><b>Diminta oleh</b></td><td>{$safe($requesterName)}</td></tr>"
        . "<tr><td><b>Alasan</b></td><td>{$safe($reason)}</td></tr>"
        . "</table>"
        . "<p>Buka halaman <b>Management &raquo; Edit Requests</b> untuk approve/deny.</p>";
    $text = "Request $kindText\nChecksheet: $typeLabel\nDetail: $recordText\nDiminta oleh: $requesterName\nAlasan: $reason";

    send_admin_notification($subject, $html, $text);
} catch (Throwable $e) {
    // swallow — request is already saved
}

echo json_encode(['success' => true, 'id' => $newId]);

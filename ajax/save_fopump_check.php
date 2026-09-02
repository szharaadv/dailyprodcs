<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/signoff.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$me   = current_user();
$uid  = (int)($me['id'] ?? 0);
$role = user_signoff_role(); // 'checker' | 'foreman' | 'supervisor' | 'admin' | null

if (!in_array($role, ['checker', 'foreman', 'supervisor', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Peran akun Anda tidak diizinkan mengisi checksheet ini.']);
    exit;
}
// A role that signs a line needs a real m_user id (the Admin session has none,
// and Admin only edits/corrects — it never stamps a signature line).
if ($role !== 'admin' && $uid <= 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesi tidak valid.']);
    exit;
}

$department_id  = (int)($input['department_id'] ?? 0);
$model_id       = (int)($input['model_id'] ?? 0);
$prod_date_code = trim((string)($input['prod_date_code'] ?? ''));
$status         = ($input['status'] ?? 'submitted') === 'draft' ? 'draft' : 'submitted';
$sampleNos      = $input['samples'] ?? [];
$rows           = $input['rows'] ?? [];

if (!$department_id || !$model_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Data tidak lengkap.']);
    exit;
}

// Section scope: a Foreman/Supervisor may only sign FO Pump Check in a
// department they're assigned to (m_user_section) — same rule as the
// "Persetujuan Saya" queue. Admin bypasses.
if (in_array($role, ['foreman', 'supervisor'], true) && !is_admin()
    && !in_array($department_id, _signoff_user_dept_ids($pdo, $uid, 'fopump_check_list.php'), true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Anda bukan approver FO Pump Check untuk departemen ini.']);
    exit;
}

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d'); // no backdating — the sheet resets daily

try {
    $pdo->beginTransaction();

    // One record per model PER DAY: load today's record (with its current
    // signatures) so we never wipe another role's sign-off, and yesterday's
    // record is left untouched as history.
    $stmt = $pdo->prepare('SELECT * FROM t_fopump_check_header WHERE model_id = ? AND tanggal = ? FOR UPDATE');
    $stmt->execute([$model_id, $today]);
    $existing = $stmt->fetch() ?: null;

    $checker_id    = $existing['checker_id'] ?? null;
    $checker_at    = $existing['checker_at'] ?? null;
    $foreman_id    = $existing['foreman_id'] ?? null;
    $foreman_at    = $existing['foreman_at'] ?? null;
    $supervisor_id = $existing['supervisor_id'] ?? null;
    $supervisor_at = $existing['supervisor_at'] ?? null;

    // Stamp only the line for the logged-in role. Admin edits without signing.
    if ($role === 'checker')        { $checker_id = $uid;    $checker_at = $now; }
    elseif ($role === 'foreman')    { $foreman_id = $uid;    $foreman_at = $now; }
    elseif ($role === 'supervisor') { $supervisor_id = $uid; $supervisor_at = $now; }

    // Keep an existing prod date code if this save didn't provide one.
    $prod = $prod_date_code !== '' ? $prod_date_code : ($existing['prod_date_code'] ?? null);

    if ($existing) {
        $header_id = (int)$existing['id'];
        $stmt = $pdo->prepare(
            'UPDATE t_fopump_check_header
             SET department_id=?, prod_date_code=?,
                 checker_id=?, checker_at=?, foreman_id=?, foreman_at=?, supervisor_id=?, supervisor_at=?,
                 status=?
             WHERE id=?'
        );
        $stmt->execute([$department_id, $prod, $checker_id, $checker_at, $foreman_id, $foreman_at,
            $supervisor_id, $supervisor_at, $status, $header_id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO t_fopump_check_header
             (department_id, model_id, tanggal, prod_date_code, checker_id, checker_at, foreman_id, foreman_at, supervisor_id, supervisor_at, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$department_id, $model_id, $today, $prod, $checker_id, $checker_at, $foreman_id, $foreman_at,
            $supervisor_id, $supervisor_at, $status]);
        $header_id = (int)$pdo->lastInsertId();
    }

    // Rewrite samples + details (cascade clears old detail via sample delete).
    $pdo->prepare('DELETE FROM t_fopump_check_sample WHERE header_id = ?')->execute([$header_id]);

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
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Notify the NEXT role's people who have an email (optional — silent no-op if
// none have one). Never let email trouble affect the save result.
if ($status === 'submitted' && in_array($role, ['checker', 'foreman'], true)) {
    try {
        $nextTitle = $role === 'checker' ? 'Foreman' : 'Supervisor';
        $mStmt = $pdo->prepare('SELECT name FROM m_fopump_check_model WHERE id = ?');
        $mStmt->execute([$model_id]);
        $modelName = (string)($mStmt->fetchColumn() ?: ('#' . $model_id));

        $rStmt = $pdo->prepare("SELECT name, email FROM m_user WHERE title = ? AND is_active = 1 AND email IS NOT NULL AND email <> ''");
        $rStmt->execute([$nextTitle]);
        $recipients = $rStmt->fetchAll();

        if ($recipients) {
            $who = (string)($me['name'] ?? 'Seseorang');
            $subject = "[FO Pump Check] Menunggu sign-off $nextTitle — $modelName";
            $safe = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
            $html = "<p>Checksheet <b>FO Pump Check</b> model <b>{$safe($modelName)}</b> sudah ditandatangani <b>{$safe($who)}</b> ("
                . $safe(ucfirst($role)) . ") dan menunggu sign-off <b>{$safe($nextTitle)}</b>.</p>"
                . "<p>Silakan login untuk memeriksa dan menandatangani.</p>";
            $text = "Checksheet FO Pump Check model $modelName menunggu sign-off $nextTitle (setelah $who).";
            foreach ($recipients as $r) {
                send_notification($r['email'], $r['name'], $subject, $html, $text);
            }
        }
    } catch (Throwable $e) {
        // ignore — the checksheet is already saved
    }
}

echo json_encode(['success' => true, 'header_id' => $header_id, 'status' => $status]);

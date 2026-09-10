<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$bakeoven_id = (int)($_GET['bakeoven_id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$bakeoven_id || !$month || !$year) {
    echo json_encode(['times' => [], 'header' => null, 'details' => new stdClass(), 'paraf' => new stdClass()]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, time_label FROM m_bakeoven_time WHERE bakeoven_id = ? AND is_active = 1 ORDER BY sort_order, id');
$stmt->execute([$bakeoven_id]);
$times = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM t_bakeoven_header WHERE bakeoven_id = ? AND month = ? AND year = ?');
$stmt->execute([$bakeoven_id, $month, $year]);
$header = $stmt->fetch() ?: null;

$details = new stdClass();
$paraf = new stdClass();
if ($header) {
    $stmt = $pdo->prepare('SELECT time_id, day, actual_temp FROM t_bakeoven_detail WHERE header_id = ?');
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $d) {
        $details->{$d['time_id'] . '_' . $d['day']} = $d['actual_temp'];
    }

    // Include the signer's name so the grid can render any saved paraf, even a
    // user who isn't in the page's people list (the paraf is stamped with
    // whoever was logged in, regardless of their job title).
    $stmt = $pdo->prepare(
        'SELECT p.day, p.user_id, u.name
         FROM t_bakeoven_paraf p
         LEFT JOIN m_user u ON u.id = p.user_id
         WHERE p.header_id = ?'
    );
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $p) {
        $paraf->{$p['day']} = ['id' => (int)$p['user_id'], 'name' => $p['name']];
    }
}

$unlocked = $header && has_active_unlock($pdo, 'bakeoven', $header['id']);

echo json_encode(['times' => $times, 'header' => $header, 'details' => $details, 'paraf' => $paraf, 'unlocked' => $unlocked]);

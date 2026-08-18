<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$jig_id = (int)($_GET['jig_id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$jig_id || !$month || !$year) {
    echo json_encode(['items' => [], 'header' => null, 'details' => new stdClass()]);
    exit;
}

$stmt = $pdo->prepare('SELECT id, checking_item FROM m_jigitem WHERE jig_id = ? AND is_active = 1 ORDER BY sort_order, id');
$stmt->execute([$jig_id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM t_jigheader WHERE jig_id = ? AND month = ? AND year = ?');
$stmt->execute([$jig_id, $month, $year]);
$header = $stmt->fetch() ?: null;

$details = new stdClass();
if ($header) {
    $stmt = $pdo->prepare('SELECT jig_item_id, day, result FROM t_jig_detail WHERE header_id = ?');
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $d) {
        $details->{$d['jig_item_id'] . '_' . $d['day']} = $d['result'];
    }
}

echo json_encode(['items' => $items, 'header' => $header, 'details' => $details]);

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/edit_requests.php';
header('Content-Type: application/json');

$pdo = get_db();
$department_id = (int)($_GET['department_id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);

if (!$department_id || !$month || !$year) {
    echo json_encode(['items' => [], 'header' => null, 'details' => new stdClass()]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, process_name, product_name, maker_brand, standard_min, standard_max, standard_unit
     FROM m_paint_viscosity_item WHERE department_id = ? AND is_active = 1 ORDER BY sort_order, id'
);
$stmt->execute([$department_id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM t_paint_viscosity_header WHERE department_id = ? AND month = ? AND year = ?');
$stmt->execute([$department_id, $month, $year]);
$header = $stmt->fetch() ?: null;

$details = new stdClass();
if ($header) {
    $stmt = $pdo->prepare('SELECT item_id, day, actual_result FROM t_paint_viscosity_detail WHERE header_id = ?');
    $stmt->execute([$header['id']]);
    foreach ($stmt->fetchAll() as $d) {
        $details->{$d['item_id'] . '_' . $d['day']} = $d['actual_result'];
    }
}

$unlocked = $header && has_active_unlock($pdo, 'paint_viscosity', $header['id']);

echo json_encode(['items' => $items, 'header' => $header, 'details' => $details, 'unlocked' => $unlocked]);

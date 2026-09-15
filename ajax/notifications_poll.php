<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login();
header('Content-Type: application/json');

$pdo = get_db();
$after = (int)($_GET['after'] ?? 0);
echo json_encode([
    'items'  => notif_since($pdo, $after),
    'unread' => notif_unread_count($pdo),
]);

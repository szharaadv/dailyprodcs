<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login();
header('Content-Type: application/json');

notif_mark_all_read(get_db());
echo json_encode(['ok' => true]);

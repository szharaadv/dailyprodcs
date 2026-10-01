<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/offday.php';
header('Content-Type: application/json');

echo json_encode(['reasons' => offday_reasons(get_db())]);

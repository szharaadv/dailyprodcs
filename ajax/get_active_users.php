<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = get_db();
$users = $pdo->query("SELECT id, name FROM m_user WHERE is_active = 1 AND name <> 'Admin' ORDER BY name")->fetchAll();

echo json_encode(['users' => $users]);

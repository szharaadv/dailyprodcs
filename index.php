<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

if (empty($_SESSION['auth_user']['name'])) {
    header('Location: login.php?next=index.php');
    exit;
}

$departments = $pdo->query('SELECT * FROM m_department WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - Select Department</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
<div class="landing">
    <div class="landing-brand">
        <div class="brand-mark">DP</div>
        <div class="brand-text">
            <div class="brand-title">Daily Prod</div>
            <div class="brand-subtitle">Production Check Sheet</div>
        </div>
    </div>

    <h1>Select Department</h1>
    <p class="landing-hint">Choose a department to start filling in a check sheet.</p>
    <p class="landing-hint">Logged in as <strong><?= htmlspecialchars($_SESSION['auth_user']['name']) ?></strong> &middot; <a href="logout.php" class="dept-switch-link">Logout</a></p>

    <div class="dept-grid">
        <?php foreach ($departments as $d): ?>
            <a class="dept-card" href="select_section.php?department_id=<?= $d['id'] ?>">
                <div class="dept-icon"><?= strtoupper(substr($d['name'], 0, 2)) ?></div>
                <div class="dept-name"><?= htmlspecialchars($d['name']) ?></div>
                <div class="dept-go">Open Check Sheet &rarr;</div>
            </a>
        <?php endforeach; ?>
        <?php if (!$departments): ?>
            <p class="empty">No departments yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

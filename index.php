<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

if (empty($_SESSION['auth_user']['name'])) {
    header('Location: login.php?next=index.php');
    exit;
}

$departments = $pdo->query('SELECT * FROM m_department WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

// FO Pump is a group of check sheets that lives under the Assembling
// department, but it's a distinct enough product line to earn its own card on
// this landing page (a shortcut straight to its sheets). Look it up by its
// group_label — a stable natural key — rather than a hard-coded department id,
// since ids can differ between the local and server copies of the database.
$fopump_dept = $pdo->query(
    "SELECT department_id FROM m_checksheet_section
     WHERE group_label = 'FO Pump' AND is_active = 1
     ORDER BY sort_order, id LIMIT 1"
)->fetchColumn();
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

    <?php if (isset($_GET['denied'])): ?>
        <p class="landing-hint" style="color:#9b3b32;font-weight:600;">Anda tidak punya akses ke halaman itu. Hubungi Admin jika ini keliru.</p>
    <?php endif; ?>
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
        <?php if ($fopump_dept): ?>
            <a class="dept-card" href="select_group.php?department_id=<?= (int)$fopump_dept ?>&group=<?= urlencode('FO Pump') ?>">
                <div class="dept-icon">FO</div>
                <div class="dept-name">FO Pump</div>
                <div class="dept-go">Open Check Sheet &rarr;</div>
            </a>
        <?php endif; ?>
        <?php if (!$departments): ?>
            <p class="empty">No departments yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

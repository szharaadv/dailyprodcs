<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department_id = (int)($_GET['department_id'] ?? 0);
$group = $_GET['group'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM m_department WHERE id = ? AND is_active = 1');
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department || $group === '') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM m_checksheet_section WHERE department_id = ? AND group_label = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department_id, $group]);
$sections = $stmt->fetchAll();

if (count($sections) <= 1) {
    $target = $sections[0]['route'] ?? 'select_section.php';
    header("Location: {$target}?department_id={$department_id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - <?= htmlspecialchars($group) ?></title>
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

    <h1><?= htmlspecialchars($group) ?></h1>
    <p class="landing-hint">Choose a check sheet to continue.</p>

    <div class="dept-grid">
        <?php foreach ($sections as $s): ?>
            <a class="dept-card" href="<?= htmlspecialchars($s['route']) ?>?department_id=<?= $department_id ?>">
                <div class="dept-icon"><?= strtoupper(substr($s['name'], 0, 2)) ?></div>
                <div class="dept-name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="dept-go">Open &rarr;</div>
            </a>
        <?php endforeach; ?>
    </div>

    <p class="landing-hint" style="margin-top:28px;"><a href="select_section.php?department_id=<?= $department_id ?>" class="dept-switch-link">&larr; Back to Sections</a></p>
</div>
</body>
</html>

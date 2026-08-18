<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = get_db();

$department_id = (int)($_GET['department_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM m_department WHERE id = ? AND is_active = 1');
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM m_checksheet_section WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department_id]);
$sections = $stmt->fetchAll();

if (count($sections) <= 1) {
    $target = $sections[0]['route'] ?? 'index.php';
    header("Location: {$target}?department_id={$department_id}");
    exit;
}

// Sections that share a group_label collapse into a single card leading to
// select_group.php, so a department with many related check sheets (e.g. FO
// Pump's daily report / check sheet / test record / reject log) doesn't turn
// into a wall of loose cards. A group with only one member is shown as a
// plain direct-link card instead — no point routing through a sub-picker
// for a single item.
$groups = [];
$cards = [];
foreach ($sections as $s) {
    if ($s['group_label']) {
        $groups[$s['group_label']][] = $s;
    } else {
        $cards[] = ['label' => $s['name'], 'href' => $s['route'] . '?department_id=' . $department_id];
    }
}
foreach ($groups as $label => $members) {
    if (count($members) === 1) {
        $cards[] = ['label' => $members[0]['name'], 'href' => $members[0]['route'] . '?department_id=' . $department_id];
    } else {
        $cards[] = [
            'label' => $label,
            'href' => 'select_group.php?department_id=' . $department_id . '&group=' . urlencode($label),
            'hint' => count($members) . ' check sheets',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - Select Section</title>
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

    <h1><?= htmlspecialchars($department['name']) ?></h1>
    <p class="landing-hint">Choose a check sheet section to continue.</p>

    <div class="dept-grid">
        <?php foreach ($cards as $c): ?>
            <a class="dept-card" href="<?= htmlspecialchars($c['href']) ?>">
                <div class="dept-icon"><?= strtoupper(substr($c['label'], 0, 2)) ?></div>
                <div class="dept-name"><?= htmlspecialchars($c['label']) ?></div>
                <div class="dept-go"><?= isset($c['hint']) ? htmlspecialchars($c['hint']) . ' &rarr;' : 'Open &rarr;' ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <p class="landing-hint" style="margin-top:28px;"><a href="index.php" class="dept-switch-link">&larr; Change Department</a></p>
</div>
</body>
</html>

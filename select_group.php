<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/picker_ui.php';
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

// Per-user routing: this person must have access to this unit (FO Pump is its
// own unit, separate from the rest of its department).
if (!can_access_unit($department_id, $group === 'FO Pump' ? 'FO Pump' : null)) {
    header('Location: index.php?denied=1');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM m_checksheet_section WHERE department_id = ? AND group_label = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department_id, $group]);
$sections = $stmt->fetchAll();

// Hide sections this user's role isn't allowed to open (see includes/access.php).
$sections = array_values(array_filter($sections, fn($s) => can_access_route($s['route'])));

if (count($sections) <= 1) {
    $target = $sections[0]['route'] ?? 'select_section.php';
    header("Location: {$target}?department_id={$department_id}");
    exit;
}

$info = picker_section_info($pdo); // section id => ['state','freq']

// FO Pump is promoted to its own landing card, so its "back" is the landing;
// any other group's back is its department's section picker.
$isTopLevelGroup = $group === 'FO Pump';
$backHref  = $isTopLevelGroup ? 'index.php' : 'select_section.php?department_id=' . $department_id;
$backLabel = $isTopLevelGroup ? 'Change Department' : 'Back to Sections';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - <?= htmlspecialchars($group) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php $ver = @filemtime(__DIR__ . '/assets/css/app.css') ?: '1'; $lver = @filemtime(__DIR__ . '/assets/css/landing.css') ?: '1'; ?>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= $ver ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $lver ?>">
</head>
<body>
<?php render_picker_topbar(); ?>

<div class="pk-wrap">
    <div class="pk-crumbs">
        <a href="index.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>Department</a>
        <span class="sep">/</span><span class="cur"><?= htmlspecialchars($group) ?></span>
    </div>

    <div class="pk-head">
        <h1><?= htmlspecialchars($group) ?></h1>
        <p>Choose a check sheet to continue.</p>
    </div>

    <div class="pk-grid">
        <?php foreach ($sections as $s): $meta = $info[(int) $s['id']] ?? null; [$cls, $lbl] = picker_status($meta['state'] ?? 'missing'); ?>
            <a class="pk-card" href="<?= htmlspecialchars($s['route']) ?>?department_id=<?= $department_id ?>">
                <div class="pk-card-top">
                    <div class="pk-ic"><?= picker_icon($s['name']) ?></div>
                    <div>
                        <h3><?= htmlspecialchars($s['name']) ?></h3>
                        <div class="pk-sub"><?= htmlspecialchars(dashboard_freq_label($meta['freq'] ?? 'daily')) ?></div>
                    </div>
                </div>
                <div class="pk-foot">
                    <span class="pk-status <?= $cls ?>"><?= htmlspecialchars($lbl) ?></span>
                    <span class="pk-go">Open &rarr;</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="pk-back"><a href="<?= htmlspecialchars($backHref) ?>">&larr; <?= htmlspecialchars($backLabel) ?></a></div>
</div>
</body>
</html>

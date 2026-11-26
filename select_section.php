<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/picker_ui.php';
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

// Per-user routing: this person must be assigned to this department.
if (!can_access_department($department_id)) {
    header('Location: index.php?denied=1');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM m_checksheet_section WHERE department_id = ? AND is_active = 1 ORDER BY sort_order');
$stmt->execute([$department_id]);
$sections = $stmt->fetchAll();

// Hide sections this user's role isn't allowed to open (see includes/access.php).
$sections = array_values(array_filter($sections, fn($s) => can_access_route($s['route'])));

if (count($sections) <= 1) {
    $target = $sections[0]['route'] ?? 'index.php';
    header("Location: {$target}?department_id={$department_id}");
    exit;
}

$info = picker_section_info($pdo); // section id => ['state','freq']

// Sections sharing a group_label collapse into one grouped card whose
// sub-sheets are shown inline. A single-member group is just a plain card.
// FO Pump has its own card on the landing, so skip it inside its department.
$groups = [];
$items = [];
foreach ($sections as $s) {
    if ($s['group_label'] === 'FO Pump') continue;
    if ($s['group_label']) {
        $groups[$s['group_label']][] = $s;
    } else {
        $items[] = ['type' => 'section', 'row' => $s];
    }
}
foreach ($groups as $label => $members) {
    if (count($members) === 1) {
        $items[] = ['type' => 'section', 'row' => $members[0]];
    } else {
        $items[] = ['type' => 'group', 'label' => $label, 'members' => $members];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - <?= htmlspecialchars($department['name']) ?></title>
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
        <span class="sep">/</span><span class="cur"><?= htmlspecialchars($department['name']) ?></span>
    </div>

    <div class="pk-head">
        <h1><?= htmlspecialchars($department['name']) ?></h1>
        <p>Choose a check sheet section to continue.</p>
    </div>

    <div class="pk-grid">
        <?php foreach ($items as $it): ?>
            <?php if ($it['type'] === 'section'): $s = $it['row']; $meta = $info[(int) $s['id']] ?? null; ?>
                <?php [$cls, $lbl] = picker_status($meta['state'] ?? 'missing'); ?>
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
            <?php else: ?>
                <div class="pk-card group">
                    <div class="pk-card-top">
                        <div class="pk-ic"><?= picker_icon($it['label']) ?></div>
                        <div>
                            <h3><?= htmlspecialchars($it['label']) ?> <span style="font-size:12px;color:var(--fg-3);font-weight:500;">— <?= count($it['members']) ?> sheet</span></h3>
                            <div class="pk-sub">Group — pick a check sheet below</div>
                        </div>
                    </div>
                    <div class="pk-sheets">
                        <?php foreach ($it['members'] as $m): $mm = $info[(int) $m['id']] ?? null; [$dCls] = picker_status($mm['state'] ?? 'missing'); ?>
                            <a class="pk-sheet" href="<?= htmlspecialchars($m['route']) ?>?department_id=<?= $department_id ?>">
                                <span class="pk-sic"><?= picker_icon($m['name']) ?></span>
                                <span class="pk-sheet-main">
                                    <span class="pk-snm"><?= htmlspecialchars($m['name']) ?></span>
                                    <span class="pk-sfr"><?= htmlspecialchars(dashboard_freq_label($mm['freq'] ?? 'daily')) ?></span>
                                </span>
                                <span class="pk-dot <?= $dCls ?>"></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="pk-back"><a href="index.php">&larr; Change Department</a></div>
</div>
</body>
</html>

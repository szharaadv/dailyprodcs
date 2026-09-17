<?php
// Public, read-only "today's check sheet status" board — viewable without an
// account (e.g. for a manager glancing at a shared screen). No data entry, no
// personal details: just which sheets are filled / still outstanding today.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/dashboard.php';
$pdo = get_db();

$sections = dashboard_sections($pdo);
$missing = array_values(array_filter($sections, fn($s) => $s['state'] === 'missing'));

$byGroup = [];
foreach ($sections as $s) {
    $key = !empty($s['group_label']) ? $s['group_label'] : $s['dept_name'];
    $byGroup[$key][] = $s;
}

$stateMeta = [
    'missing'  => ['label' => 'Not filled', 'cls' => 'ds-pill-missing'],
    'filled'   => ['label' => 'Filled',     'cls' => 'ds-pill-filled'],
    'optional' => ['label' => 'As needed',  'cls' => 'ds-pill-optional'],
    'off'      => ['label' => 'Off day',    'cls' => 'ds-pill-off'],
];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120"><!-- auto-refresh for a glance screen -->
    <title>Today's Check Sheet Status · Daily Prod</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #eef0f2; margin: 0; }
        .pub-wrap { max-width: 900px; margin: 0 auto; padding: 28px 20px 48px; }
        .pub-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .pub-brand { display: flex; align-items: center; gap: 12px; }
        .pub-mark { width: 44px; height: 44px; border-radius: 10px; background: #9b3b32; color: #fff; display: flex; align-items: center; justify-content: center; font: 700 15px Oswald, sans-serif; }
        .pub-title { font: 700 20px Oswald, sans-serif; text-transform: uppercase; letter-spacing: .02em; color: #181a1e; }
        .pub-sub { font-size: 13px; color: #8b93a1; }
        .pub-login { font-size: 13px; font-weight: 600; color: #9b3b32; text-decoration: none; border: 1px solid #e4c9c4; padding: 8px 14px; border-radius: 8px; background: #fff; }
        .pub-login:hover { background: #fdf1ef; }
    </style>
</head>
<body>
<div class="pub-wrap">
    <div class="pub-head">
        <div class="pub-brand">
            <div class="pub-mark">DP</div>
            <div>
                <div class="pub-title">Today's Check Sheet Status</div>
                <div class="pub-sub"><?= $e(date('l, d F Y')) ?></div>
            </div>
        </div>
        <a class="pub-login" href="login.php">Login &rarr;</a>
    </div>

    <div class="ds-summary">
        <div class="ds-summary-main <?= count($missing) ? 'ds-summary-warn' : 'ds-summary-ok' ?>">
            <div class="ds-summary-num"><?= count($missing) ?></div>
            <div class="ds-summary-text">
                <?= count($missing) ? 'check sheet(s) not filled today' : 'All required check sheets are done 🎉' ?>
            </div>
        </div>
    </div>

    <?php foreach ($byGroup as $groupName => $rows): ?>
    <div class="ds-group">
        <div class="ds-group-title"><?= $e($groupName) ?></div>
        <div class="ds-list">
            <?php foreach ($rows as $s): $meta = $stateMeta[$s['state']]; ?>
            <div class="ds-row ds-row-<?= $e($s['state']) ?>" style="cursor:default;">
                <div class="ds-row-main">
                    <div class="ds-row-name"><?= $e($s['name']) ?></div>
                    <div class="ds-row-freq"><?= $e(dashboard_freq_label($s['freq'])) ?></div>
                </div>
                <span class="ds-pill <?= $meta['cls'] ?>"><?= $e($meta['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <p style="text-align:center;color:#9aa1ab;font-size:12px;margin-top:24px;">Auto-refreshes every 2 minutes · <?= $e(date('H:i')) ?></p>
</div>
</body>
</html>

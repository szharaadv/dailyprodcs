<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/picker_ui.php';
$pdo = get_db();

if (empty($_SESSION['auth_user']['name'])) {
    header('Location: login.php?next=index.php');
    exit;
}

$departments = $pdo->query('SELECT * FROM m_department WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
// Per-user routing: only show departments this person is allowed to open.
$departments = array_values(array_filter($departments, fn($d) => can_access_department((int) $d['id'])));

// FO Pump is a group of check sheets under Assembling, but it earns its own
// card here (a shortcut straight to its sheets). Look it up by its group_label
// — a stable natural key — rather than a hard-coded department id.
$fopump_dept = $pdo->query(
    "SELECT department_id FROM m_checksheet_section
     WHERE group_label = 'FO Pump' AND is_active = 1
     ORDER BY sort_order, id LIMIT 1"
)->fetchColumn();

// Today's status: count "not filled" per department (excluding the FO Pump
// group, which has its own card) and for FO Pump on its own.
$deptSummary = [];
$fopumpSummary = ['total' => 0, 'missing' => 0];
foreach (dashboard_sections($pdo) as $s) {
    if ($s['group_label'] === 'FO Pump') {
        $fopumpSummary['total']++;
        if ($s['state'] === 'missing') $fopumpSummary['missing']++;
    } else {
        $did = (int) $s['department_id'];
        $deptSummary[$did]['total']   = ($deptSummary[$did]['total']   ?? 0) + 1;
        $deptSummary[$did]['missing'] = ($deptSummary[$did]['missing'] ?? 0) + ($s['state'] === 'missing' ? 1 : 0);
    }
}

$me = current_user();
$firstName = preg_split('/\s+/', trim($me['name']))[0] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - Select Department</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php $ver = @filemtime(__DIR__ . '/assets/css/app.css') ?: '1'; $lver = @filemtime(__DIR__ . '/assets/css/landing.css') ?: '1'; ?>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= $ver ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $lver ?>">
</head>
<body>
<?php render_picker_topbar(); ?>

<div class="pk-wrap">
    <?php if (isset($_GET['denied'])): ?>
        <div class="pk-denied">You don't have access to that page. Contact an admin if this is a mistake.</div>
    <?php endif; ?>

    <div class="pk-head">
        <h1>Select Department</h1>
        <p>Hi <?= htmlspecialchars($firstName) ?> — choose a department to start filling in a check sheet.</p>
    </div>

    <div class="pk-grid">
        <?php foreach ($departments as $d): ?>
            <?php
            $sum = $deptSummary[(int) $d['id']] ?? ['total' => 0, 'missing' => 0];
            [$sCls, $sLbl] = picker_summary((int) ($sum['total'] ?? 0), (int) ($sum['missing'] ?? 0));
            ?>
            <a class="pk-card dept" href="select_section.php?department_id=<?= (int) $d['id'] ?>">
                <div class="pk-card-top">
                    <div class="pk-ic"><?= picker_icon($d['name']) ?></div>
                    <div>
                        <h3><?= htmlspecialchars($d['name']) ?></h3>
                        <div class="pk-sub"><?= (int) ($sum['total'] ?? 0) ?> check sheet</div>
                    </div>
                </div>
                <div class="pk-foot">
                    <span class="pk-status <?= $sCls ?>"><?= htmlspecialchars($sLbl) ?></span>
                    <span class="pk-go">Open &rarr;</span>
                </div>
            </a>
        <?php endforeach; ?>

        <?php if ($fopump_dept && can_access_unit((int) $fopump_dept, 'FO Pump')): ?>
            <?php [$fCls, $fLbl] = picker_summary($fopumpSummary['total'], $fopumpSummary['missing']); ?>
            <a class="pk-card dept" href="select_group.php?department_id=<?= (int) $fopump_dept ?>&group=<?= urlencode('FO Pump') ?>">
                <div class="pk-card-top">
                    <div class="pk-ic"><?= picker_icon('FO Pump') ?></div>
                    <div>
                        <h3>FO Pump</h3>
                        <div class="pk-sub"><?= (int) $fopumpSummary['total'] ?> check sheet</div>
                    </div>
                </div>
                <div class="pk-foot">
                    <span class="pk-status <?= $fCls ?>"><?= htmlspecialchars($fLbl) ?></span>
                    <span class="pk-go">Open &rarr;</span>
                </div>
            </a>
        <?php endif; ?>

        <?php if (!$departments && !($fopump_dept && can_access_unit((int) $fopump_dept, 'FO Pump'))): ?>
            <div class="pk-empty"><?= is_admin() ? 'No departments yet.' : 'You haven\'t been assigned to any department yet. Please contact an admin.' ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

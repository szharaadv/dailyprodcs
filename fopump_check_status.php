<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/signoff.php';
require_login();
$pdo = get_db();

// Which day to show (default today). Past dates = history.
$d = $_GET['d'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $d);
if (!$dt || $dt->format('Y-m-d') !== $d || $d > date('Y-m-d')) {
    $d = date('Y-m-d');
}
$isToday = ($d === date('Y-m-d'));

$stmt = $pdo->prepare(
    "SELECT m.id AS model_id, m.name AS model_name, m.department_id,
            h.id AS header_id,
            h.checker_at,    c.name AS checker_name,
            h.foreman_at,    f.name AS foreman_name,
            h.supervisor_at, s.name AS supervisor_name
     FROM m_fopump_check_model m
     LEFT JOIN t_fopump_check_header h ON h.model_id = m.id AND h.tanggal = ? AND h.status = 'submitted'
     LEFT JOIN m_user c ON c.id = h.checker_id
     LEFT JOIN m_user f ON f.id = h.foreman_id
     LEFT JOIN m_user s ON s.id = h.supervisor_id
     WHERE m.is_active = 1
     ORDER BY m.sort_order, m.id"
);
$stmt->execute([$d]);
$rows = $stmt->fetchAll();

// Summary counts
$counts = ['done' => 0, 'waiting' => 0, 'empty' => 0];
foreach ($rows as $r) { $counts[signoff_overall($r['header_id'] ? $r : null)['cls']]++; }

$role = user_signoff_role();
$canSign = in_array($role, ['checker', 'foreman', 'supervisor', 'admin'], true);

$base_url = '';
$active_nav = 'fopump-check-status';
$section_route = 'fopump_check_list.php';
$page_title = 'Status Approval';
$page_subtitle = 'FO Pump Check · posisi sign-off tiap model';
require __DIR__ . '/includes/app_top.php';
$export_route = 'fopump_check_list.php';
$export_dept = (int)$pdo->query("SELECT department_id FROM m_checksheet_section WHERE route='fopump_check_list.php' AND is_active=1 LIMIT 1")->fetchColumn();
require __DIR__ . '/includes/export_button.php';
?>

<div class="filter-bar" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">
    <form method="get" style="display:flex;gap:10px;align-items:center;">
        <label for="d_display" style="font-weight:600;">Tanggal</label>
        <input type="text" id="d_display" class="holiday-date-input date-filter-input"
               data-display data-alt="d" readonly
               value="<?= htmlspecialchars($d) ?>" max="<?= date('Y-m-d') ?>">
        <input type="hidden" id="d" name="d" value="<?= htmlspecialchars($d) ?>" onchange="this.form.submit()">
        <?php if (!$isToday): ?><a href="fopump_check_status.php" class="dept-switch-link">Hari ini</a><?php endif; ?>
    </form>
    <span style="color:#9aa1ab;">|</span>
    <span class="signoff-badge done">Selesai: <?= $counts['done'] ?></span>
    <span class="signoff-badge waiting">Dalam proses: <?= $counts['waiting'] ?></span>
    <span class="signoff-badge empty">Belum diisi: <?= $counts['empty'] ?></span>
    <?php if (!$isToday): ?><span class="import-hint">(history <?= htmlspecialchars(date('d/m/Y', strtotime($d))) ?>)</span><?php endif; ?>
</div>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th style="width:120px;">Model</th>
            <th style="width:150px;">Status</th>
            <th>Sign-off trail</th>
            <th style="width:90px;"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <?php
            $h = $r['header_id'] ? $r : null;
            $ov = signoff_overall($h);
            $next = signoff_next_role($h);
            $openHref = 'fopump_check_list.php?department_id=' . (int)$r['department_id'] . '&model_id=' . (int)$r['model_id'];
        ?>
        <tr>
            <td><b><?= htmlspecialchars($r['model_name']) ?></b></td>
            <td><span class="signoff-badge <?= $ov['cls'] ?>"><?= htmlspecialchars($ov['label']) ?></span></td>
            <td><?= signoff_render_stepper($h, $next) ?></td>
            <td>
                <?php if ($canSign && $isToday): ?>
                    <a class="cs-view-btn-sm" href="<?= htmlspecialchars($openHref) ?>">Buka &rarr;</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">Belum ada model.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<script src="assets/js/holiday-calendar.js?v=<?= @filemtime(__DIR__ . '/assets/js/holiday-calendar.js') ?: 1 ?>"></script>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>

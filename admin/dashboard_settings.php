<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dashboard.php';
require_admin();
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $freqs = $_POST['freq'] ?? [];
    $allowed = ['daily', 'weekly', 'monthly', 'as_needed'];
    $stmt = $pdo->prepare('UPDATE m_checksheet_section SET fill_frequency = ? WHERE id = ?');
    foreach ($freqs as $sid => $val) {
        $val = in_array($val, $allowed, true) ? $val : 'daily';
        $stmt->execute([$val, (int)$sid]);
    }
    header('Location: dashboard_settings.php?saved=1');
    exit;
}

$rows = $pdo->query(
    "SELECT s.id, s.name, s.route, COALESCE(s.fill_frequency,'daily') AS freq, d.name AS dept
     FROM m_checksheet_section s JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order, s.id"
)->fetchAll();

$base_url = '../';
$active_nav = 'mgmt-dashboard-settings';
$page_title = 'Dashboard Settings';
$page_subtitle = 'Management · Set which check sheets are required and how often';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Saved.</div><?php endif; ?>
<p class="import-hint" style="margin-bottom:12px;">
    The dashboard flags "not filled" only for check sheets due in their period.
    Choose <strong>As needed</strong> for ones that aren't required every day (e.g. only when there's data).
</p>

<form method="post">
    <input type="hidden" name="action" value="save">
    <div class="table-scroll">
    <table class="admin-table">
        <thead>
            <tr><th>Department</th><th>Check Sheet</th><th style="width:220px;">Required frequency</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['dept']) ?></td>
                <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                <td>
                    <select name="freq[<?= $r['id'] ?>]">
                        <?php foreach (['daily', 'weekly', 'monthly', 'as_needed'] as $f): ?>
                            <option value="<?= $f ?>" <?= $r['freq'] === $f ? 'selected' : '' ?>><?= htmlspecialchars(dashboard_freq_label($f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="margin-top:14px;"><button type="submit" class="btn">Save</button></div>
</form>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

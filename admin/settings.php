<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pin') {
    $pin = trim($_POST['delete_pin'] ?? '');
    if (!preg_match('/^\d{4}$/', $pin)) {
        $error = 'PIN must be exactly 4 digits.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO m_setting (setting_key, value) VALUES ("delete_pin", ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $stmt->execute([$pin]);
        header('Location: settings.php?saved=1');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT value FROM m_setting WHERE setting_key = "delete_pin"');
$stmt->execute();
$currentPin = $stmt->fetchColumn() ?: '1234';

$base_url = '../';
$active_nav = 'config-settings';
$page_title = 'Settings';
$page_subtitle = 'Master Data · App-wide settings';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save_pin">
    <div class="form-grid">
        <div class="form-row">
            <label>Delete PIN</label>
            <input type="text" name="delete_pin" value="<?= htmlspecialchars($currentPin) ?>" maxlength="4" pattern="\d{4}" inputmode="numeric" required>
            <p class="import-hint">Required before deleting a submitted checksheet from any "View Checksheets" list.</p>
        </div>
    </div>
    <div class="form-row">
        <button type="submit" class="btn">Save</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

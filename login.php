<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

$next = $_GET['next'] ?? ($_POST['next'] ?? 'index.php');
// Only allow relative redirects within this app.
if (!preg_match('#^[a-zA-Z0-9_\-./]+\.php(\?.*)?$#', $next)) {
    $next = 'index.php';
}

// Already picked an identity this session — no need to ask again.
if (!empty($_SESSION['auth_user']['name'])) {
    header('Location: ' . $next);
    exit;
}

const ADMIN_PIN = '0709';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
    $pin = trim($_POST['pin'] ?? '');
    if ($pin !== ADMIN_PIN) {
        $error = 'Incorrect password.';
    } else {
        $_SESSION['auth_user'] = ['name' => 'Admin', 'role' => 'admin'];
        header('Location: ' . $next);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'user_login') {
    $_SESSION['auth_user'] = ['name' => 'User', 'role' => 'user'];
    header('Location: ' . $next);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet - Login</title>
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

    <h1>Who's this?</h1>
    <p class="landing-hint">Pick how you're signing in.</p>

    <?php if ($error): ?><div class="alert alert-error" style="max-width: 420px; margin: 0 auto 16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="dept-grid" style="max-width: 480px; margin: 0 auto;">
        <form method="post" class="dept-card" style="cursor: default;">
            <input type="hidden" name="action" value="admin_login">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="dept-icon">AD</div>
            <div class="dept-name">Admin</div>
            <input type="password" name="pin" placeholder="Password" required
                   style="margin-top:10px; width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #d7dbe2; border-radius:6px; font-size:14px; text-align:center;">
            <button type="submit" class="dept-go" style="border:none; background:none; cursor:pointer; font:inherit; padding:8px 0 0; width:100%;">Continue &rarr;</button>
        </form>

        <form method="post" class="dept-card" style="cursor: default;">
            <input type="hidden" name="action" value="user_login">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="dept-icon">US</div>
            <div class="dept-name">User</div>
            <button type="submit" class="dept-go" style="border:none; background:none; cursor:pointer; font:inherit; padding:8px 0 0; width:100%;">Continue &rarr;</button>
        </form>
    </div>
</div>
</body>
</html>

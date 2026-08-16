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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pick') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM m_user WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $picked = $stmt->fetch();

    if (!$picked) {
        $error = 'User not found.';
    } else {
        $_SESSION['auth_user'] = ['name' => $picked['name'], 'role' => $picked['role']];
        header('Location: ' . $next);
        exit;
    }
}

$users = $pdo->query('SELECT * FROM m_user WHERE is_active = 1 ORDER BY name')->fetchAll();
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
    <p class="landing-hint">Pick your name to continue.</p>

    <?php if ($error): ?><div class="alert alert-error" style="max-width: 420px; margin: 0 auto 16px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="pick">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <div class="dept-grid">
            <?php foreach ($users as $u): ?>
                <button type="submit" name="user_id" value="<?= $u['id'] ?>" class="dept-card" style="border: none; cursor: pointer; font: inherit;">
                    <div class="dept-icon"><?= strtoupper(substr($u['name'], 0, 2)) ?></div>
                    <div class="dept-name"><?= htmlspecialchars($u['name']) ?></div>
                    <div class="dept-go">Continue &rarr;</div>
                </button>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <p class="empty">No users set up yet.</p>
            <?php endif; ?>
        </div>
    </form>
</div>
</body>
</html>

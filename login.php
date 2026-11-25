<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db();

$next = $_GET['next'] ?? ($_POST['next'] ?? 'dashboard.php');
// Only allow relative redirects within this app.
if (!preg_match('#^[a-zA-Z0-9_\-./]+\.php(\?.*)?$#', $next)) {
    $next = 'index.php';
}

// Already signed in this session — no need to ask again.
if (!empty($_SESSION['auth_user']['name'])) {
    header('Location: ' . $next);
    exit;
}

/*
 * Interim sign-in: pick your name from the list, no password. The role comes
 * from the chosen m_user row. This is temporary until One YADIN (SSO) is wired
 * up — at that point YADIN authenticates the person and hands their identity
 * (NIK + name) to a YADIN handler that maps it to an m_user row and calls
 * establish_session_for(), so people never touch this page. See includes/auth.php.
 */
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, name, role, title FROM m_user WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) {
        $error = 'Please choose who you are.';
    } else {
        establish_session_for($u);
        header('Location: ' . $next);
        exit;
    }
}

$users = $pdo->query(
    "SELECT id, name, role FROM m_user
     WHERE is_active = 1
     ORDER BY FIELD(role, 'superadmin', 'admin', 'user'), name"
)->fetchAll();

$roleLabel = ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'user' => 'User'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Production Check Sheet — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php $ver = @filemtime(__DIR__ . '/assets/css/app.css') ?: '1'; ?>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= $ver ?>">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(900px 500px at 15% -10%, rgba(166,58,47,.14), transparent 60%),
                radial-gradient(900px 600px at 110% 120%, rgba(127,42,34,.12), transparent 55%),
                var(--bg);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .login-head {
            background: linear-gradient(145deg, #9b3328, var(--brand-deep));
            padding: 30px 28px 26px;
            color: #fff;
            text-align: center;
        }
        .login-mark {
            width: 56px; height: 56px; margin: 0 auto 14px;
            border-radius: 15px; background: #fff; color: var(--brand-ink);
            display: flex; align-items: center; justify-content: center;
            font: 700 20px Oswald, sans-serif;
            box-shadow: 0 4px 14px rgba(0,0,0,.22);
        }
        .login-head h1 {
            margin: 0; font: 700 21px Oswald, sans-serif;
            text-transform: uppercase; letter-spacing: .03em;
        }
        .login-head p { margin: 4px 0 0; font-size: 12.5px; color: rgba(255,255,255,.78); }
        .login-body { padding: 26px 28px 28px; }
        .login-body h2 { margin: 0 0 4px; font: 600 16px Inter, sans-serif; color: var(--fg); }
        .login-body .sub { margin: 0 0 18px; font-size: 13px; color: var(--fg-3); }
        .name-field { display: flex; flex-direction: column; gap: 7px; }
        .name-field label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; color: var(--fg-3);
        }
        .name-field select {
            padding: 13px 14px;
            border: 1px solid var(--line-strong);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            color: var(--fg);
            background: var(--surface-2);
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a6968f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 18px;
        }
        .name-field select:focus {
            outline: none; border-color: var(--brand);
            box-shadow: 0 0 0 4px var(--brand-soft); background: var(--surface);
        }
        .login-btn {
            margin-top: 18px; width: 100%;
            padding: 13px; border: none; border-radius: 12px;
            background: var(--brand); color: #fff;
            font: 600 15px Inter, sans-serif; cursor: pointer;
            box-shadow: 0 4px 14px rgba(127,42,34,.28);
            transition: background .15s;
        }
        .login-btn:hover { background: var(--brand-ink); }
        .login-alert {
            background: var(--crit-soft); color: var(--crit);
            border-radius: 10px; padding: 10px 13px; font-size: 13px; font-weight: 500;
            margin-bottom: 16px;
        }
        .login-foot {
            margin: 20px 0 0; text-align: center; font-size: 12.5px; color: var(--fg-3);
        }
        .login-foot a { color: var(--brand); font-weight: 600; text-decoration: none; }
        .login-foot a:hover { text-decoration: underline; }
        .login-note {
            margin: 14px 0 0; text-align: center; font-size: 11.5px; color: var(--fg-3);
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-head">
        <div class="login-mark">DP</div>
        <h1>Daily Prod</h1>
        <p>Production Check Sheet System</p>
    </div>
    <div class="login-body">
        <h2>Sign in</h2>
        <p class="sub">Choose your name to continue.</p>

        <?php if ($error): ?><div class="login-alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="name-field">
                <label for="user_id">Your name</label>
                <select id="user_id" name="user_id" required autofocus>
                    <option value="" disabled selected>— Select your name —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>">
                            <?= htmlspecialchars($u['name']) ?><?php if ($u['role'] !== 'user'): ?> (<?= $roleLabel[$u['role']] ?? ucfirst($u['role']) ?>)<?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="login-btn">Sign in &rarr;</button>
        </form>

        <p class="login-note">Temporary sign-in. One YADIN login is coming soon.</p>

        <p class="login-foot">
            <a href="dashboard_public.php">&#128202; View today's status without signing in &rarr;</a>
        </p>
    </div>
</div>
</body>
</html>

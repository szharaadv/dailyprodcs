<?php
require_once __DIR__ . '/../config/db.php';
/**
 * Expects before include:
 * $base_url        - '' for root pages
 * $active_nav      - see sidebar.php
 * $page_title      - main heading text
 * $page_subtitle   - small text under heading
 */
$base_url = $base_url ?? '';
$page_title = $page_title ?? '';
$page_subtitle = $page_subtitle ?? '';

// In-app notifications (Admin → users) shown in the top-bar bell.
require_once __DIR__ . '/notifications.php';
$notifUnread = 0;
$notifList = [];
if (current_user() !== null) {
    $__notifPdo = get_db();
    $notifUnread = notif_unread_count($__notifPdo);
    $notifList = notif_list($__notifPdo, 15);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?: 'Daily Production Check Sheet') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php
    $cssFiles = ['app.css', 'style.css', 'admin.css'];
    foreach ($cssFiles as $cssFile):
        $cssPath = __DIR__ . '/../assets/css/' . $cssFile;
        $ver = is_file($cssPath) ? filemtime($cssPath) : '1';
    ?>
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/<?= $cssFile ?>?v=<?= $ver ?>">
    <?php endforeach; ?>
    <script>
        // Autosave-to-draft is off while editing an already-submitted record (an
        // approved edit unlock) — there we're changing a submitted sheet, not a draft.
        const AUTOSAVE_ENABLED = <?= json_encode(empty($editing_unlocked)) ?>;
    </script>
    <script src="<?= $base_url ?>assets/js/autosave-draft.js?v=<?= @filemtime(__DIR__ . '/../assets/js/autosave-draft.js') ?: 1 ?>"></script>
    <script src="<?= $base_url ?>assets/js/checksheet-validate.js?v=<?= @filemtime(__DIR__ . '/../assets/js/checksheet-validate.js') ?: 1 ?>"></script>
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <div class="app-topbar">
            <div class="topbar-heading">
                <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Toggle menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <div>
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                    <?php if (!empty($breadcrumb)): ?>
                        <nav class="topbar-breadcrumb">
                            <?php foreach ($breadcrumb as $i => $crumb): ?>
                                <?php if ($i > 0): ?><span class="crumb-sep">&rsaquo;</span><?php endif; ?>
                                <a class="crumb" href="<?= $base_url . $crumb['href'] ?>" title="<?= htmlspecialchars($crumb['title'] ?? '') ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php elseif ($page_subtitle): ?><p><?= htmlspecialchars($page_subtitle) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="topbar-right">
                <?php if (current_user() !== null): ?>
                <div class="notif-wrap">
                    <button type="button" class="notif-bell" id="notif-bell" aria-label="Notifikasi" aria-expanded="false" data-mark-url="<?= $base_url ?>ajax/mark_notifications_read.php" data-poll-url="<?= $base_url ?>ajax/notifications_poll.php" data-latest-id="<?= (int)($notifList[0]['id'] ?? 0) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span class="notif-badge" id="notif-badge"<?= $notifUnread > 0 ? '' : ' hidden' ?>><?= $notifUnread > 99 ? '99+' : (int)$notifUnread ?></span>
                    </button>
                    <div class="notif-panel" id="notif-panel" hidden>
                        <div class="notif-panel-head"><span>Notifikasi</span></div>
                        <div class="notif-panel-list">
                            <?php if (!$notifList): ?>
                                <div class="notif-empty">Belum ada notifikasi.</div>
                            <?php else: foreach ($notifList as $n): ?>
                                <div class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>">
                                    <div class="notif-item-top">
                                        <span class="notif-type notif-type-<?= htmlspecialchars($n['type']) ?>"><?= htmlspecialchars(ucfirst($n['type'])) ?></span>
                                        <span class="notif-time"><?= htmlspecialchars(notif_time_ago($n['created_at'])) ?></span>
                                    </div>
                                    <div class="notif-item-title"><?= htmlspecialchars($n['title']) ?></div>
                                    <?php if (!empty($n['body'])): ?><div class="notif-item-body"><?= nl2br(htmlspecialchars($n['body'])) ?></div><?php endif; ?>
                                    <?php if (!empty($n['created_by'])): ?><div class="notif-item-by">&mdash; <?= htmlspecialchars($n['created_by']) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <?php if (is_admin()): ?>
                        <a class="notif-panel-foot" href="<?= $base_url ?>admin/notifications.php">Kelola / kirim notifikasi &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="topbar-date"><?= date('D, d M Y') ?></div>
                <?php if (!empty($me)): ?>
                <div class="topbar-user">
                    <div class="tu-avatar">
                        <span><?= htmlspecialchars(user_initials($me['name'])) ?></span>
                    </div>
                    <div class="tu-meta">
                        <div class="tu-name" title="<?= htmlspecialchars($me['name']) ?>"><?= htmlspecialchars($me['name']) ?></div>
                        <div class="tu-role"><?= htmlspecialchars($me['role']) ?></div>
                    </div>
                    <a class="tu-logout" href="<?= $base_url ?>logout.php">Logout</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="app-content<?= ($active_nav ?? '') !== 'checksheet' ? ' app-content-doc' : '' ?>">

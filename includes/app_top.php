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

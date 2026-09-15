<?php
/**
 * In-app notifications: Admin-sent announcements / updates / reminders shown to
 * users via the bell in the top bar. Read state is per viewer; the shared Admin
 * identity (no m_user id) is recorded as user_id = 0.
 */
require_once __DIR__ . '/auth.php';

/** The current viewer's id for read-tracking (0 for the shared Admin login). */
function notif_viewer_id(): int
{
    return (int)(current_user()['id'] ?? 0);
}

/** WHERE fragment + params selecting notifications visible to the viewer. */
function _notif_visible_where(int $viewerId): array
{
    // Everyone sees broadcasts; a real user also sees ones addressed to them.
    return ["(n.audience = 'all' OR (n.audience = 'user' AND n.user_id = ?))", [$viewerId]];
}

/** How many visible notifications the viewer hasn't read yet. */
function notif_unread_count(PDO $pdo): int
{
    $vid = notif_viewer_id();
    [$w, $p] = _notif_visible_where($vid);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM t_notification n
         LEFT JOIN t_notification_read r ON r.notification_id = n.id AND r.user_id = ?
         WHERE $w AND r.notification_id IS NULL"
    );
    $stmt->execute(array_merge([$vid], $p));
    return (int)$stmt->fetchColumn();
}

/** Recent notifications visible to the viewer, each tagged is_read. */
function notif_list(PDO $pdo, int $limit = 20): array
{
    $vid = notif_viewer_id();
    [$w, $p] = _notif_visible_where($vid);
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT n.*, (r.notification_id IS NOT NULL) AS is_read
         FROM t_notification n
         LEFT JOIN t_notification_read r ON r.notification_id = n.id AND r.user_id = ?
         WHERE $w
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT $limit"
    );
    $stmt->execute(array_merge([$vid], $p));
    return $stmt->fetchAll();
}

/** Visible notifications newer than $afterId (for live polling / toasts). */
function notif_since(PDO $pdo, int $afterId, int $limit = 10): array
{
    $vid = notif_viewer_id();
    [$w, $p] = _notif_visible_where($vid);
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare(
        "SELECT n.id, n.title, n.body, n.type, n.created_by, n.created_at
         FROM t_notification n
         WHERE $w AND n.id > ?
         ORDER BY n.id ASC
         LIMIT $limit"
    );
    $stmt->execute(array_merge($p, [$afterId]));
    return $stmt->fetchAll();
}

/** Mark every notification currently visible to the viewer as read. */
function notif_mark_all_read(PDO $pdo): void
{
    $vid = notif_viewer_id();
    [$w, $p] = _notif_visible_where($vid);
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO t_notification_read (notification_id, user_id)
         SELECT n.id, ? FROM t_notification n WHERE $w"
    );
    $stmt->execute(array_merge([$vid], $p));
}

/** Create a notification. audience 'all' or 'user' (+ $userId). Returns new id. */
function notif_create(PDO $pdo, string $title, string $body, string $type, string $audience, ?int $userId, ?string $createdBy): int
{
    $type = in_array($type, ['info', 'update', 'reminder'], true) ? $type : 'info';
    $audience = $audience === 'user' ? 'user' : 'all';
    $userId = $audience === 'user' ? ($userId ?: null) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO t_notification (title, body, type, audience, user_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([trim($title), trim($body) !== '' ? trim($body) : null, $type, $audience, $userId, $createdBy]);
    return (int)$pdo->lastInsertId();
}

/** Short "x minutes/hours/days ago" style timestamp for display. */
function notif_time_ago(?string $at): string
{
    if (!$at) return '';
    $ts = strtotime($at);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d/m/Y', $ts);
}

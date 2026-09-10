<?php
/**
 * Current-user / session layer.
 *
 * No password login — identity is picked from the Users list (m_user)
 * via login.php, a simple "who are you?" name picker.
 */
require_once __DIR__ . '/../config/db.php';

/** The logged-in user, or null if nobody is authenticated. */
function current_user(): ?array
{
    if (!empty($_SESSION['auth_user']['name'])) {
        return $_SESSION['auth_user'];
    }
    return null;
}

/**
 * The current user's FO Pump Check sign-off role, derived from their m_user
 * `title`: Operator/Staff → 'checker', Foreman → 'foreman', Supervisor →
 * 'supervisor'. Admin → 'admin' (can act at any level). Anyone else → null
 * (not part of the sign-off flow). Falls back to a DB lookup for sessions that
 * signed in before `title` was stored in the session.
 */
/**
 * The current user's raw job title (Operator/Staff/Foreman/Supervisor/…), or
 * null. Falls back to a DB lookup + caches it, for sessions that signed in
 * before `title` was stored in the session.
 */
function current_user_title(): ?string
{
    $u = current_user();
    if ($u === null) return null;
    if (array_key_exists('title', $u)) return $u['title'];

    $title = null;
    if (!empty($u['id'])) {
        $stmt = get_db()->prepare('SELECT title FROM m_user WHERE id = ?');
        $stmt->execute([$u['id']]);
        $title = $stmt->fetchColumn() ?: null;
    }
    $_SESSION['auth_user']['title'] = $title; // cache for next time
    return $title;
}

function user_signoff_role(): ?string
{
    $u = current_user();
    if ($u === null) return null;
    if (($u['role'] ?? '') === 'admin') return 'admin';

    switch (current_user_title()) {
        case 'Operator':
        case 'Staff':
            return 'checker';
        case 'Foreman':
            return 'foreman';
        case 'Supervisor':
            return 'supervisor';
        default:
            return null;
    }
}

/**
 * Filter a roster (rows with a `title` column) down to the given job title(s),
 * matched case-insensitively. Used so a checksheet's person picker only lists
 * people who actually hold that role — e.g. the "Operator" / "Checker" dropdown
 * shows Operators, the "Foreman" dropdown shows Foremen. Pass one or more
 * titles: users_by_title($people, 'Operator') or (..., 'Operator', 'Staff').
 */
function users_by_title(array $rows, string ...$titles): array
{
    $want = array_map(fn($t) => strtolower(trim($t)), $titles);
    return array_values(array_filter(
        $rows,
        fn($r) => in_array(strtolower(trim((string)($r['title'] ?? ''))), $want, true)
    ));
}

/** Redirect to login if nobody is authenticated. */
function require_login(): void
{
    if (current_user() === null) {
        header('Location: ' . base_prefix() . 'login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
}

/** Whether the current session is the Admin identity (not the shared User one). */
function is_admin(): bool
{
    $u = current_user();
    return $u !== null && ($u['role'] ?? '') === 'admin';
}

/**
 * Gate an admin-only page: must be logged in AND be Admin, else bounce to
 * index. Every admin-only page lives one level under /admin, so the
 * redirect target is relative to that, not to the site root.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ../index.php');
        exit;
    }
}

/** Initials for the avatar fallback, e.g. "Budi Santoso" -> "BS". */
function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0][0] ?? '';
    $last  = count($parts) > 1 ? end($parts)[0] : ($parts[0][1] ?? '');
    return strtoupper($first . $last);
}

/** Best-effort relative prefix so redirects work from root pages. */
function base_prefix(): string
{
    return '';
}

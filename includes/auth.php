<?php
/**
 * Current-user / session layer.
 *
 * Interim login has no password — identity is picked from the Users list
 * (m_user) via login.php, a simple "who are you?" name picker. The role comes
 * from the chosen m_user row.
 *
 * This is also the integration seam for One YADIN (SSO): once YADIN
 * authenticates a person it will hand us their identity (NIK + name), which the
 * YADIN handler maps to an m_user row and passes to establish_session_for().
 * Role handling then stays identical however the person signed in.
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
 * Establish the signed-in session from an m_user row (expects name + role, and
 * optionally id + title). The single place a session is created — shared by the
 * interim name login and, later, the One YADIN SSO handler — so the role granted
 * here is the same no matter how the person was authenticated.
 */
function establish_session_for(array $u): void
{
    $_SESSION['auth_user'] = [
        'id'    => isset($u['id']) ? (int) $u['id'] : null,
        'name'  => $u['name'],
        'role'  => $u['role'] ?? 'user',
        'title' => $u['title'] ?? null,
    ];
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
    if (in_array($u['role'] ?? '', ['admin', 'superadmin'], true)) return 'admin';

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

/**
 * Whether the current session may open admin (management) pages. Both 'admin'
 * and 'superadmin' qualify — superadmin is admin plus the superadmin-only
 * pages gated by require_superadmin(). A plain 'user' never qualifies.
 */
function is_admin(): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'] ?? '', ['admin', 'superadmin'], true);
}

/** Whether the current session is a Super Admin (the highest role). */
function is_superadmin(): bool
{
    $u = current_user();
    return $u !== null && ($u['role'] ?? '') === 'superadmin';
}

/**
 * Gate an admin page: must be logged in AND be admin or superadmin, else bounce
 * to index. Every admin page lives one level under /admin, so the redirect
 * target is relative to that, not to the site root.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ../index.php?denied=1');
        exit;
    }
}

/**
 * Gate a superadmin-only page (Users & roles, Dashboard Settings, Document
 * Numbers, Notifications): must be logged in AND be superadmin, else bounce to
 * index. A plain admin is turned away here.
 */
function require_superadmin(): void
{
    require_login();
    if (!is_superadmin()) {
        header('Location: ../index.php?denied=1');
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

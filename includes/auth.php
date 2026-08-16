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

/** Redirect to login if nobody is authenticated. */
function require_login(): void
{
    if (current_user() === null) {
        header('Location: ' . base_prefix() . 'login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
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

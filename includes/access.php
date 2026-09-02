<?php
/**
 * Role-based access control for check-sheet routes (EXAMPLE / starting point).
 *
 * Map a route (the m_checksheet_section.route value / the list PHP file) to the
 * list of job titles allowed to open it. A route not listed here is open to any
 * logged-in user (current behaviour). Admin can always open everything.
 *
 * To restrict another section, just add a line here — no other code needed:
 *   'assembly_list.php' => ['Operator', 'Staff', 'Foreman', 'Supervisor'],
 */
require_once __DIR__ . '/auth.php';

$GLOBALS['ROUTE_ACCESS'] = [
    // FO Pump Check: only people in the sign-off flow may open it.
    'fopump_check_list.php' => ['Operator', 'Staff', 'Foreman', 'Supervisor'],
];

/** Allowed titles for a route, or null when the route is open to everyone. */
function route_allowed_titles(string $route): ?array
{
    return $GLOBALS['ROUTE_ACCESS'][$route] ?? null;
}

/** Whether the current user may open the given route. Admin always may. */
function can_access_route(string $route): bool
{
    if (is_admin()) return true;
    $allowed = route_allowed_titles($route);
    if ($allowed === null) return true; // unrestricted
    return in_array(current_user_title(), $allowed, true);
}

/** Hard gate for a page: bounce to the section picker if not allowed. */
function require_route_access(string $route): void
{
    require_login();
    if (!can_access_route($route)) {
        header('Location: index.php?denied=' . urlencode($route));
        exit;
    }
}

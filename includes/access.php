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

/* ------------------------------------------------------------------
 * Per-user routing, by "access unit".
 *
 * A unit is normally a whole department — but FO Pump, though it lives inside
 * the Assembling department, is promoted to its own card on the picker, so it
 * is its own unit. That way ticking an Assembling sheet (e.g. Torque) opens
 * Assembling but NOT FO Pump, and vice-versa.
 *
 * A non-admin's units are derived from the sections ticked for them in
 * Management > Users. No ticked section = no access. Admin / superadmin see all.
 * ------------------------------------------------------------------ */

/** The access-unit key for a (department, section-group) pair. FO Pump is its
 *  own unit; everything else is keyed by its department. */
function access_unit_key(int $departmentId, ?string $groupLabel): string
{
    return $departmentId . ':' . ($groupLabel === 'FO Pump' ? 'fopump' : 'dept');
}

/**
 * The set of unit keys the current user may open: null = unrestricted (admin /
 * superadmin), otherwise the units of their ticked sections (possibly empty =
 * no access). Fails open (null) only if the tables aren't there yet.
 */
function user_access_units(): ?array
{
    if (is_admin()) return null;
    $u = current_user();
    if (!$u || empty($u['id'])) return []; // identified but no DB id → no access
    try {
        $stmt = get_db()->prepare(
            'SELECT DISTINCT s.department_id, s.group_label
             FROM m_user_section us
             JOIN m_checksheet_section s ON s.id = us.section_id
             WHERE us.user_id = ?'
        );
        $stmt->execute([(int) $u['id']]);
        $units = [];
        foreach ($stmt->fetchAll() as $r) {
            $units[] = access_unit_key((int) $r['department_id'], $r['group_label']);
        }
        return array_values(array_unique($units));
    } catch (Throwable $e) {
        return null; // tables missing → don't lock anyone out
    }
}

/** Whether the user may open the unit a (department, group) belongs to. */
function can_access_unit(int $departmentId, ?string $groupLabel): bool
{
    $units = user_access_units();
    if ($units === null) return true;
    return in_array(access_unit_key($departmentId, $groupLabel), $units, true);
}

/** Convenience: the "department proper" unit (everything except FO Pump). */
function can_access_department(int $departmentId): bool
{
    return can_access_unit($departmentId, null);
}

/**
 * Hard gate for a check-sheet list page. Works out which unit this page belongs
 * to from its own route (the running script) within the given department, so a
 * FO Pump page is gated by FO Pump access and the rest of Assembling isn't.
 */
function require_department_access(int $departmentId): void
{
    require_login();
    $route = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $group = null;
    try {
        $stmt = get_db()->prepare('SELECT group_label FROM m_checksheet_section WHERE department_id = ? AND route = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$departmentId, $route]);
        $g = $stmt->fetchColumn();
        $group = ($g === false) ? null : $g;
    } catch (Throwable $e) {
        $group = null;
    }
    if (!can_access_unit($departmentId, $group)) {
        header('Location: index.php?denied=1');
        exit;
    }
}

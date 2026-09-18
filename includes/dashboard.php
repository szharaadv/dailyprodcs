<?php
/**
 * Dashboard status: for each active check sheet, whether it's been filled for
 * the period it's due in (today / this week / this month), so a supervisor can
 * see at a glance what's still outstanding today.
 *
 * States: 'filled' (done), 'missing' (due & not filled), 'optional' (as-needed,
 * never flagged), 'off' (a daily sheet on a non-working day). "Which sheets are
 * required, and how often" comes from m_checksheet_section.fill_frequency, which
 * Admin sets in Management → Dashboard Settings.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/calendar_lib.php';

/** Is $date (Y-m-d) a working day (not weekend/holiday)? */
function _dash_is_workday(PDO $pdo, string $date): bool
{
    return in_array($date, get_working_days($pdo, $date, $date), true);
}

/** Does this section have data for the current period? Per-route, best-effort. */
function _dash_filled(PDO $pdo, string $route, int $dept): bool
{
    $today = date('Y-m-d');
    $y = (int)date('Y'); $m = (int)date('n'); $d = (int)date('j');
    $wk = min(5, intdiv($d - 1, 7) + 1); // 3S-3T week-of-month (1..5)
    try {
        switch ($route) {
            case 'painting_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_checksheet_header WHERE department_id=? AND tanggal=? AND status='submitted' LIMIT 1", [$dept, $today]);
            case 'assembly_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_assy_header WHERE department_id=? AND tanggal=? AND status='submitted' LIMIT 1", [$dept, $today]);
            case 'fopump_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_fopump_header WHERE department_id=? AND tanggal=? AND status='submitted' LIMIT 1", [$dept, $today]);
            case 'painting_prod_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_painting_prod_header WHERE department_id=? AND tanggal=? AND status='submitted' LIMIT 1", [$dept, $today]);
            case 'bakeoven_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_bakeoven_detail dt JOIN t_bakeoven_header h ON h.id=dt.header_id JOIN m_bakeoven b ON b.id=h.bakeoven_id WHERE b.department_id=? AND h.month=? AND h.year=? AND dt.day=? AND dt.actual_temp<>'' LIMIT 1", [$dept, $m, $y, $d]);
            case 'washing_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_washing_detail dt JOIN t_washing_header h ON h.id=dt.header_id WHERE h.department_id=? AND h.month=? AND h.year=? AND dt.day=? AND (dt.ganti_air<>'' OR dt.temperatur_air<>'' OR dt.total_acid<>'' OR dt.checker_id IS NOT NULL) LIMIT 1", [$dept, $m, $y, $d]);
            case 'paint_viscosity_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_paint_viscosity_detail dt JOIN t_paint_viscosity_header h ON h.id=dt.header_id WHERE h.department_id=? AND h.month=? AND h.year=? AND dt.day=? AND dt.actual_result<>'' LIMIT 1", [$dept, $m, $y, $d]);
            case 'sub_assembly_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_jig_detail dt JOIN t_jigheader h ON h.id=dt.header_id JOIN m_jig j ON j.id=h.jig_id WHERE j.department_id=? AND h.month=? AND h.year=? AND dt.day=? AND dt.result<>'' LIMIT 1", [$dept, $m, $y, $d]);
            case '3s3t_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_3s3t_detail dt JOIN t_3s3t_header h ON h.id=dt.header_id WHERE h.department_id=? AND h.month=? AND h.year=? AND dt.`week{$wk}`<>'' AND dt.`week{$wk}` IS NOT NULL LIMIT 1", [$dept, $m, $y]);
            case 'fopump_check_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_fopump_check_header WHERE department_id=? AND status='submitted' AND DATE(created_at)=? LIMIT 1", [$dept, $today]);
            case 'fopump_test_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_fopump_test_header WHERE department_id=? AND status='submitted' AND DATE(created_at)=? LIMIT 1", [$dept, $today]);
            case 'fopump_reject_list.php':
                return _dash_exists($pdo, "SELECT 1 FROM t_fopump_reject_header WHERE department_id=? AND month=? AND year=? LIMIT 1", [$dept, $m, $y]);
        }
    } catch (Throwable $e) {
        // Unknown/failed check → treat as not-filled but don't break the page.
    }
    return false;
}

function _dash_exists(PDO $pdo, string $sql, array $params): bool
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/** All active sections with today's fill status, ordered by department. */
function dashboard_sections(PDO $pdo): array
{
    $isWork = _dash_is_workday($pdo, date('Y-m-d'));
    $base = "SELECT s.id, s.route, s.name, s.department_id, s.group_label, %s AS freq, d.name AS dept_name
             FROM m_checksheet_section s JOIN m_department d ON d.id = s.department_id
             WHERE s.is_active = 1 ORDER BY d.sort_order, s.sort_order, s.id";
    try {
        $sections = $pdo->query(sprintf($base, "COALESCE(s.fill_frequency,'daily')"))->fetchAll();
    } catch (Throwable $e) {
        // fill_frequency column not present yet (migration not run): default all
        // to 'daily' so the dashboard still works instead of erroring.
        $sections = $pdo->query(sprintf($base, "'daily'"))->fetchAll();
    }

    $out = [];
    foreach ($sections as $s) {
        $freq = $s['freq'];
        $filled = _dash_filled($pdo, $s['route'], (int)$s['department_id']);
        if ($freq === 'as_needed')       $state = $filled ? 'filled' : 'optional';
        elseif ($filled)                 $state = 'filled';
        elseif ($freq === 'daily' && !$isWork) $state = 'off';
        else                             $state = 'missing';
        $s['filled'] = $filled;
        $s['state'] = $state;
        $out[] = $s;
    }
    return $out;
}

/** Human labels for a frequency / state. */
function dashboard_freq_label(string $f): string
{
    return ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'as_needed' => 'As needed'][$f] ?? $f;
}

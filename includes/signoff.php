<?php
/**
 * FO Pump Check role-based sign-off queue helpers.
 *
 * "Pending for me" = a submitted record whose line for my role isn't signed
 * yet: Foreman → foreman_at is NULL; Supervisor → supervisor_at is NULL.
 * Admin sees every still-incomplete record (either line unsigned) as an
 * overview. Checkers are fillers, not approvers, so they have no queue.
 */
require_once __DIR__ . '/auth.php';

/**
 * Checksheet types that carry the Checker → Foreman → Supervisor sign-off.
 * Each header table has checker/foreman/supervisor _id + _at columns (the
 * "checker" id column may be named differently, e.g. operator_id) and a
 * `tanggal` day column. Add a new checksheet here to fold it into the queue.
 */
function signoff_types(): array
{
    return [
        'fopump_check' => [
            'label' => 'FO Pump Check', 'route' => 'fopump_check_list.php',
            'table' => 't_fopump_check_header', 'checker_col' => 'checker_id', 'period' => 'daily',
            'join' => 'JOIN m_fopump_check_model m ON m.id = h.model_id',
            'title_expr' => 'm.name', 'order' => 'm.sort_order, m.id',
        ],
        'assy' => [
            'label' => 'Torque', 'route' => 'assembly_list.php',
            'table' => 't_assy_header', 'checker_col' => 'checker_id', 'period' => 'daily',
            'join' => 'JOIN m_assy_model m ON m.id = h.model_id',
            'title_expr' => "CONCAT(m.name, IFNULL(CONCAT(' · Eng ', NULLIF(h.no_engine, '')), ''))",
            'order' => 'm.sort_order, m.id',
        ],
        'painting' => [
            'label' => 'Painting', 'route' => 'painting_list.php',
            'table' => 't_checksheet_header', 'checker_col' => 'checker_id', 'period' => 'daily',
            'join' => 'JOIN m_condition m ON m.id = h.condition_id',
            'title_expr' => "CONCAT(m.name, ' · ', DATE_FORMAT(h.tanggal, '%d/%m'))",
            'order' => 'm.sort_order, m.id',
        ],
        'fopump' => [
            'label' => 'FO Pump Report', 'route' => 'fopump_list.php',
            'table' => 't_fopump_header', 'checker_col' => 'operator_id', 'period' => 'daily',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT('Report ', DATE_FORMAT(h.tanggal, '%d/%m/%Y'))",
            'order' => 'h.id',
        ],
        'painting_prod' => [
            'label' => 'Painting Report', 'route' => 'painting_prod_list.php',
            'table' => 't_painting_prod_header', 'checker_col' => 'checker_id', 'period' => 'daily',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT('Report ', DATE_FORMAT(h.tanggal, '%d/%m/%Y'))",
            'order' => 'h.id',
        ],
        'fopump_test' => [
            'label' => 'FO Pump Test', 'route' => 'fopump_test_list.php',
            'table' => 't_fopump_test_header', 'checker_col' => 'checker_id', 'period' => 'ongoing',
            'join' => 'JOIN m_fopump_test_model m ON m.id = h.model_id',
            'title_expr' => 'm.name', 'order' => 'm.id',
        ],
        // --- Monthly grid checksheets (per-month sign-off) ---
        'paint_viscosity' => [
            'label' => 'Paint Viscosity', 'route' => 'paint_viscosity_list.php',
            'table' => 't_paint_viscosity_header', 'checker_col' => 'checker_id', 'period' => 'monthly',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT(LPAD(h.month,2,'0'), '/', h.year)", 'order' => 'h.id',
        ],
        'washing' => [
            'label' => 'Washing', 'route' => 'washing_list.php',
            'table' => 't_washing_header', 'checker_col' => 'checker_id', 'period' => 'monthly',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT(LPAD(h.month,2,'0'), '/', h.year)", 'order' => 'h.id',
        ],
        'fopump_reject' => [
            'label' => 'FO Pump Reject', 'route' => 'fopump_reject_list.php',
            'table' => 't_fopump_reject_header', 'checker_col' => 'checker_id', 'period' => 'monthly',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT(LPAD(h.month,2,'0'), '/', h.year)", 'order' => 'h.id',
        ],
        '3s3t' => [
            'label' => 'Checksheet 3S-3T', 'route' => '3s3t_list.php',
            'table' => 't_3s3t_header', 'checker_col' => 'operator_id', 'period' => 'monthly',
            'join' => 'JOIN m_department m ON m.id = h.department_id',
            'title_expr' => "CONCAT('Line ', h.line, ' · ', LPAD(h.month,2,'0'), '/', h.year)", 'order' => 'h.id',
        ],
    ];
}

/** The sign-off type for a section route (e.g. 'assembly_list.php' → 'assy'), or null. */
function signoff_type_for_route(?string $route): ?string
{
    if (!$route) return null;
    foreach (signoff_types() as $type => $cfg) {
        if ($cfg['route'] === $route) return $type;
    }
    return null;
}

/**
 * Department ids the current user may approve for a given section route,
 * from m_user_section (the "Visible on check sheets" assignment in
 * Management → Users). This is how a Painting foreman/supervisor is kept
 * separate from an Assembling one. Empty = not an approver for that section.
 */
function _signoff_user_dept_ids(PDO $pdo, int $uid, string $route): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT s.department_id
         FROM m_user_section us
         JOIN m_checksheet_section s ON s.id = us.section_id
         WHERE us.user_id = ? AND s.route = ? AND s.is_active = 1'
    );
    $stmt->execute([$uid, $route]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * The "which records count right now" fragment, by period:
 *  - daily:   submitted, dated today (resets daily).
 *  - ongoing: submitted, any date (one record per model, no reset).
 *  - monthly: current month/year (grid sheets; status only if the table has it).
 * Pass $alias = '' for a single-table UPDATE (bare column names).
 */
function _signoff_period_where(array $cfg, string $alias = 'h'): string
{
    $p = ($alias === '') ? '' : $alias . '.';
    switch ($cfg['period'] ?? 'daily') {
        case 'ongoing':
            return "{$p}status = 'submitted'";
        case 'monthly':
            $st = !empty($cfg['has_status']) ? "{$p}status = 'submitted' AND " : '';
            return $st . "{$p}month = MONTH(CURDATE()) AND {$p}year = YEAR(CURDATE())";
        default: // daily
            return "{$p}status = 'submitted' AND {$p}tanggal = CURDATE()";
    }
}

/**
 * The WHERE fragment (on alias h) selecting rows pending for the given role.
 * Monthly grids auto-create a header before anyone signs, so there the Checker
 * must have finalized (checker_at set) before it enters the Foreman/Supervisor
 * queue. Daily/ongoing sheets only exist once submitted, so no such gate.
 */
function _signoff_pending_where(?string $role, string $period = 'daily'): ?string
{
    $gate = $period === 'monthly' ? 'h.checker_at IS NOT NULL AND ' : '';
    switch ($role) {
        case 'foreman':    return $gate . 'h.foreman_at IS NULL';
        case 'supervisor': return $gate . 'h.supervisor_at IS NULL';
        case 'admin':      return $gate . '(h.foreman_at IS NULL OR h.supervisor_at IS NULL)';
        default:           return null; // checker / none → no queue
    }
}

/**
 * Records awaiting the current user's sign-off today. Pass $onlyType to scope
 * to one checksheet (per-section queue); null counts across all types.
 */
function signoff_pending_count(PDO $pdo, ?string $role, ?string $onlyType = null): int
{
    if (_signoff_pending_where($role) === null) return 0;
    $uid = (int)(current_user()['id'] ?? 0);
    $admin = is_admin();
    $total = 0;
    foreach (signoff_types() as $type => $cfg) {
        if ($onlyType !== null && $type !== $onlyType) continue;
        $deptCond = '';
        if (!$admin) {
            $depts = _signoff_user_dept_ids($pdo, $uid, $cfg['route']);
            if (!$depts) continue; // not an approver for this section
            $deptCond = ' AND ' . ($cfg['dept_col'] ?? 'h.department_id') . ' IN (' . implode(',', $depts) . ')';
        }
        $cond = _signoff_pending_where($role, $cfg['period'] ?? 'daily');
        $total += (int) $pdo->query(
            "SELECT COUNT(*) FROM {$cfg['table']} h {$cfg['join']} WHERE " . _signoff_period_where($cfg) . " AND $cond$deptCond"
        )->fetchColumn();
    }
    return $total;
}

/** Pending rows, each tagged with type/label/route/title. $onlyType scopes to one checksheet. */
function signoff_pending_rows_all(PDO $pdo, ?string $role, ?string $onlyType = null): array
{
    if (_signoff_pending_where($role) === null) return [];
    $uid = (int)(current_user()['id'] ?? 0);
    $admin = is_admin();
    $all = [];
    foreach (signoff_types() as $type => $cfg) {
        if ($onlyType !== null && $type !== $onlyType) continue;
        $deptCond = '';
        if (!$admin) {
            $depts = _signoff_user_dept_ids($pdo, $uid, $cfg['route']);
            if (!$depts) continue; // not an approver for this section
            $deptCond = ' AND ' . ($cfg['dept_col'] ?? 'h.department_id') . ' IN (' . implode(',', $depts) . ')';
        }
        $cond = _signoff_pending_where($role, $cfg['period'] ?? 'daily');
        $sql = "SELECT h.id AS header_id, h.department_id,
                       {$cfg['title_expr']} AS title,
                       cc.name AS checker_name, h.checker_at,
                       ff.name AS foreman_name, h.foreman_at,
                       ss.name AS supervisor_name, h.supervisor_at
                FROM {$cfg['table']} h
                {$cfg['join']}
                LEFT JOIN m_user cc ON cc.id = h.{$cfg['checker_col']}
                LEFT JOIN m_user ff ON ff.id = h.foreman_id
                LEFT JOIN m_user ss ON ss.id = h.supervisor_id
                WHERE " . _signoff_period_where($cfg) . " AND $cond$deptCond
                ORDER BY {$cfg['order']}";
        foreach ($pdo->query($sql)->fetchAll() as $r) {
            $r['type'] = $type;
            $r['type_label'] = $cfg['label'];
            $r['route'] = $cfg['route'];
            $all[] = $r;
        }
    }
    return $all;
}

/**
 * Stamp the current user's Foreman/Supervisor signature on one record. Only
 * today's submitted record, only if that line isn't signed yet. Returns
 * ['ok' => bool] (ok=false when nothing was stamped, e.g. already signed).
 */
function signoff_stamp(PDO $pdo, string $type, int $headerId, string $role): array
{
    $cfg = signoff_types()[$type] ?? null;
    if (!$cfg) return ['ok' => false, 'error' => 'Jenis checksheet tidak dikenal.'];

    if ($role === 'checker')        { $idCol = $cfg['checker_col']; $atCol = 'checker_at'; }
    elseif ($role === 'foreman')    { $idCol = 'foreman_id';        $atCol = 'foreman_at'; }
    elseif ($role === 'supervisor') { $idCol = 'supervisor_id';     $atCol = 'supervisor_at'; }
    else return ['ok' => false, 'error' => 'Peran Anda tidak bisa menandatangani.'];

    $uid = (int)(current_user()['id'] ?? 0);
    if ($uid <= 0) return ['ok' => false, 'error' => 'Sesi tidak valid.'];

    // Section scope: unless Admin, the signer must be assigned (m_user_section)
    // to this checksheet's section in the record's own department.
    if (!is_admin()) {
        $deptCol = $cfg['dept_col'] ?? 'h.department_id';
        $dStmt = $pdo->prepare("SELECT $deptCol FROM {$cfg['table']} h {$cfg['join']} WHERE h.id = ?");
        $dStmt->execute([$headerId]);
        $dept = (int)$dStmt->fetchColumn();
        if (!in_array($dept, _signoff_user_dept_ids($pdo, $uid, $cfg['route']), true)) {
            return ['ok' => false, 'error' => 'Anda bukan approver untuk section/departemen ini.'];
        }
    }

    // On monthly grids, Foreman/Supervisor can only sign after the Checker has
    // finalized the month (matches the queue's gate).
    $gate = '';
    if (($cfg['period'] ?? 'daily') === 'monthly' && in_array($role, ['foreman', 'supervisor'], true)) {
        $gate = ' AND checker_at IS NOT NULL';
    }

    $stmt = $pdo->prepare(
        "UPDATE {$cfg['table']} SET $idCol = ?, $atCol = NOW()
         WHERE id = ? AND $atCol IS NULL AND " . _signoff_period_where($cfg, '') . $gate
    );
    $stmt->execute([$uid, $headerId]);
    return ['ok' => $stmt->rowCount() > 0];
}

/** The three sign-off steps for a header row, each: label/name/at/signed. */
function signoff_steps(?array $h): array
{
    return [
        ['role' => 'checker',    'label' => 'Checker',    'name' => $h['checker_name'] ?? null,    'at' => $h['checker_at'] ?? null],
        ['role' => 'foreman',    'label' => 'Foreman',    'name' => $h['foreman_name'] ?? null,    'at' => $h['foreman_at'] ?? null],
        ['role' => 'supervisor', 'label' => 'Supervisor', 'name' => $h['supervisor_name'] ?? null, 'at' => $h['supervisor_at'] ?? null],
    ];
}

/** Overall status of a record: label + css class (done|waiting|empty). */
function signoff_overall(?array $h): array
{
    if (!$h) return ['label' => 'Belum diisi', 'cls' => 'empty'];
    if (!empty($h['supervisor_at'])) return ['label' => 'Selesai', 'cls' => 'done'];
    if (!empty($h['foreman_at']))    return ['label' => 'Menunggu Supervisor', 'cls' => 'waiting'];
    if (!empty($h['checker_at']))    return ['label' => 'Menunggu Foreman', 'cls' => 'waiting'];
    return ['label' => 'Belum diisi', 'cls' => 'empty'];
}

/** Format a datetime for display, or '' when null. */
function signoff_time(?string $at): string
{
    if (!$at) return '';
    $t = strtotime($at);
    return $t ? date('d/m/Y H:i', $t) : '';
}

/**
 * Render the shared stepper HTML for a header. $nextRole marks which pending
 * step is "next" (highlight); $youRole marks the viewer's own pending line.
 */
function signoff_render_stepper(?array $h, ?string $nextRole = null, ?string $youRole = null): string
{
    $steps = signoff_steps($h);
    $out = '<div class="signoff-stepper">';
    foreach ($steps as $i => $s) {
        $signed = !empty($s['at']);
        $cls = 'signoff-step';
        $dot = (string)($i + 1);
        if ($signed) { $cls .= ' done'; $dot = '&#10003;'; }
        elseif ($youRole && $s['role'] === $youRole) { $cls .= ' you'; }
        elseif ($nextRole && $s['role'] === $nextRole) { $cls .= ' next'; }

        $out .= '<div class="' . $cls . '">';
        $out .= '<span class="signoff-dot">' . $dot . '</span>';
        $out .= '<div class="signoff-role">' . htmlspecialchars($s['label']) . '</div>';
        if ($signed) {
            $out .= '<div class="signoff-name">' . htmlspecialchars($s['name'] ?? '—') . '</div>';
            $out .= '<div class="signoff-time">' . htmlspecialchars(signoff_time($s['at'])) . '</div>';
        } elseif ($youRole && $s['role'] === $youRole) {
            $out .= '<div class="signoff-hint">Giliran Anda</div>';
        } else {
            $out .= '<div class="signoff-name" style="color:#9aa1ab;">belum</div>';
        }
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}

/** Which role is "next" to act (first unsigned in order), or null if complete. */
function signoff_next_role(?array $h): ?string
{
    if (!$h || empty($h['checker_at'])) return 'checker';
    if (empty($h['foreman_at'])) return 'foreman';
    if (empty($h['supervisor_at'])) return 'supervisor';
    return null;
}

/** Full rows (model + signer names/times) awaiting the current user's sign-off. */
function signoff_pending_rows(PDO $pdo, ?string $role): array
{
    $cond = _signoff_pending_where($role);
    if ($cond === null) return [];
    return $pdo->query(
        "SELECT h.id, h.model_id, h.department_id, h.prod_date_code,
                m.name AS model_name,
                c.name AS checker_name, h.checker_at,
                f.name AS foreman_name, h.foreman_at,
                s.name AS supervisor_name, h.supervisor_at
         FROM t_fopump_check_header h
         JOIN m_fopump_check_model m ON m.id = h.model_id
         LEFT JOIN m_user c ON c.id = h.checker_id
         LEFT JOIN m_user f ON f.id = h.foreman_id
         LEFT JOIN m_user s ON s.id = h.supervisor_id
         WHERE h.status = 'submitted' AND h.tanggal = CURDATE() AND $cond
         ORDER BY m.sort_order, m.id"
    )->fetchAll();
}

<?php
/**
 * Shared helpers for the Edit Request queue. Every checksheet is locked
 * after submit / after its date passes (see includes/calendar_lib.php).
 * There are two ways past that lock: an Admin-approved, time-boxed unlock
 * on one specific record (the User-facing flow) — or, for the Admin
 * identity itself, unconditional access to every record, no request
 * needed. Admin is effectively the app's superadmin/developer account, so
 * has_active_unlock() short-circuits true for it everywhere this is
 * checked (both the save endpoints' lock guards and the entry pages'
 * "load this specific record" logic).
 */
require_once __DIR__ . '/auth.php';

const EDIT_REQUEST_UNLOCK_HOURS = 48;

/** Whether the given checksheet record can currently be edited outside its normal window. */
function has_active_unlock(PDO $pdo, string $checksheetType, int $headerId): bool
{
    if (is_admin()) return true;

    $stmt = $pdo->prepare(
        "SELECT 1 FROM t_edit_request
         WHERE checksheet_type = ? AND header_id = ? AND status = 'approved' AND unlock_expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$checksheetType, $headerId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Whether an Admin-approved, still-active FILL request exists for a missed
 * day — i.e. permission to create a brand-new record for a specific past
 * date that was never submitted. This is the missed-date counterpart of
 * has_active_unlock() (which reopens an existing record). Admin always has it.
 * Pass $conditionId = null for checksheet types without conditions (Torque,
 * FO Pump).
 */
function has_active_fill_unlock(PDO $pdo, string $checksheetType, int $departmentId, ?int $conditionId, string $date): bool
{
    if (is_admin()) return true;

    $sql = "SELECT 1 FROM t_edit_request
            WHERE checksheet_type = ? AND header_id IS NULL AND target_date = ?
              AND department_id = ? AND status = 'approved' AND unlock_expires_at > NOW()";
    $params = [$checksheetType, $date, $departmentId];
    if ($conditionId) {
        $sql .= ' AND condition_id = ?';
        $params[] = $conditionId;
    } else {
        $sql .= ' AND condition_id IS NULL';
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/**
 * All currently-approved (still-unlocked) fill requests for a department, as a
 * lookup set the missing-date banner uses to turn a "Request" button into a
 * direct "Fill" link once Admin has approved. Key: "conditionId|date" (empty
 * conditionId for types without conditions).
 */
function active_fill_unlock_set(PDO $pdo, string $checksheetType, int $departmentId): array
{
    $stmt = $pdo->prepare(
        "SELECT target_date, condition_id FROM t_edit_request
         WHERE checksheet_type = ? AND header_id IS NULL AND department_id = ?
           AND status = 'approved' AND unlock_expires_at > NOW()"
    );
    $stmt->execute([$checksheetType, $departmentId]);
    $set = [];
    foreach ($stmt->fetchAll() as $r) {
        $set[($r['condition_id'] ?? '') . '|' . $r['target_date']] = true;
    }
    return $set;
}

/** Count of requests awaiting Admin action — for the sidebar badge. */
function pending_edit_request_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM t_edit_request WHERE status = 'pending'")->fetchColumn();
}

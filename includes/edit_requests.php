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

/** Count of requests awaiting Admin action — for the sidebar badge. */
function pending_edit_request_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM t_edit_request WHERE status = 'pending'")->fetchColumn();
}

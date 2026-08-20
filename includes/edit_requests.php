<?php
/**
 * Shared helpers for the Edit Request queue. Every checksheet is locked
 * after submit / after its date passes (see includes/calendar_lib.php);
 * this is the one sanctioned bypass — an Admin-approved, time-boxed
 * unlock on one specific record.
 */

const EDIT_REQUEST_UNLOCK_HOURS = 48;

/** Whether the given checksheet record currently has an active, Admin-approved unlock. */
function has_active_unlock(PDO $pdo, string $checksheetType, int $headerId): bool
{
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

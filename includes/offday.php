<?php
/**
 * Helpers for "off-day" excuses: marking a missing checksheet day as excused
 * with an admin-managed reason (Preventive Maintenance, Stock Taking, …) instead
 * of requiring the sheet to be filled. See ajax/save_offday.php + assets/js/offday.js.
 */

/** Active reasons, ordered. */
function offday_reasons(PDO $pdo): array
{
    try {
        return $pdo->query('SELECT id, name FROM m_offday_reason WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
    } catch (Throwable $e) {
        return []; // table not migrated yet → feature inert
    }
}

/**
 * Excused days for a checksheet type + department in a given month, as a lookup
 * set keyed "conditionId|date" (empty conditionId for types without conditions),
 * value = reason name. Mirrors active_fill_unlock_set() so the missing-check
 * banners can both hide excused days and show what they were excused for.
 */
function offday_set(PDO $pdo, string $type, int $departmentId, int $year, int $month): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT o.tanggal, o.condition_id, r.name AS reason
             FROM t_checksheet_offday o
             JOIN m_offday_reason r ON r.id = o.reason_id
             WHERE o.checksheet_type = ? AND o.department_id = ?
               AND YEAR(o.tanggal) = ? AND MONTH(o.tanggal) = ?"
        );
        $stmt->execute([$type, $departmentId, $year, $month]);
    } catch (Throwable $e) {
        return [];
    }
    $set = [];
    foreach ($stmt->fetchAll() as $r) {
        $set[($r['condition_id'] ?? '') . '|' . $r['tanggal']] = $r['reason'];
    }
    return $set;
}

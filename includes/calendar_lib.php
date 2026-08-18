<?php
/**
 * Shared "working day" logic for View Checksheets' missing-check banners.
 * A working day is a weekday, adjusted by the YADIN calendar (m_holiday):
 * a holiday entry marks it non-working, an is_workday=1 entry (a
 * compensating Saturday) marks it working.
 */
function get_working_days(PDO $pdo, string $start, string $end): array
{
    if ($end < $start) return [];

    $stmt = $pdo->prepare('SELECT tanggal, is_workday FROM m_holiday WHERE tanggal BETWEEN ? AND ?');
    $stmt->execute([$start, $end]);
    $holidayMap = [];
    foreach ($stmt->fetchAll() as $h) { $holidayMap[$h['tanggal']] = (bool)$h['is_workday']; }

    $workingDays = [];
    $cursor = new DateTime($start);
    $endDt = new DateTime($end);
    while ($cursor <= $endDt) {
        $d = $cursor->format('Y-m-d');
        $isWorking = array_key_exists($d, $holidayMap) ? $holidayMap[$d] : ((int)$cursor->format('N') < 6);
        if ($isWorking) $workingDays[] = $d;
        $cursor->modify('+1 day');
    }
    return $workingDays;
}

/** Formats a "missing" banner's date list, e.g. "03/08, 04/08 (2 days)". */
function format_missing_dates(array $dates): string
{
    $formatted = implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), $dates));
    return $formatted . ' (' . count($dates) . ' day' . (count($dates) !== 1 ? 's' : '') . ')';
}

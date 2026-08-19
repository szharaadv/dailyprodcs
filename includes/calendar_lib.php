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

/**
 * Rule: no backdating, no future-dating — a checksheet can only ever be
 * saved for "today" (the server's current date), never a past or future
 * date. For the single-date checksheets (Painting, Torque, FO Pump Daily
 * Report) this is enforced simply by ignoring the client's `tanggal` and
 * always writing date('Y-m-d') server-side.
 *
 * Same rule for the monthly-grid auto-save checksheets (Bake Oven, Sub
 * Assembly, Washing, Paint Viscosity): a day cell is only writable while
 * day/month/year together equal today.
 */
function is_today_ymd(int $day, int $month, int $year): bool
{
    return sprintf('%04d-%02d-%02d', $year, $month, $day) === date('Y-m-d');
}

/** Current month/year, for checksheets keyed by period only (no day), e.g. FO Pump Daily Reject. */
function is_current_period(int $month, int $year): bool
{
    return $month === (int) date('n') && $year === (int) date('Y');
}

/** 1-based week-of-month for "today" (day 1-7 = week 1, 8-14 = week 2, ...), for Checksheet 3S-3T. */
function current_week_of_month(): int
{
    return (int) ceil(((int) date('j')) / 7);
}

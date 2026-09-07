<?php
/**
 * Standard OK/NG verdict, mirroring the front-end logic in assets/js/assy.js
 * and assets/js/app.js exactly (lenient parseFloat on the bounds, text-equality
 * fallback). Shared by the assy detail view and the Engine Revision page so the
 * marking on screen always matches how the entry form decides OK/NG.
 */

if (!function_exists('std_num')) {
    /** parseFloat-style leading-number extraction; null when no number. */
    function std_num($v): ?float
    {
        if ($v === null) return null;
        $s = preg_replace('/,/', '.', trim((string)$v), 1);
        if (preg_match('/^[-+]?(\d+\.?\d*|\.\d+)/', $s, $m)) return (float)$m[0];
        return null;
    }
}

if (!function_exists('std_verdict')) {
    /** Returns 'OK' | 'NG' | null (null = no standard, or empty actual). */
    function std_verdict($minRaw, $maxRaw, $actualRaw): ?string
    {
        $actual = trim((string)$actualRaw);
        // Empty or a bare "-" means the item does not apply to this model /
        // nothing to measure — treat as neutral (no verdict), never NG.
        if ($actual === '' || $actual === '-') return null;
        $minRaw = trim((string)$minRaw);
        $maxRaw = trim((string)$maxRaw);
        $hasMin = $minRaw !== '' && $minRaw !== '-';
        $hasMax = $maxRaw !== '' && $maxRaw !== '-';
        if (!$hasMin && !$hasMax) return null;

        $min = std_num($minRaw);
        $max = std_num($maxRaw);
        $val = std_num($actual);
        if ($val !== null && ($min !== null || $max !== null)) {
            if ($min !== null && $val < $min) return 'NG';
            if ($max !== null && $val > $max) return 'NG';
            return 'OK';
        }
        $expected = $hasMin ? $minRaw : $maxRaw;
        return strtolower($actual) === strtolower($expected) ? 'OK' : 'NG';
    }
}

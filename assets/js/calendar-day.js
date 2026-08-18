/**
 * Shared client-side mirror of includes/calendar_lib.php's get_working_days()
 * rule, for checksheets that render a full day-by-day monthly grid (Bake
 * Oven, Sub Assembly, Washing Machine) instead of a single date picker.
 * Those single-date-picker checksheets (Painting, Torque, FO Pump) already
 * block holiday dates via assets/js/holiday-calendar.js disabling the cell
 * in its popup — this covers the grid-style ones where every day is always
 * visible and needs its own inputs disabled instead.
 */
function getDayInfo(day, month, year, holidays) {
    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const h = holidays[dateStr];
    if (h) {
        return h.is_workday
            ? { cls: 'cal-day-workday', title: h.label, blocked: false }
            : { cls: 'cal-day-holiday', title: h.label, blocked: true };
    }
    const dow = new Date(year, month - 1, day).getDay(); // 0=Sun..6=Sat
    if (dow === 0 || dow === 6) return { cls: 'cal-day-weekend', title: 'Weekend', blocked: true };
    return { cls: '', title: '', blocked: false };
}

async function fetchHolidays(year) {
    try {
        const res = await fetch(`ajax/get_holidays.php?year=${year}`);
        return await res.json();
    } catch (e) {
        return {};
    }
}

/**
 * Steps a Month/Year select pair by `delta` months (wrapping the year as
 * needed) and fires 'change' on the month select so the page's existing
 * change listener reloads — used by the Prev/Next month nav buttons.
 */
function stepMonth(monthSelect, yearSelect, delta) {
    let m = parseInt(monthSelect.value, 10) + delta;
    let y = parseInt(yearSelect.value, 10);
    if (m < 1) { m = 12; y -= 1; }
    if (m > 12) { m = 1; y += 1; }

    if (![...yearSelect.options].some((o) => Number(o.value) === y)) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        yearSelect.appendChild(opt);
    }
    yearSelect.value = y;
    monthSelect.value = m;
    monthSelect.dispatchEvent(new Event('change', { bubbles: true }));
}

function wireMonthNav(prevBtnId, nextBtnId, monthSelect, yearSelect) {
    document.getElementById(prevBtnId)?.addEventListener('click', () => stepMonth(monthSelect, yearSelect, -1));
    document.getElementById(nextBtnId)?.addEventListener('click', () => stepMonth(monthSelect, yearSelect, 1));
}

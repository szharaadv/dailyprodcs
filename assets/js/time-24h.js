/**
 * Keep .time-24h text inputs in 24-hour HH:MM format (no AM/PM). Typing is free;
 * on blur the value is normalised: "0630"/"630"/"6:30" → "06:30", and hours/
 * minutes are clamped to a valid time.
 */
document.addEventListener('blur', function (e) {
    const el = e.target;
    if (!el || !el.classList || !el.classList.contains('time-24h')) return;
    let v = (el.value || '').trim();
    if (v === '') return;
    const digits = v.replace(/\D/g, '');
    if (/^\d{3,4}$/.test(digits)) {
        const s = digits.padStart(4, '0');
        v = s.slice(0, 2) + ':' + s.slice(2);
    }
    const m = /^(\d{1,2}):(\d{2})$/.exec(v);
    if (m) {
        const h = Math.min(23, parseInt(m[1], 10));
        const mi = Math.min(59, parseInt(m[2], 10));
        el.value = String(h).padStart(2, '0') + ':' + String(mi).padStart(2, '0');
    }
}, true);

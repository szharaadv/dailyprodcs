(function () {
    function pad(n) { return String(n).padStart(2, '0'); }
    function fmt(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    const ISO_RE = /^\d{4}-\d{2}-\d{2}$/;
    // Friendly display, e.g. "26 Aug 2026".
    function fmtDisplay(iso) {
        const p = iso.split('-').map(Number);
        return p[2] + ' ' + MONTH_NAMES[p[1] - 1].slice(0, 3) + ' ' + p[0];
    }

    class HolidayCalendar {
        constructor(input) {
            this.input = input;
            this.max = input.getAttribute('max') || null;
            this.min = input.getAttribute('min') || null;
            // Plain mode: a bare date picker (no holiday markers, nothing
            // disabled except min/max) — used for filters where any day is
            // selectable, e.g. the status-history date filter.
            this.plain = input.hasAttribute('data-plain');
            // Display mode: show a friendly "26 Aug 2026" in the field while the
            // real ISO value is kept in dataset.iso (and mirrored to a hidden
            // field named by data-alt, which is what the form submits).
            this.displayMode = input.hasAttribute('data-display');
            this.altId = input.getAttribute('data-alt') || null;
            if (this.displayMode && ISO_RE.test(input.value)) {
                input.dataset.iso = input.value;
                input.value = fmtDisplay(input.value);
            }
            this.holidayCache = {};
            this.popup = null;
            input.addEventListener('click', () => this.toggle());
            input.addEventListener('keydown', (e) => e.preventDefault());
        }

        currentIso() {
            if (this.displayMode) return this.input.dataset.iso || null;
            return ISO_RE.test(this.input.value) ? this.input.value : null;
        }

        async loadYear(year) {
            if (this.plain) return {};
            if (this.holidayCache[year]) return this.holidayCache[year];
            try {
                const res = await fetch('ajax/get_holidays.php?year=' + year);
                this.holidayCache[year] = await res.json();
            } catch (e) {
                this.holidayCache[year] = {};
            }
            return this.holidayCache[year];
        }

        toggle() {
            if (this.popup) { this.close(); } else { this.open(); }
        }

        async open() {
            const today = new Date();
            const val = this.currentIso() || fmt(today.getFullYear(), today.getMonth() + 1, today.getDate());
            const [y, m] = val.split('-').map(Number);
            this.viewYear = y;
            this.viewMonth = m;

            this.popup = document.createElement('div');
            this.popup.className = 'hc-popup';
            this.popup.addEventListener('click', (e) => e.stopPropagation());
            document.body.appendChild(this.popup);
            this.positionPopup();
            await this.render();

            this.outsideHandler = (e) => {
                if (this.popup && !this.popup.contains(e.target) && e.target !== this.input) this.close();
            };
            setTimeout(() => document.addEventListener('click', this.outsideHandler), 0);
        }

        positionPopup() {
            const r = this.input.getBoundingClientRect();
            const popupWidth = 260;
            let left = window.scrollX + r.left;
            const maxLeft = window.scrollX + document.documentElement.clientWidth - popupWidth - 8;
            if (left > maxLeft) left = Math.max(window.scrollX + 8, maxLeft);
            this.popup.style.top = (window.scrollY + r.bottom + 4) + 'px';
            this.popup.style.left = left + 'px';
        }

        close() {
            if (this.popup) { this.popup.remove(); this.popup = null; }
            if (this.outsideHandler) document.removeEventListener('click', this.outsideHandler);
        }

        async render() {
            if (!this.popup) return;
            const holidays = await this.loadYear(this.viewYear);
            const firstDow = new Date(this.viewYear, this.viewMonth - 1, 1).getDay();
            const daysInMonth = new Date(this.viewYear, this.viewMonth, 0).getDate();

            let html = '<div class="hc-header">'
                + '<button type="button" class="hc-nav" data-dir="-1">&larr;</button>'
                + '<span>' + MONTH_NAMES[this.viewMonth - 1] + ' ' + this.viewYear + '</span>'
                + '<button type="button" class="hc-nav" data-dir="1">&rarr;</button>'
                + '</div>';
            html += '<div class="hc-grid hc-weekdays">' + ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((d) => '<div>' + d + '</div>').join('') + '</div>';
            html += '<div class="hc-grid hc-days">';
            for (let i = 0; i < firstDow; i++) html += '<div class="hc-day hc-empty"></div>';
            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = fmt(this.viewYear, this.viewMonth, d);
                const h = holidays[dateStr];
                let cls = 'hc-day';
                let title = '';
                let disabled = false;
                if (h) {
                    cls += h.is_workday ? ' hc-workday' : ' hc-holiday';
                    title = h.label;
                    if (!h.is_workday) disabled = true;
                }
                if (this.max && dateStr > this.max) disabled = true;
                if (this.min && dateStr < this.min) disabled = true;
                if (dateStr === this.currentIso()) cls += ' hc-selected';
                if (disabled) cls += ' hc-disabled';
                html += '<button type="button" class="' + cls + '" data-date="' + dateStr + '"'
                    + (title ? ' title="' + title.replace(/"/g, '&quot;') + '"' : '')
                    + (disabled ? ' disabled' : '') + '>' + d + '</button>';
            }
            html += '</div>';
            html += '<div class="hc-footer"><a href="#" class="hc-today">Today</a></div>';
            this.popup.innerHTML = html;

            // stopPropagation everywhere inside the popup: the nav click
            // replaces this.popup's innerHTML (detaching the very button
            // that was clicked), and if that same click event is still
            // bubbling toward document when the outside-click handler
            // checks `popup.contains(e.target)`, a detached target reads as
            // "outside" and closes the popup right back up again — so it
            // looks like the arrow does nothing. Stopping propagation here
            // means the outside-click handler never sees this click at all.
            this.popup.querySelectorAll('.hc-nav').forEach((btn) => btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const dir = parseInt(btn.dataset.dir, 10);
                this.viewMonth += dir;
                if (this.viewMonth < 1) { this.viewMonth = 12; this.viewYear--; }
                if (this.viewMonth > 12) { this.viewMonth = 1; this.viewYear++; }
                this.positionPopup();
                this.render();
            }));
            this.popup.querySelectorAll('.hc-day:not(.hc-empty):not(.hc-disabled)').forEach((btn) => btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const iso = btn.dataset.date;
                if (this.displayMode) {
                    this.input.dataset.iso = iso;
                    this.input.value = fmtDisplay(iso);
                } else {
                    this.input.value = iso;
                }
                if (this.altId) {
                    const alt = document.getElementById(this.altId);
                    if (alt) { alt.value = iso; alt.dispatchEvent(new Event('change', { bubbles: true })); }
                } else {
                    this.input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                this.close();
            }));
            const todayLink = this.popup.querySelector('.hc-today');
            if (todayLink) {
                todayLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const now = new Date();
                    this.viewYear = now.getFullYear();
                    this.viewMonth = now.getMonth() + 1;
                    this.render();
                });
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.holiday-date-input').forEach((el) => new HolidayCalendar(el));
    });
})();

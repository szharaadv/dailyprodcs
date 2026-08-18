/**
 * A searchable text field with a custom-drawn dropdown (light theme, matches
 * the rest of the app) — replaces native <input list="datalist"> whose
 * suggestion popup is OS/browser chrome and can't be restyled.
 *
 * turnIntoCombo(inputEl, options, { onSelect, allowCustom })
 *   options: [{ value, label }]
 *   onSelect(value, option): fired when the input's text exactly matches an
 *     option's label (by click, keyboard Enter, or typing/pasting the full
 *     label) — mirrors a <select>'s change event for callers that need the
 *     underlying id.
 *   allowCustom: if false, blurring on a non-matching value clears the
 *     input (used where a real match is required, e.g. Model pickers tied
 *     to per-checksheet master data). Default true (free-text fields).
 */
function turnIntoCombo(inputEl, options, { onSelect, allowCustom = true } = {}) {
    inputEl.classList.add('combo-input');
    inputEl.setAttribute('autocomplete', 'off');

    const wrap = document.createElement('div');
    wrap.className = 'combo-wrap';
    inputEl.parentNode.insertBefore(wrap, inputEl);
    wrap.appendChild(inputEl);

    const menu = document.createElement('div');
    menu.className = 'combo-menu';
    menu.hidden = true;
    wrap.appendChild(menu);

    let filtered = options;
    let activeIdx = -1;
    let lastMatchedValue = null;

    function byLabel(text) {
        const needle = text.trim().toLowerCase();
        return options.find(o => o.label.toLowerCase() === needle) || null;
    }

    function renderMenu() {
        if (!filtered.length) {
            menu.innerHTML = '<div class="combo-empty">No matches</div>';
        } else {
            menu.innerHTML = filtered.map((o, i) => `
                <div class="combo-option${i === activeIdx ? ' combo-option-active' : ''}" data-idx="${i}">${escapeComboHtml(o.label)}</div>
            `).join('');
        }
    }

    function openMenu(filterText) {
        filtered = filterText
            ? options.filter(o => o.label.toLowerCase().includes(filterText.trim().toLowerCase()))
            : options;
        activeIdx = -1;
        renderMenu();
        menu.hidden = false;
    }

    function closeMenu() {
        menu.hidden = true;
    }

    function pick(option) {
        inputEl.value = option.label;
        lastMatchedValue = option.value;
        // Fire a real 'input' event too, so any other delegated listener a
        // page has on this field (reading e.target.value) stays in sync —
        // setting .value from JS alone does not trigger one. This also runs
        // our own 'input' handler below (which may reopen the menu), so
        // close it again afterwards to make sure it ends up closed.
        inputEl.dispatchEvent(new Event('input', { bubbles: true }));
        closeMenu();
        if (onSelect) onSelect(option.value, option);
    }

    function tryMatchTyped() {
        const hit = byLabel(inputEl.value);
        if (hit && hit.value !== lastMatchedValue) {
            lastMatchedValue = hit.value;
            if (onSelect) onSelect(hit.value, hit);
        } else if (!hit) {
            lastMatchedValue = null;
        }
    }

    inputEl.addEventListener('focus', () => openMenu());
    inputEl.addEventListener('input', () => {
        openMenu(inputEl.value);
        tryMatchTyped();
    });

    inputEl.addEventListener('keydown', (e) => {
        if (menu.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { openMenu(inputEl.value); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, filtered.length - 1); renderMenu(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); renderMenu(); }
        else if (e.key === 'Enter') { if (!menu.hidden && activeIdx >= 0) { e.preventDefault(); pick(filtered[activeIdx]); } }
        else if (e.key === 'Escape') { closeMenu(); }
    });

    menu.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('.combo-option');
        if (!opt) return;
        e.preventDefault();
        pick(filtered[parseInt(opt.dataset.idx, 10)]);
    });

    inputEl.addEventListener('blur', () => {
        setTimeout(() => {
            closeMenu();
            if (!allowCustom) {
                const hit = byLabel(inputEl.value);
                if (!hit) { inputEl.value = ''; lastMatchedValue = null; }
            }
        }, 150);
    });

    // Resolve whatever the field starts with (e.g. a prefilled draft value).
    const initialHit = byLabel(inputEl.value);
    lastMatchedValue = initialHit ? initialHit.value : null;

    return { getValue: () => lastMatchedValue };
}

function escapeComboHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
}

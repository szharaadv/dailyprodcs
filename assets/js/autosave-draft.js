/**
 * Silent autosave-to-draft for the daily fill sheets (Painting, Torque, FO Pump
 * report/test/check/reject). Debounced ~1.5s after the user's last edit, plus a
 * best-effort save when they leave the page, so a filled-but-unsubmitted sheet
 * isn't lost. It only starts once the user has actually touched the form (a
 * trusted input/change), so an untouched page never creates a blank draft, and
 * it's skipped entirely when editing an already-submitted record
 * (AUTOSAVE_ENABLED = false, set in includes/app_bottom.php).
 *
 * A sheet wires it after its own setup:
 *   initAutosaveDraft({ save: () => saveChecksheet('draft', true) });
 * where the passed `save` performs a SILENT draft save (no alert/redirect) and
 * throws on failure so autosave can retry.
 */
function initAutosaveDraft(opts) {
    opts = opts || {};
    const save = opts.save;
    const enabled = opts.enabled !== undefined ? opts.enabled
        : (typeof AUTOSAVE_ENABLED === 'undefined' ? true : AUTOSAVE_ENABLED);
    if (!enabled || typeof save !== 'function') return;

    const root = opts.root || document;
    const DELAY = opts.delay || 1500;
    let touched = false, dirty = false, saving = false, timer = null;

    const status = document.createElement('span');
    status.className = 'autosave-status';
    status.setAttribute('aria-live', 'polite');
    const actions = document.querySelector('.actions') || document.querySelector('.fopump-check-toolbar');
    if (actions) actions.appendChild(status);

    function stamp(t) { status.textContent = t; }
    function hhmm(d) {
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    async function run() {
        if (saving || !dirty || !touched) return;
        saving = true; dirty = false;
        let ok = false;
        try { ok = await save(); } catch (e) { ok = false; }
        saving = false;
        // Only announce a real save. When the draft can't be saved yet (the
        // sheet isn't filled enough, or the record is already submitted) the
        // save returns falsy — stay quiet and wait for the next edit to retry,
        // rather than spamming requests or showing a scary error.
        if (ok) stamp('Auto-saved ' + hhmm(new Date()));
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(run, DELAY); }

    function onEdit(e) {
        if (!e.isTrusted) return; // ignore programmatic value changes (page setup)
        const t = e.target;
        if (t && (t.id === 'btn-draft' || t.id === 'btn-submit')) return;
        touched = true; dirty = true; schedule();
    }
    root.addEventListener('input', onEdit, true);
    root.addEventListener('change', onEdit, true);

    // Best-effort final save when the tab is hidden / the page is being left.
    // The sheet's fetch uses keepalive so this can still complete during unload.
    function flush() {
        if (touched && dirty && !saving) { clearTimeout(timer); try { save(); } catch (e) {} }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flush();
    });
    window.addEventListener('pagehide', flush);
}
window.initAutosaveDraft = initAutosaveDraft;

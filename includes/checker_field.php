<?php
require_once __DIR__ . '/auth.php';

/**
 * Renders the "Checker" field locked to the logged-in user.
 *
 * The checker is always whoever is signed in (ready for One YADIN SSO), so this
 * replaces the old operator dropdown. It still emits `<select id="f_checker">`
 * with the user as its single, selected option, so every form's JS keeps
 * working unchanged — `document.getElementById('f_checker').value` returns the
 * logged-in user's id. The select is `disabled` purely to show it is fixed;
 * a disabled select is still readable from JS (it just can't be changed or
 * submitted via a form post, which these AJAX forms don't rely on).
 */
function render_checker_field(string $label = 'Checker'): void
{
    $u    = current_user();
    $id   = $u && isset($u['id']) ? (int) $u['id'] : '';
    $name = $u['name'] ?? '';
    ?>
    <div class="field-block">
        <label><?= htmlspecialchars($label) ?></label>
        <select id="f_checker" class="locked-select" disabled title="Diisi otomatis dari akun yang login">
            <option value="<?= $id ?>" selected><?= htmlspecialchars($name) ?></option>
        </select>
    </div>
    <?php
}

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/import_lib.php';
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = (int)($department['id'] ?? 0);

$error = null; $summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk') {
    $pickModels  = array_map('intval', $_POST['models'] ?? []);
    $rawNew      = $_POST['new_models'] ?? '';
    $newModels   = is_array($rawNew) ? $rawNew : preg_split('/[\r\n,]+/', trim((string)$rawNew));
    $chkItems    = $_POST['item_checking'] ?? [];
    $chkStandard = $_POST['item_standard'] ?? [];
    $chkMin      = $_POST['item_min'] ?? [];
    $chkMax      = $_POST['item_max'] ?? [];

    // Only rows that actually have a standard filled in are applied. This lets
    // the user fill just one (or a few) of the 36 pre-listed items and apply
    // only those — the untouched rows (name only, no standard) are skipped.
    $items = [];
    foreach ($chkItems as $i => $name) {
        $name = trim((string)$name);
        if ($name === '') continue;
        $std = trim((string)($chkStandard[$i] ?? ''));
        $min = trim((string)($chkMin[$i] ?? ''));
        $max = trim((string)($chkMax[$i] ?? ''));
        if ($std === '' && $min === '' && $max === '') continue; // name only, no standard
        $items[] = ['checking_item' => $name, 'standard' => $std, 'min' => $min, 'max' => $max];
    }

    if (!$items) {
        $error = 'Isi standar (Std Min / Std Max) untuk minimal satu checking item yang mau diterapkan.';
    } elseif (!$pickModels && !array_filter(array_map('trim', $newModels))) {
        $error = 'Pilih minimal satu model, atau ketik nama model baru.';
    } else {
        $pdo->beginTransaction();

        // Existing models in this department, keyed by lowercase name (for new-name matching).
        $existing = $pdo->prepare('SELECT id, name FROM m_assy_model WHERE department_id = ?');
        $existing->execute([$department_id]);
        $byName = [];
        foreach ($existing as $m) $byName[mb_strtolower($m['name'])] = (int)$m['id'];

        $targetIds = $pickModels;
        $createdModels = [];
        $nextSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM m_assy_model WHERE department_id = ' . $department_id)->fetchColumn();
        $insModel = $pdo->prepare('INSERT INTO m_assy_model (department_id, name, sort_order, configured) VALUES (?,?,?,1)');
        foreach ($newModels as $nm) {
            $nm = trim($nm);
            if ($nm === '') continue;
            $key = mb_strtolower($nm);
            if (isset($byName[$key])) { $targetIds[] = $byName[$key]; continue; } // already exists
            $nextSort++;
            $insModel->execute([$department_id, $nm, $nextSort]);
            $id = (int)$pdo->lastInsertId();
            $byName[$key] = $id;
            $targetIds[] = $id;
            $createdModels[] = $nm;
        }
        $targetIds = array_values(array_unique(array_filter($targetIds)));

        // Apply each item to each target model (upsert by model + checking_item name).
        $findItem = $pdo->prepare('SELECT id FROM m_assy_checklist_item WHERE model_id = ? AND LOWER(checking_item) = LOWER(?) LIMIT 1');
        $updItem  = $pdo->prepare('UPDATE m_assy_checklist_item SET standard=?, standard_min=?, standard_max=?, is_active=1 WHERE id=?');
        $insItem  = $pdo->prepare('INSERT INTO m_assy_checklist_item (model_id, checking_item, standard, standard_min, standard_max, sort_order) VALUES (?,?,?,?,?,?)');
        $setCfg   = $pdo->prepare('UPDATE m_assy_model SET configured = 1 WHERE id = ?');

        $inserted = 0; $updated = 0;
        foreach ($targetIds as $mid) {
            $nextItemSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM m_assy_checklist_item WHERE model_id = ' . (int)$mid)->fetchColumn();
            foreach ($items as $it) {
                $findItem->execute([$mid, $it['checking_item']]);
                $existId = $findItem->fetchColumn();
                if ($existId) {
                    $updItem->execute([import_nz($it['standard']), import_nz($it['min']), import_nz($it['max']), (int)$existId]);
                    $updated++;
                } else {
                    $nextItemSort++;
                    $insItem->execute([$mid, $it['checking_item'], import_nz($it['standard']), import_nz($it['min']), import_nz($it['max']), $nextItemSort]);
                    $inserted++;
                }
            }
            $setCfg->execute([$mid]);
        }
        $pdo->commit();

        $parts = [count($targetIds) . ' model', count($items) . ' checking item',
                  $inserted . ' baris ditambah', $updated . ' baris diupdate'];
        if ($createdModels) $parts[] = 'model baru dibuat: ' . implode(', ', $createdModels);
        // Post/Redirect/Get. After applying, reset ONLY the model selection
        // (checkboxes + new-model chips) — but KEEP the checking-item standards
        // the user typed, so they can apply the same standards to another set of
        // models. The typed item rows ride across the redirect in a flash.
        $_SESSION['bulk_flash'] = implode(' · ', $parts);
        $_SESSION['bulk_items'] = [
            'checking' => $chkItems, 'standard' => $chkStandard, 'min' => $chkMin, 'max' => $chkMax,
        ];
        header('Location: assy_checklist_bulk.php');
        exit;
    }
}

if (!empty($_SESSION['bulk_flash'])) {
    $summary = $_SESSION['bulk_flash'];
    unset($_SESSION['bulk_flash']);
}

$models = $pdo->prepare('SELECT id, name, configured FROM m_assy_model WHERE department_id = ? ORDER BY sort_order, id');
$models->execute([$department_id]);
$models = $models->fetchAll();

// Master Engine names, for autocomplete on the "add new model" field.
$engineModels = $pdo->query('SELECT model FROM m_engine WHERE is_active = 1 ORDER BY sort_order, model')->fetchAll(PDO::FETCH_COLUMN);

// Standard 36-item Torque checklist, pre-filled so the user only enters the
// Std Min/Max and picks the models.
$defaultItems = [
    'Balancer bearing retainer bolt', 'Radiator bolt', 'Switch thermo', 'Balance weight bolt',
    'Bearing retainer bolt', 'Main bearing housing bolt', 'Cam gear tightening nut',
    'Fan case tightening bolt', 'Fan pulley tightening nut',
    'Side gap crank shaft', 'Conecting rod bolt', 'Idle shaft bolt', 'Balancer driving gear bolt',
    'Rear cover bolt', 'Fly wheel end nut', 'Tension belt check', 'Cylinder head nut', 'Gear case bolt',
    'FO Limiter tightening nut', 'FO Limiter tightening cup bolth', 'Delivery valve  holder',
    'Fuel injection  pump tightening nut', 'Rocker arm support tightening nut', 'Valve clearence (suc/exh )',
    'Top clearence',
    'Fuel injection valve (IDI)', 'Joint overflow tightening nut', 'FO nozzle retainer nut (DI)',
    'Glow plug (TF-V)', 'Bonet cyl. Head', 'Lubrication oil drain plug', 'Oil signal/oil pressure switch',
    'Terminal nut glow plug (M4) TF-V', 'Bolt starter motor', 'Key switch FW side', 'Cover radiator',
];

// "Salin dari model": prefill the form with an existing model's items + standards
// so the user can duplicate a similar model and just tweak the numbers.
$copyFrom = (int)($_GET['copy_from'] ?? 0);
$copyFromName = '';

// Rows to render, in priority: typed values on validation error (POST) > a model
// picked to copy from (copy_from) > the just-applied values (flash) > the default
// list. Model selection is never carried over, so it always resets.
$renderRows = [];
$rowSrc = null;
if (!empty($_POST['item_checking']) && is_array($_POST['item_checking'])) {
    $rowSrc = ['checking' => $_POST['item_checking'], 'standard' => $_POST['item_standard'] ?? [],
               'min' => $_POST['item_min'] ?? [], 'max' => $_POST['item_max'] ?? []];
} elseif ($copyFrom) {
    $cs = $pdo->prepare('SELECT checking_item, standard, standard_min, standard_max
                         FROM m_assy_checklist_item WHERE model_id = ? AND is_active = 1 ORDER BY sort_order, id');
    $cs->execute([$copyFrom]);
    foreach ($cs as $r) {
        $renderRows[] = ['checking' => (string)$r['checking_item'], 'standard' => (string)($r['standard'] ?? ''),
                         'min' => (string)($r['standard_min'] ?? ''), 'max' => (string)($r['standard_max'] ?? '')];
    }
    foreach ($models as $m) { if ((int)$m['id'] === $copyFrom) { $copyFromName = $m['name']; break; } }
    unset($_SESSION['bulk_items']); // a deliberate copy overrides any pending flash
} elseif (!empty($_SESSION['bulk_items'])) {
    $rowSrc = $_SESSION['bulk_items'];
    unset($_SESSION['bulk_items']);
}
if ($rowSrc) {
    foreach ($rowSrc['checking'] as $i => $nm) {
        $renderRows[] = [
            'checking' => (string)$nm,
            'standard' => (string)($rowSrc['standard'][$i] ?? ''),
            'min'      => (string)($rowSrc['min'][$i] ?? ''),
            'max'      => (string)($rowSrc['max'][$i] ?? ''),
        ];
    }
}
if (!$renderRows) { // nothing from POST / copy / flash → the standard list
    foreach ($defaultItems as $n) $renderRows[] = ['checking' => $n, 'standard' => '', 'min' => '', 'max' => ''];
}

$base_url = '../';
$active_nav = 'config-assy-checklist-bulk';
$section_route = 'assembly_list.php';
$page_title = 'Bulk Checking Item';
$page_subtitle = 'Master Data · Terapkan satu checking item + standar ke banyak model sekaligus';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($summary): ?><div class="alert alert-ok">Berhasil: <?= htmlspecialchars($summary) ?></div><?php endif; ?>

<form method="post" class="admin-form" id="bulk-form">
    <input type="hidden" name="action" value="bulk">

    <h3 class="bulk-h">1. Checking Item &amp; Standar</h3>
    <div class="copy-from">
        <label>Salin item + standar dari model:</label>
        <select id="copy-from" onchange="location.href='assy_checklist_bulk.php'+(this.value?('?copy_from='+this.value):'');">
            <option value="">— mulai dari daftar standar (kosong) —</option>
            <?php foreach ($models as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $copyFrom === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($copyFrom && $copyFromName !== ''): ?>
            <span class="copy-note">Disalin dari <b><?= htmlspecialchars($copyFromName) ?></b> — edit angkanya &amp; hapus baris yang tak dipakai, lalu pilih model tujuan.</span>
        <?php endif; ?>
    </div>
    <div class="bulk-note">Isi standar hanya untuk item yang mau diterapkan sekarang — bisa satu item saja. Baris yang standarnya kosong akan dilewati (tidak diterapkan).</div>
    <div id="fill-summary" class="fill-summary"></div>
    <div class="bulk-items-wrap">
    <table class="admin-table" id="bulk-items">
        <colgroup>
            <col style="width:44px;"><col style="width:420px;"><col style="width:180px;"><col style="width:150px;"><col style="width:150px;"><col style="width:36px;">
        </colgroup>
        <thead><tr><th>No</th><th>Checking Item</th><th>Standard (teks)</th><th>Std Min</th><th>Std Max</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($renderRows as $r): ?>
            <tr>
                <td class="rownum c"></td>
                <td><input type="text" name="item_checking[]" autocomplete="off" value="<?= htmlspecialchars($r['checking']) ?>"></td>
                <td><input type="text" name="item_standard[]" autocomplete="off" placeholder="opsional (mis. M8X25 = 2.6±0.3 Kg.m)" value="<?= htmlspecialchars($r['standard']) ?>"></td>
                <td><input type="text" name="item_min[]" autocomplete="off" value="<?= htmlspecialchars($r['min']) ?>"></td>
                <td><input type="text" name="item_max[]" autocomplete="off" value="<?= htmlspecialchars($r['max']) ?>"></td>
                <td class="c"><button type="button" class="row-del" title="Hapus baris">&times;</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="add-row">+ Tambah baris item</button>

    <h3 class="bulk-h">2. Terapkan ke Model</h3>
    <div class="bulk-models">
        <div id="exist-chips" class="chip-box"></div>
        <div class="ms-wrap">
            <input type="text" id="exist-search" autocomplete="off" placeholder="cari model, centang untuk memilih (bisa banyak)">
            <div id="exist-panel" class="ms-panel" hidden></div>
        </div>
        <label class="pick-all-lite"><input type="checkbox" id="pick-all"> Pilih semua model (<?= count($models) ?>)</label>
        <div id="exist-hidden"></div>
    </div>

    <div class="form-row" style="margin-top:14px;">
        <label>Atau tambah model baru (centang beberapa dari Master Engine, atau ketik nama baru)</label>
        <div id="newmodel-chips" class="chip-box"></div>
        <div class="ms-wrap">
            <input type="text" id="ms-search" autocomplete="off" placeholder="ketik untuk cari / tambah, centang untuk memilih">
            <div id="ms-panel" class="ms-panel" hidden></div>
        </div>
        <div class="chip-hint">Centang model di daftar (boleh banyak sekaligus &mdash; daftar tetap terbuka). Untuk nama di luar Master Engine, ketik lalu tekan <b>Enter</b>.</div>
        <div id="newmodel-hidden"></div>
    </div>

    <div class="form-row" style="margin-top:16px;">
        <button type="submit" class="btn">Terapkan ke Model Terpilih</button>
    </div>
</form>

<style>
    .bulk-h { margin: 18px 0 8px; font-size: 15px; }
    /* Bounded, self-scrolling box so the list doesn't run the whole page down.
       Not named .table-scroll on purpose — the doc-page rule would force
       max-height:none/overflow-y:visible and break this. The sticky thead
       then sticks to THIS box's top. */
    .bulk-items-wrap { max-height: 55vh; overflow: auto; border: 1px solid #e7e7ea; border-radius: 8px; }
    #bulk-items { table-layout: fixed; min-width: 100%; }
    /* position: sticky already establishes a containing block, so the absolute
       resizer handle anchors to the th. */
    #bulk-items thead th { position: sticky; top: 0; z-index: 3; background: #f2f2f4; box-shadow: inset 0 -1px 0 #d6dae0; }
    #bulk-items thead th { overflow: visible; }
    .col-resizer { position: absolute; top: 0; right: 0; width: 7px; height: 100%; cursor: col-resize; user-select: none; }
    .col-resizer:hover { background: #c7d0fd; }
    #bulk-items td, #bulk-items th { overflow: hidden; }
    #bulk-items input { max-width: 100%; }
    #bulk-items input { width: 100%; padding: 6px 8px; border: 1px solid #d0d5dd; border-radius: 6px; }
    #bulk-items td.c { text-align: center; }
    .row-del { border: none; background: none; color: #b3261e; font-size: 18px; cursor: pointer; line-height: 1; }
    .bulk-models { border: 1px solid #e7e7ea; border-radius: 10px; padding: 12px 14px; background: #fff; }
    .pick-all-lite { display: inline-flex; align-items: center; gap: 7px; margin-top: 10px; font-size: 13px; color: #475467; cursor: pointer; }
    .pick-all-lite input { width: 15px; height: 15px; }
    .mini-new { background: #fff4e5; color: #b25e09; border: 1px solid #f4c988; font-size: 10px; padding: 0 6px; border-radius: 10px; }
    .ms-wrap { position: relative; max-width: 420px; }
    #ms-search { width: 100%; padding: 8px; border: 1px solid #d0d5dd; border-radius: 7px; }
    .ms-panel { position: absolute; z-index: 20; left: 0; right: 0; margin-top: 4px; background: #fff;
        border: 1px solid #d6dae0; border-radius: 8px; box-shadow: 0 8px 24px rgba(16,24,40,.14);
        max-height: 260px; overflow-y: auto; padding: 4px; }
    .ms-opt { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 6px; font-size: 13.5px; cursor: pointer; }
    .ms-opt:hover { background: #f2f4f7; }
    .ms-opt input { width: 15px; height: 15px; pointer-events: none; }
    .ms-add { padding: 7px 10px; color: #3538cd; font-size: 13px; cursor: pointer; border-radius: 6px; }
    .ms-add:hover { background: #eef2ff; }
    .ms-empty { padding: 10px; color: #98a2b3; font-size: 13px; }
    .chip-box { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .chip { display: inline-flex; align-items: center; gap: 6px; background: #f2f4f7; border: 1px solid #d6dae0;
        border-radius: 16px; padding: 3px 10px; font-size: 13px; }
    .chip b { font-weight: 600; }
    .chip .x { cursor: pointer; color: #b3261e; font-weight: 700; }
    .chip-hint { color: #667085; font-size: 12px; margin-top: 5px; }
    .fill-summary { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: center; margin: 0 0 8px;
        font-size: 12.5px; color: #475467; }
    .fill-summary .pill { background: #f2f4f7; border: 1px solid #e3e6ea; border-radius: 20px; padding: 2px 10px; }
    .fill-summary .pill b { color: #101828; }
    .fill-summary .pill.done { background: #e8f7ee; border-color: #bfe6cd; }
    .fill-summary .pill.done b { color: #1e7d34; }
    .fill-summary .pill.ready { background: #eef2ff; border-color: #c7d0fd; }
    .fill-summary .pill.ready b { color: #3538cd; }
    .bulk-note { font-size: 12.5px; color: #667085; margin: 0 0 8px; }
    .copy-from { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0 0 10px; }
    .copy-from label { font-size: 13px; font-weight: 600; color: #344054; }
    .copy-from select { padding: 6px 8px; border: 1px solid #d0d5dd; border-radius: 7px; }
    .copy-from .copy-note { font-size: 12.5px; color: #1e7d34; }
</style>
<script>
window.ENGINE_MODELS = <?= json_encode($engineModels) ?>;
window.EXISTING_MODELS = <?= json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['name']], $models)) ?>;
</script>
<script>
function updateFillSummary() {
    const tb = document.querySelector('#bulk-items tbody');
    const total = tb.querySelectorAll('tr').length;
    const cnt = (name) => [...tb.querySelectorAll('input[name="' + name + '"]')].filter(i => i.value.trim() !== '').length;
    // "Ready" = rows that have a standard (min/max/standard) filled — those are
    // the ones that will actually be applied.
    let ready = 0;
    tb.querySelectorAll('tr').forEach(tr => {
        const g = n => (tr.querySelector('input[name="' + n + '"]')?.value || '').trim() !== '';
        if ((tr.querySelector('input[name="item_checking[]"]')?.value || '').trim() !== '' &&
            (g('item_min[]') || g('item_max[]') || g('item_standard[]'))) ready++;
    });
    const cols = [
        ['Std Min', cnt('item_min[]')],
        ['Std Max', cnt('item_max[]')],
        ['Standard teks', cnt('item_standard[]')],
    ];
    const box = document.getElementById('fill-summary');
    box.innerHTML =
        `<span class="pill ready">Siap diterapkan: <b>${ready}/${total}</b> item</span>`
        + cols.map(([label, n]) =>
            `<span class="pill ${n === total && total > 0 ? 'done' : ''}">${label}: <b>${n}/${total}</b></span>`
        ).join('');
}

// Fixed row numbers: assigned once, and DON'T shift when a row is deleted
// (deleting No.3 leaves 1,2,4,5…). New rows get the next unused number.
let nextRowNum = 1;
function initRowNumbers() {
    document.querySelectorAll('#bulk-items tbody tr').forEach((tr) => {
        const cell = tr.querySelector('.rownum');
        if (cell) cell.textContent = nextRowNum++;
    });
}
document.getElementById('add-row').addEventListener('click', function () {
    const tb = document.querySelector('#bulk-items tbody');
    const tr = tb.rows[0].cloneNode(true);
    tr.querySelectorAll('input').forEach(i => i.value = '');
    const cell = tr.querySelector('.rownum');
    if (cell) cell.textContent = nextRowNum++;
    tb.appendChild(tr);
    updateFillSummary();
});
document.querySelector('#bulk-items tbody').addEventListener('click', function (e) {
    if (e.target.classList.contains('row-del')) {
        const rows = this.querySelectorAll('tr');
        if (rows.length > 1) e.target.closest('tr').remove();               // number NOT reassigned
        else e.target.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
        updateFillSummary();
    }
});
document.querySelector('#bulk-items tbody').addEventListener('input', updateFillSummary);
updateFillSummary(); initRowNumbers();

// Draggable column widths (persisted per browser).
(function () {
    const table = document.getElementById('bulk-items');
    const cols = table.querySelectorAll('colgroup col');
    const ths = table.querySelectorAll('thead th');
    const KEY = 'bulkColWidths2';
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (Array.isArray(saved)) saved.forEach((w, i) => { if (w && cols[i]) cols[i].style.width = w + 'px'; });
    } catch (e) {}
    function save() {
        try { localStorage.setItem(KEY, JSON.stringify([...cols].map((c, i) => parseInt(c.style.width) || ths[i].offsetWidth))); } catch (e) {}
    }
    ths.forEach((th, i) => {
        if (i === 0) return; // no handle on the "No" column
        if (i >= cols.length - 1) return; // no handle on the last (×) column
        const r = document.createElement('div');
        r.className = 'col-resizer';
        th.appendChild(r);
        r.addEventListener('mousedown', function (e) {
            e.preventDefault(); e.stopPropagation();
            const startX = e.clientX, startW = ths[i].offsetWidth;
            function move(ev) { cols[i].style.width = Math.max(60, startW + (ev.clientX - startX)) + 'px'; }
            function up() {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                document.body.style.cursor = ''; save();
            }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
            document.body.style.cursor = 'col-resize';
        });
    });
})();
// Reusable multi-select: search box + checkbox dropdown that STAYS OPEN while
// ticking, with picked items shown as chips above. Used for both the existing
// models (sends ids) and new models (sends names, allows custom).
function multiSelect(cfg) {
    const esc = s => String(s).replace(/[&<>"']/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
    const byVal = new Map(cfg.items.map(it => [String(it.value).toLowerCase(), it]));
    const selected = new Map(); // key -> {value, label}
    const wrap = cfg.panel.closest('.ms-wrap');

    function syncHidden() {
        cfg.hidden.innerHTML = '';
        for (const it of selected.values()) {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = cfg.hiddenName; h.value = it.value;
            cfg.hidden.appendChild(h);
        }
    }
    function renderChips() {
        cfg.chips.innerHTML = '';
        for (const [key, it] of selected) {
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.innerHTML = '<b></b> <span class="x">&times;</span>';
            chip.querySelector('b').textContent = it.label;
            chip.querySelector('.x').addEventListener('click', () => { selected.delete(key); renderChips(); syncHidden(); renderPanel(); });
            cfg.chips.appendChild(chip);
        }
    }
    function toggleItem(it) {
        const key = String(it.value).toLowerCase();
        if (selected.has(key)) selected.delete(key); else selected.set(key, it);
        renderChips(); syncHidden(); renderPanel();
    }
    function toggleByLabel(label) {
        const hit = cfg.items.find(o => o.label.toLowerCase() === label.toLowerCase());
        if (hit) toggleItem(hit);
        else if (cfg.allowCustom) toggleItem({ value: label, label: label });
    }
    function renderPanel() {
        const q = cfg.search.value.trim().toLowerCase();
        const matches = cfg.items.filter(o => !q || o.label.toLowerCase().includes(q));
        let html = '';
        if (cfg.allowCustom && q && !cfg.items.some(o => o.label.toLowerCase() === q)) {
            html += `<div class="ms-add" data-add="${esc(cfg.search.value.trim())}">+ Tambah "<b>${esc(cfg.search.value.trim())}</b>"</div>`;
        }
        html += matches.map(o =>
            `<label class="ms-opt"><input type="checkbox" ${selected.has(String(o.value).toLowerCase()) ? 'checked' : ''} data-val="${esc(o.value)}"> ${esc(o.label)}</label>`
        ).join('') || '<div class="ms-empty">Tidak ada hasil.</div>';
        cfg.panel.innerHTML = html;
    }
    function open() { renderPanel(); cfg.panel.hidden = false; }
    function close() { cfg.panel.hidden = true; }

    cfg.search.addEventListener('focus', open);
    cfg.search.addEventListener('input', open);
    cfg.search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); const v = cfg.search.value.trim(); if (v) { toggleByLabel(v); cfg.search.value = ''; open(); } }
        else if (e.key === 'Escape') { close(); }
    });
    cfg.panel.addEventListener('mousedown', function (e) {
        e.preventDefault(); e.stopPropagation();
        const add = e.target.closest('.ms-add');
        const opt = e.target.closest('.ms-opt');
        if (add) { toggleByLabel(add.dataset.add); cfg.search.value = ''; }
        else if (opt) {
            const val = opt.querySelector('input').dataset.val;
            toggleItem(byVal.get(String(val).toLowerCase()) || { value: val, label: val });
        }
        cfg.search.focus();
    });
    document.addEventListener('mousedown', function (e) { if (!wrap.contains(e.target)) close(); });

    return {
        selectAll() { cfg.items.forEach(it => selected.set(String(it.value).toLowerCase(), it)); renderChips(); syncHidden(); renderPanel(); },
        clearAll()  { selected.clear(); renderChips(); syncHidden(); renderPanel(); },
    };
}

// Existing models -> models[] (ids)
const existMS = multiSelect({
    search: document.getElementById('exist-search'),
    panel: document.getElementById('exist-panel'),
    chips: document.getElementById('exist-chips'),
    hidden: document.getElementById('exist-hidden'),
    items: (window.EXISTING_MODELS || []).map(m => ({ value: m.id, label: m.name })),
    hiddenName: 'models[]',
    allowCustom: false,
});
const pickAll = document.getElementById('pick-all');
if (pickAll) pickAll.addEventListener('change', function () { this.checked ? existMS.selectAll() : existMS.clearAll(); });

// New models -> new_models[] (names, from Master Engine + custom)
multiSelect({
    search: document.getElementById('ms-search'),
    panel: document.getElementById('ms-panel'),
    chips: document.getElementById('newmodel-chips'),
    hidden: document.getElementById('newmodel-hidden'),
    items: (window.ENGINE_MODELS || []).map(m => ({ value: m, label: m })),
    hiddenName: 'new_models[]',
    allowCustom: true,
});
</script>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/importers.php';
$pdo = get_db();

$registry = import_registry();

$stmt = $pdo->query(
    "SELECT s.id, s.name, s.route, s.department_id, d.name AS dept_name
     FROM m_checksheet_section s
     JOIN m_department d ON d.id = s.department_id
     WHERE s.is_active = 1
     ORDER BY d.sort_order, s.sort_order"
);
$allSections = array_filter($stmt->fetchAll(), fn($s) => isset($registry[$s['route']]));

if (!empty($_SESSION['department_id'])) {
    $scopedSections = array_values(array_filter($allSections, fn($s) => $s['department_id'] == $_SESSION['department_id']));
    if ($scopedSections) {
        $allSections = $scopedSections;
    }
}

$selected_section_id = (int)($_GET['section_id'] ?? ($_POST['section_id'] ?? 0));
if (!$selected_section_id && !empty($_SESSION['section_route'])) {
    foreach ($allSections as $s) {
        if ($s['route'] === $_SESSION['section_route']
            && (empty($_SESSION['department_id']) || $s['department_id'] == $_SESSION['department_id'])) {
            $selected_section_id = $s['id'];
            break;
        }
    }
}
$section = null;
foreach ($allSections as $s) {
    if ($s['id'] == $selected_section_id) { $section = $s; break; }
}
if (!$section && $allSections) {
    $section = reset($allSections);
    $selected_section_id = $section['id'];
}

// ---- Template download (headers only) ----
if (($_GET['action'] ?? '') === 'template' && $section) {
    $cfg = $registry[$section['route']];
    $filename = 'template_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($section['name'])) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, import_fillable_cols($cfg));
    fclose($out);
    exit;
}

// ---- Export existing data (same columns as the template) ----
if (($_GET['action'] ?? '') === 'export' && $section) {
    $cfg = $registry[$section['route']];
    $cols = $cfg['template'];
    $rows = isset($cfg['export']) && function_exists($cfg['export'])
        ? ($cfg['export'])($pdo, (int)$section['department_id'])
        : [];
    $filename = 'export_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($section['name'])) . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols);
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($c) => $r[$c] ?? '', $cols));
    }
    fclose($out);
    exit;
}

$result = null;
$error = null;

// ---- Import upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import' && $section) {
    $cfg = $registry[$section['route']];
    try {
        $names = $_FILES['csv']['name'] ?? [];
        $tmpPaths = $_FILES['csv']['tmp_name'] ?? [];
        $errors = $_FILES['csv']['error'] ?? [];
        if (!is_array($names)) { $names = [$names]; $tmpPaths = [$tmpPaths]; $errors = [$errors]; }
        $names = array_values(array_filter($names, fn($n) => $n !== ''));
        if (!$names) {
            throw new RuntimeException('Please choose at least one file to upload.');
        }

        $allRows = [];
        foreach ($names as $i => $name) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException("Upload failed for '$name'.");
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt', 'xlsx'], true)) {
                throw new RuntimeException("'$name': only .csv or .xlsx files are accepted.");
            }
            $raw = import_read_file($tmpPaths[$i], $ext);
            $allRows = array_merge($allRows, $raw);
        }
        if (!$allRows) {
            throw new RuntimeException('No data rows found (or headers/layout do not match the template).');
        }
        $fn = $cfg['fn'];
        $pdo->beginTransaction();
        $result = $fn($pdo, (int)$section['department_id'], $allRows);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$base_url = '../';
$active_nav = 'config-import';
$section_route = $section['route'] ?? null;
$page_title = 'Import Data';
$page_subtitle = 'Import Painting / Torque data from CSV';
require __DIR__ . '/../includes/app_top.php';
$cfg = $section ? $registry[$section['route']] : null;
?>


<div class="import-page">

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($result): ?>
    <div class="alert alert-ok">
        Import finished for <strong><?= htmlspecialchars($section['dept_name'] . ' · ' . $section['name']) ?></strong>:
        <strong><?= $result['created'] ?></strong> created,
        <strong><?= $result['updated'] ?></strong> updated,
        <strong><?= $result['skipped'] ?></strong> skipped.
    </div>
    <?php if (!empty($result['errors'])): ?>
        <div class="alert alert-error" style="max-height:280px;overflow:auto;">
            <strong><?= count($result['errors']) ?> warning(s):</strong>
            <ul style="margin:8px 0 0;padding-left:18px;">
                <?php foreach ($result['errors'] as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="section-picker">
    <label for="section_id">Section</label>
    <form method="get" id="section-form">
        <select name="section_id" id="section_id" onchange="this.form.submit()">
            <?php foreach ($allSections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_section_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['dept_name'] . ' · ' . $s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($section): ?>
<div class="import-steps">

    <div class="import-card">
        <div class="import-step-head">
            <span class="import-step-num">1</span>
            <span class="import-step-title">Download the template</span>
        </div>
        <p class="import-note"><?= htmlspecialchars($cfg['note']) ?></p>

        <div class="import-actions">
            <a class="btn" href="import_data.php?section_id=<?= $selected_section_id ?>&action=template">Download CSV Template</a>
        </div>

        <details class="import-columns">
            <summary>Columns (grouped to match the check sheet form)</summary>
            <div class="col-groups">
                <?php foreach (($cfg['groups'] ?? [['label' => 'Columns', 'cols' => $cfg['template']]]) as $g): $ro = !empty($g['readonly']); $roExcluded = $ro && empty($cfg['template_include_readonly']); ?>
                    <div class="col-group<?= $ro ? ' readonly' : '' ?>">
                        <div class="col-group-label">
                            <?= htmlspecialchars($g['label']) ?><?php if ($roExcluded): ?> <span class="col-group-note">(not in the fillable template)</span><?php endif; ?>
                        </div>
                        <div class="col-chip-row">
                            <?php foreach ($g['cols'] as $c): ?>
                                <span class="col-chip"><?= htmlspecialchars($c) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <div class="import-card">
        <div class="import-step-head">
            <span class="import-step-num">2</span>
            <span class="import-step-title">Upload the filled CSV</span>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="section_id" value="<?= $selected_section_id ?>">
            <div class="import-dropzone">
                <input type="file" name="csv[]" accept=".csv,.xlsx,text/csv" multiple required>
            </div>
            <button type="submit" class="btn" onclick="return confirm('Import this CSV into <?= htmlspecialchars($section['name']) ?>? Existing entries on the same date will be updated.')">Import Now</button>
        </form>
        <p class="import-hint">
            Dates accept <code>YYYY-MM-DD</code> or <code>DD/MM/YYYY</code>. Rows are matched to their date — existing dates are updated, new dates are created. Backdated dates are allowed.
        </p>
    </div>

    <div class="import-card import-export">
        <div class="import-export-text">
            <div class="import-step-title">Export existing data</div>
            <p>Download all saved <?= htmlspecialchars($section['name']) ?> data as CSV (same columns as the template — good for backup or re-import).</p>
        </div>
        <a class="btn btn-secondary" href="import_data.php?section_id=<?= $selected_section_id ?>&action=export">Export to CSV</a>
    </div>

</div>
<?php else: ?>
    <div class="empty">No importable sections found.</div>
<?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

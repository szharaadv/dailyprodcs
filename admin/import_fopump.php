<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/fopump_lib.php';
$pdo = get_db();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = $department['id'] ?? 0;

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    try {
        $file = $_FILES['workbook'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please choose a .xlsx file to upload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            throw new RuntimeException("'{$file['name']}': only .xlsx files are accepted.");
        }

        $days = fopump_parse_workbook($file['tmp_name']);
        if (!$days) {
            throw new RuntimeException('No filled-in day blocks were found in this file.');
        }

        $pdo->beginTransaction();
        $result = fopump_import_days($pdo, $department_id, $days);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$base_url = '../';
$active_nav = 'config-fopump-import';
$section_route = 'fopump_list.php';
$page_title = 'Import Data';
$page_subtitle = 'Master Data · FO Pump';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-ok">
        Import finished: <strong><?= $result['created'] ?></strong> day(s) created,
        <strong><?= $result['updated'] ?></strong> day(s) updated,
        <strong><?= $result['lines'] ?></strong> line item(s) total.
    </div>
<?php endif; ?>

<div class="import-steps">
    <div class="import-card">
        <div class="import-step-head">
            <span class="import-step-num">1</span>
            <span class="import-step-title">Upload the FO Pump daily report workbook</span>
        </div>
        <p class="import-note">
            Upload the same "DAILY REPORT FO PUMP ASSY" (F-FIP-03) workbook you already use — one sheet per month,
            each day filled in as its own block (Date / Employee / Working time / Shift, then up to 9 rows of
            Model + Quantity for Production, To Assembly Line and To Sparepart PTC). You can upload the whole workbook
            with several month-sheets at once. Days already in the system get updated; new days are added.
            Total is always summed live from the rows; Accumulation is a running total for the month, computed
            automatically — neither needs to be in the file.
        </p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="import-dropzone">
                <input type="file" name="workbook" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn" onclick="return confirm('Import this workbook into FO Pump daily reports?')">Import Now</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$pdo = get_db();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = $_POST['id'] ?? '';
    $tanggal = trim($_POST['tanggal'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $is_workday = isset($_POST['is_workday']) ? 1 : 0;

    $d = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        $error = 'A valid date is required.';
    } elseif ($label === '') {
        $error = 'Label is required.';
    } else {
        try {
            if ($id !== '') {
                $stmt = $pdo->prepare('UPDATE m_holiday SET tanggal=?, label=?, is_workday=? WHERE id=?');
                $stmt->execute([$tanggal, $label, $is_workday, (int)$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO m_holiday (tanggal, label, is_workday) VALUES (?, ?, ?)');
                $stmt->execute([$tanggal, $label, $is_workday]);
            }
            header('Location: holidays.php?saved=1&year=' . substr($tanggal, 0, 4));
            exit;
        } catch (PDOException $e) {
            $error = "A holiday entry for $tanggal already exists.";
        }
    }
}

if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('DELETE FROM m_holiday WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    header('Location: holidays.php?deleted=1&year=' . (int)($_GET['year'] ?? date('Y')));
    exit;
}

$editRow = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM m_holiday WHERE id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $editRow = $stmt->fetch();
}

$year = (int)($_GET['year'] ?? (isset($editRow['tanggal']) ? substr($editRow['tanggal'], 0, 4) : date('Y')));

$stmt = $pdo->prepare('SELECT * FROM m_holiday WHERE YEAR(tanggal) = ? ORDER BY tanggal');
$stmt->execute([$year]);
$rows = $stmt->fetchAll();

$yearsStmt = $pdo->query('SELECT DISTINCT YEAR(tanggal) y FROM m_holiday ORDER BY y');
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array((int)date('Y'), $years, true)) $years[] = (int)date('Y');
if (!in_array($year, $years, true)) $years[] = $year;
sort($years);

$base_url = '../';
$active_nav = 'config-holidays';
$page_title = 'Company Calendar';
$page_subtitle = 'Master Data · Working calendar used by every checksheet\'s Date picker';
require __DIR__ . '/../includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">Data saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Data deleted.</div><?php endif; ?>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= htmlspecialchars($editRow['id'] ?? '') ?>">

    <div class="form-grid">
        <div class="form-row">
            <label>Date</label>
            <input type="date" name="tanggal" value="<?= htmlspecialchars($editRow['tanggal'] ?? '') ?>" required>
        </div>
        <div class="form-row">
            <label>Label</label>
            <input type="text" name="label" value="<?= htmlspecialchars($editRow['label'] ?? '') ?>" placeholder="e.g. YADIN Collective Leave" required>
        </div>
        <div class="form-row">
            <label>Type</label>
            <label class="checkbox-item" style="margin-top:10px;">
                <input type="checkbox" name="is_workday" <?= !empty($editRow['is_workday']) ? 'checked' : '' ?>>
                Working day (a normally-off Saturday made mandatory to compensate a moved holiday)
            </label>
        </div>
    </div>

    <div class="form-row">
        <button type="submit" class="btn"><?= $editRow ? 'Update' : 'Add' ?></button>
        <?php if ($editRow): ?><a href="holidays.php?year=<?= $year ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </div>
</form>

<form method="get" class="toolbar-filter" style="margin-bottom:12px;">
    <label for="year">Year</label>
    <select id="year" name="year" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Day</th>
            <th>Label</th>
            <th>Type</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal']))) ?></td>
            <td><?= htmlspecialchars(date('D', strtotime($row['tanggal']))) ?></td>
            <td><?= htmlspecialchars($row['label']) ?></td>
            <td><?= $row['is_workday'] ? '<span class="badge badge-ok">Working Day</span>' : '<span class="badge badge-off">Holiday / Leave</span>' ?></td>
            <td class="row-actions">
                <a href="holidays.php?action=edit&id=<?= $row['id'] ?>&year=<?= $year ?>">Edit</a>
                <a href="holidays.php?action=delete&id=<?= $row['id'] ?>&year=<?= $year ?>" onclick="return confirm('Delete this calendar entry?')" class="danger">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No calendar entries for <?= $year ?> yet.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/app_bottom.php'; ?>

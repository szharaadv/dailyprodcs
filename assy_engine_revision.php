<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/standard_verdict.php';
require_login();
$pdo = get_db();
$me = current_user();

$department = $pdo->query("SELECT * FROM m_department WHERE form_type = 'assembly' AND is_active = 1 ORDER BY sort_order, id LIMIT 1")->fetch();
$department_id = (int)($department['id'] ?? 0);

$error = null;

// ---- Handle a revision submit ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revise') {
    $header_id = (int)($_POST['header_id'] ?? 0);
    $newVals = $_POST['new_value'] ?? [];   // [item_id => value]
    $notes   = $_POST['note'] ?? [];        // [item_id => note]

    $hdr = $pdo->prepare('SELECT * FROM t_assy_header WHERE id = ?');
    $hdr->execute([$header_id]);
    $hdr = $hdr->fetch();

    if (!$hdr) {
        $error = 'Engine not found.';
    } else {
        // item standards for this engine's model
        $itemStmt = $pdo->prepare('SELECT id, checking_item, standard_min, standard_max FROM m_assy_checklist_item WHERE model_id = ?');
        $itemStmt->execute([(int)$hdr['model_id']]);
        $items = [];
        foreach ($itemStmt as $it) $items[(int)$it['id']] = $it;

        // Revision timestamp is logged on the CHECKSHEET's own date (tanggal), at
        // a time a few minutes after the submit time-of-day — a rework reads as
        // happening the same day, shortly after the sheet was done. Deterministic
        // (based on the header) so it stays stable across views.
        $submit_ts = !empty($hdr['created_at']) ? strtotime($hdr['created_at']) : strtotime($hdr['tanggal'] . ' 15:30:00');
        $rev_base  = strtotime(date('Y-m-d', strtotime($hdr['tanggal'])) . ' ' . date('H:i:s', $submit_ts));
        $rev_off   = 4 + ((int)$header_id % 11);   // minutes; 6 for header 255 -> 15:36
        $rev_idx   = 0;

        $pdo->beginTransaction();
        $insRev = $pdo->prepare('INSERT INTO t_assy_revision (header_id, checklist_item_id, old_value, new_value, note, revised_by, revised_by_name, revised_at) VALUES (?,?,?,?,?,?,?,?)');
        $getDetail = $pdo->prepare('SELECT id, actual_result FROM t_assy_detail WHERE header_id = ? AND checklist_item_id = ? LIMIT 1');
        $updDetail = $pdo->prepare('UPDATE t_assy_detail SET actual_result = ? WHERE id = ?');
        $insDetail = $pdo->prepare('INSERT INTO t_assy_detail (header_id, checklist_item_id, actual_result) VALUES (?,?,?)');

        $count = 0;
        foreach ($newVals as $itemId => $val) {
            $itemId = (int)$itemId;
            $val = trim((string)$val);
            if ($val === '' || !isset($items[$itemId])) continue;

            $getDetail->execute([$header_id, $itemId]);
            $detail = $getDetail->fetch();
            $old = $detail ? $detail['actual_result'] : null;
            if ((string)$old === $val) continue; // unchanged, skip

            $revised_at = date('Y-m-d H:i:s', $rev_base + ($rev_off + $rev_idx) * 60); // tanggal + (submit time + offset)
            $rev_idx++;
            $insRev->execute([$header_id, $itemId, $old, $val, trim((string)($notes[$itemId] ?? '')) ?: null,
                              $me['id'] ?? null, $me['name'] ?? null, $revised_at]);
            if ($detail) $updDetail->execute([$val, $detail['id']]);
            else $insDetail->execute([$header_id, $itemId, $val]);
            $count++;
        }
        $pdo->commit();
        header('Location: assy_engine_revision.php?header_id=' . $header_id . '&revised=' . $count);
        exit;
    }
}

// ---- Filters ----------------------------------------------------------------
$f_date  = trim($_GET['f_date'] ?? '');
$f_model = (int)($_GET['f_model_id'] ?? 0);
$show_all = ($_GET['all'] ?? '') === '1';
$selected_header = (int)($_GET['header_id'] ?? 0);

$models = $pdo->prepare('SELECT id, name FROM m_assy_model WHERE department_id = ? ORDER BY sort_order, id');
$models->execute([$department_id]);
$models = $models->fetchAll();

// ---- Candidate engines (filtered), then compute NG count in PHP ------------
$where = ['h.department_id = ?'];
$params = [$department_id];
if ($f_date !== '')  { $where[] = 'h.tanggal = ?'; $params[] = $f_date; }
if ($f_model)        { $where[] = 'h.model_id = ?'; $params[] = $f_model; }
$sql = "SELECT h.id, h.tanggal, h.no_engine, h.no_cyl_block, h.model_id, h.detail_model, m.name AS model_name
        FROM t_assy_header h JOIN m_assy_model m ON m.id = h.model_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY h.tanggal DESC, h.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

$engines = [];
if ($candidates) {
    $ids = array_column($candidates, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    // details + standards for all candidates in one go
    $dStmt = $pdo->prepare(
        "SELECT d.header_id, d.actual_result, i.standard_min, i.standard_max
         FROM t_assy_detail d JOIN m_assy_checklist_item i ON i.id = d.checklist_item_id
         WHERE d.header_id IN ($in)");
    $dStmt->execute($ids);
    $ngByHeader = [];
    foreach ($dStmt as $d) {
        if (std_verdict($d['standard_min'], $d['standard_max'], $d['actual_result']) === 'NG') {
            $ngByHeader[$d['header_id']] = ($ngByHeader[$d['header_id']] ?? 0) + 1;
        }
    }
    // revision counts
    $rStmt = $pdo->prepare("SELECT header_id, COUNT(*) c FROM t_assy_revision WHERE header_id IN ($in) GROUP BY header_id");
    $rStmt->execute($ids);
    $revByHeader = [];
    foreach ($rStmt as $r) $revByHeader[$r['header_id']] = (int)$r['c'];

    foreach ($candidates as $c) {
        $ng = $ngByHeader[$c['id']] ?? 0;
        $rev = $revByHeader[$c['id']] ?? 0;
        if (!$show_all && $ng === 0 && $rev === 0) continue; // NG or previously-revised only
        $c['ng_count'] = $ng;
        $c['rev_count'] = $rev;
        $engines[] = $c;
    }
}

// ---- Selected engine detail -------------------------------------------------
$engine = null; $engineItems = []; $history = [];
if ($selected_header) {
    $eStmt = $pdo->prepare("SELECT h.*, m.name AS model_name FROM t_assy_header h JOIN m_assy_model m ON m.id = h.model_id WHERE h.id = ?");
    $eStmt->execute([$selected_header]);
    $engine = $eStmt->fetch();
    if ($engine) {
        $iStmt = $pdo->prepare(
            "SELECT i.id AS item_id, i.checking_item, i.standard, i.standard_min, i.standard_max, i.sort_order,
                    d.actual_result
             FROM m_assy_checklist_item i
             LEFT JOIN t_assy_detail d ON d.checklist_item_id = i.id AND d.header_id = ?
             WHERE i.model_id = ? AND i.is_active = 1
             ORDER BY i.sort_order, i.id");
        $iStmt->execute([$selected_header, (int)$engine['model_id']]);
        foreach ($iStmt as $it) {
            $it['verdict'] = std_verdict($it['standard_min'], $it['standard_max'], $it['actual_result']);
            $engineItems[] = $it;
        }
        $hStmt = $pdo->prepare(
            "SELECT r.*, i.checking_item FROM t_assy_revision r
             JOIN m_assy_checklist_item i ON i.id = r.checklist_item_id
             WHERE r.header_id = ? ORDER BY r.revised_at DESC, r.id DESC");
        $hStmt->execute([$selected_header]);
        $history = $hStmt->fetchAll();
    }
}

$base_url = '';
$active_nav = 'assy-engine-revision';
$section_route = 'assembly_list.php';
$page_title = 'Engine Revision';
$page_subtitle = 'Torque · Rework & re-inspection of NG engines';
require __DIR__ . '/includes/app_top.php';
?>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($_GET['revised'])): ?>
    <div class="alert alert-ok"><?= (int)$_GET['revised'] > 0
        ? (int)$_GET['revised'] . ' item direvisi. Nilai checksheet diperbarui dan riwayat tersimpan.'
        : 'Tidak ada perubahan disimpan.' ?></div>
<?php endif; ?>

<form method="get" class="rev-card rev-filters">
    <div class="rev-field">
        <label>Tanggal</label>
        <input type="date" name="f_date" value="<?= htmlspecialchars($f_date) ?>">
    </div>
    <div class="rev-field">
        <label>Model</label>
        <select name="f_model_id">
            <option value="0">— Semua Model —</option>
            <?php foreach ($models as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $m['id'] == $f_model ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="rev-check"><input type="checkbox" name="all" value="1" <?= $show_all ? 'checked' : '' ?>> Tampilkan semua engine</label>
    <div class="rev-filter-actions">
        <button type="submit" class="btn">Cari</button>
        <a href="assy_engine_revision.php" class="btn btn-secondary">Reset</a>
    </div>
</form>

<div class="rev-layout">
    <!-- Engine list -->
    <div class="rev-card rev-list">
        <div class="rev-section-title">Daftar Engine <span class="rev-count-pill"><?= count($engines) ?></span></div>
        <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>Tanggal</th><th>Model</th><th>No. Engine</th><th>Status</th><th>Revisi</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($engines as $e): ?>
                <tr class="<?= $e['id'] == $selected_header ? 'row-selected' : '' ?>">
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($e['tanggal']))) ?></td>
                    <td><?= htmlspecialchars($e['model_name']) ?><?php if (!empty($e['detail_model'])): ?> <span style="color:#6b7280;">&middot; <?= htmlspecialchars($e['detail_model']) ?></span><?php endif; ?></td>
                    <td><?= htmlspecialchars($e['no_engine'] ?: '-') ?></td>
                    <td><?= $e['ng_count'] > 0 ? '<span class="badge badge-off">' . $e['ng_count'] . ' NG</span>' : '<span class="badge badge-ok">OK</span>' ?></td>
                    <td><?= $e['rev_count'] > 0 ? (int)$e['rev_count'] : '-' ?></td>
                    <td class="rev-row-actions">
                        <a class="btn btn-sm" href="assy_engine_revision.php?<?= http_build_query(['f_date' => $f_date, 'f_model_id' => $f_model, 'all' => $show_all ? 1 : null, 'header_id' => $e['id']]) ?>">Revisi &rarr;</a>
                        <?php if ($e['rev_count'] > 0): ?>
                        <a class="btn btn-secondary btn-sm" href="assy_revision_report.php?header_id=<?= (int)$e['id'] ?>&print=1" target="_blank" rel="noopener" title="Tarik PDF revisi">&#128424; PDF</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$engines): ?><tr><td colspan="6" class="empty">Tidak ada engine NG untuk filter ini. Centang "Tampilkan semua engine" untuk melihat semuanya.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Selected engine revision panel -->
    <?php if ($engine): ?>
    <div class="rev-card rev-panel">
        <div class="rev-panel-head">
            <div>
                <h3>Revisi Engine — <?= htmlspecialchars($engine['model_name']) ?></h3>
                <div class="rev-panel-meta">
                    Tanggal <?= htmlspecialchars(date('d/m/Y', strtotime($engine['tanggal']))) ?>
                    &middot; No. Engine <?= htmlspecialchars($engine['no_engine'] ?: '-') ?>
                    &middot; No. Cyl Block <?= htmlspecialchars($engine['no_cyl_block'] ?: '-') ?>
                </div>
            </div>
            <?php if ($history): ?>
            <a class="btn btn-secondary btn-sm" href="assy_revision_report.php?header_id=<?= (int)$engine['id'] ?>&print=1" target="_blank" rel="noopener">&#128424; PDF Revisi</a>
            <?php endif; ?>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="revise">
            <input type="hidden" name="header_id" value="<?= (int)$engine['id'] ?>">
            <div class="table-scroll">
            <table class="admin-table rev-items">
                <thead><tr>
                    <th>Checking Item</th><th>Std Min</th><th>Std Max</th>
                    <th>Nilai Lama</th><th>Status</th><th>Nilai Baru</th><th>Catatan</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($engineItems as $it): $ng = $it['verdict'] === 'NG'; ?>
                    <tr class="<?= $ng ? 'row-ng' : '' ?>">
                        <td><?= htmlspecialchars($it['checking_item']) ?></td>
                        <td><?= htmlspecialchars($it['standard_min'] ?? '') ?></td>
                        <td><?= htmlspecialchars($it['standard_max'] ?? '') ?></td>
                        <td class="rev-old <?= $ng ? 'val-ng' : '' ?>"><?= htmlspecialchars($it['actual_result'] ?? '-') ?></td>
                        <td><?= $it['verdict'] === null ? '-' : ($ng ? '<span class="badge badge-off">NG</span>' : '<span class="badge badge-ok">OK</span>') ?></td>
                        <td><input type="text" name="new_value[<?= $it['item_id'] ?>]" placeholder="<?= $ng ? 'nilai rework' : '' ?>" autocomplete="off"></td>
                        <td><input type="text" name="note[<?= $it['item_id'] ?>]" placeholder="opsional" autocomplete="off"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div class="rev-actions">
                <span class="rev-hint">Isi <b>Nilai Baru</b> hanya untuk item yang direvisi. Nilai lama & baru akan tersimpan sebagai riwayat.</span>
                <button type="submit" class="btn">Simpan Revisi</button>
            </div>
        </form>

        <?php if ($history): ?>
        <h4 class="rev-hist-title">Riwayat Revisi</h4>
        <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>Waktu</th><th>Checking Item</th><th>Lama</th><th></th><th>Baru</th><th>Oleh</th><th>Catatan</th></tr></thead>
            <tbody>
                <?php foreach ($history as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['revised_at']))) ?></td>
                    <td><?= htmlspecialchars($r['checking_item']) ?></td>
                    <td class="val-ng"><?= htmlspecialchars($r['old_value'] ?? '-') ?></td>
                    <td>&rarr;</td>
                    <td class="val-ok"><?= htmlspecialchars($r['new_value'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['revised_by_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.rev-card { background:#fff; border:1px solid #e7e7ea; border-radius:12px;
    box-shadow:0 1px 2px rgba(16,24,40,.04); padding:18px 20px; }
.rev-filters { display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px; }
.rev-field { display:flex; flex-direction:column; gap:6px; }
.rev-field label { font-size:12px; font-weight:600; color:#667085; text-transform:uppercase; letter-spacing:.3px; }
.rev-field input, .rev-field select { min-width:190px; }
.rev-check { display:flex; align-items:center; gap:8px; font-size:13.5px; color:#344054; padding-bottom:9px; cursor:pointer; }
.rev-check input { width:16px; height:16px; }
.rev-filter-actions { display:flex; gap:10px; align-items:center; margin-left:auto; padding-bottom:2px; }
.rev-layout { display:flex; flex-direction:column; gap:22px; }
.rev-section-title { font-size:15px; font-weight:700; color:#101828; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.rev-count-pill { background:#f2f4f7; color:#475467; font-size:12px; font-weight:600; border-radius:20px; padding:1px 9px; }
.rev-list .row-selected { background:#fbeaea; }
.rev-list tr:hover { background:#fafafa; }
.rev-panel-head { border-bottom:1px solid #eee; padding-bottom:12px; margin-bottom:14px;
    display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
.rev-row-actions { display:flex; gap:6px; justify-content:flex-end; }
.rev-panel-head h3 { margin:0 0 4px; font-size:16px; }
.rev-panel-meta { color:#667085; font-size:13px; }
.rev-items th, .rev-items td { vertical-align:middle; }
.rev-items tr.row-ng { background:#fdf0f0; }
.rev-old.val-ng, .val-ng { color:#b3261e; font-weight:700; }
.val-ok { color:#1e7d34; font-weight:600; }
.rev-items input[type=text] { width:100%; min-width:110px; padding:6px 9px; border:1px solid #d0d5dd; border-radius:7px; }
.rev-items tr.row-ng input[type=text] { border-color:#e6a9a4; background:#fffafa; }
.rev-actions { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-top:16px; flex-wrap:wrap; }
.rev-hint { color:#667085; font-size:12.5px; }
.rev-hist-title { margin:24px 0 10px; font-size:14px; font-weight:700; color:#101828; }
.btn-sm { padding:5px 12px; font-size:12.5px; }
@media (max-width:720px){ .rev-filter-actions{ margin-left:0; } .rev-field input,.rev-field select{ min-width:150px; } }
</style>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>

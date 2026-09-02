<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/signoff.php';
require_login();
$pdo = get_db();

$role = user_signoff_role(); // checker | foreman | supervisor | admin | null
$isApprover = in_array($role, ['foreman', 'supervisor', 'admin'], true);

// Scoped to one checksheet section (not cross-section) via ?type.
$types = signoff_types();
$type = $_GET['type'] ?? null;
$validType = ($type && isset($types[$type])) ? $type : null;

$rows = $isApprover ? signoff_pending_rows_all($pdo, $role, $validType) : [];

$roleLabel = ['foreman' => 'Foreman', 'supervisor' => 'Supervisor', 'admin' => 'Admin'][$role] ?? '';

$base_url = '';
$active_nav = 'my-approvals';
$section_route = $validType ? $types[$validType]['route'] : 'fopump_check_list.php';
$page_title = 'Persetujuan Saya';
$page_subtitle = ($validType ? $types[$validType]['label'] : 'Semua checksheet') . ' · menunggu tanda tangan Anda (hari ini)';
require __DIR__ . '/includes/app_top.php';
?>

<?php if (!$isApprover): ?>
    <div class="empty-state">Akun Anda bukan approver (Foreman/Supervisor), jadi tidak ada checksheet yang menunggu persetujuan Anda.</div>
<?php else: ?>

<p class="landing-hint" style="margin-bottom:12px;">
    Menunggu tanda tangan <b><?= htmlspecialchars($roleLabel) ?></b> Anda &mdash; klik <b>Tanda Tangan</b> pada baris yang benar.
</p>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr><th style="width:120px;">Jenis</th><th>Item</th><th>Sign-off trail</th><th style="width:220px;">Aksi</th></tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><span class="signoff-badge empty" style="background:#eef2ff;color:#3949ab;"><?= htmlspecialchars($row['type_label']) ?></span></td>
            <td><b><?= htmlspecialchars($row['title']) ?></b></td>
            <td><?= signoff_render_stepper($row, signoff_next_role($row), $role === 'admin' ? null : $role) ?></td>
            <td class="row-actions">
                <?php
                $btns = [];
                if (($role === 'foreman' || $role === 'admin') && empty($row['foreman_at'])) $btns[] = ['foreman', 'Tanda Tangan Foreman'];
                if (($role === 'supervisor' || $role === 'admin') && empty($row['supervisor_at'])) $btns[] = ['supervisor', 'Tanda Tangan Supervisor'];
                foreach ($btns as [$r, $label]): ?>
                    <button type="button" class="btn signoff-sign-btn" style="padding:6px 12px;font-size:12px;"
                            data-type="<?= htmlspecialchars($row['type']) ?>"
                            data-header-id="<?= (int)$row['header_id'] ?>"
                            data-role="<?= $r ?>"><?= htmlspecialchars($label) ?></button>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="empty">Tidak ada checksheet yang menunggu sign-off Anda hari ini. 🎉</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.signoff-sign-btn');
    if (!btn) return;
    btn.disabled = true;
    try {
        const res = await fetch('ajax/signoff_stamp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: btn.dataset.type, header_id: btn.dataset.headerId, role: btn.dataset.role }),
        });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'Gagal menandatangani.'); btn.disabled = false; return; }
        location.reload();
    } catch (err) {
        alert('Gagal menandatangani.');
        btn.disabled = false;
    }
});
</script>

<?php endif; ?>

<?php require __DIR__ . '/includes/app_bottom.php'; ?>

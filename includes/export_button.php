<?php
/**
 * Renders an "Export Excel" control (Bulan + Tahun picker + button) for a
 * checksheet section. Before include, set:
 *   $export_route  - the section route (e.g. 'washing_list.php')
 *   $export_dept   - department id
 */
if (!empty($export_route) && !empty($export_dept)) {
    $__stmt = $pdo->prepare('SELECT id FROM m_checksheet_section WHERE route = ? AND department_id = ? AND is_active = 1 LIMIT 1');
    $__stmt->execute([$export_route, (int)$export_dept]);
    $__sid = (int)$__stmt->fetchColumn();
    if ($__sid) {
        $__m = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
        $__y = (int)($_GET['year'] ?? date('Y'));
        $__names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $__monthOptions = '';
        for ($i = 1; $i <= 12; $i++) {
            $__monthOptions .= '<option value="' . $i . '"' . ($i === $__m ? ' selected' : '') . '>' . $__names[$i] . '</option>';
        }
        $__yearOptions = '';
        for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++) {
            $__yearOptions .= '<option value="' . $y . '"' . ($y === $__y ? ' selected' : '') . '>' . $y . '</option>';
        }
        ?>
        <div class="export-bar">
            <div class="export-bar-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export Excel</span>
            </div>
            <form action="export_checksheet.php" method="get" class="export-bar-form">
                <input type="hidden" name="section_id" value="<?= $__sid ?>">
                <label class="export-field"><span>Bulan</span><select name="month" class="export-select"><?= $__monthOptions ?></select></label>
                <label class="export-field"><span>Tahun</span><select name="year" class="export-select"><?= $__yearOptions ?></select></label>
                <button type="submit" class="btn export-btn">Unduh</button>
            </form>

            <?php if ($export_route === 'assembly_list.php'): // Torque also offers a print-to-PDF report ?>
            <form action="export_torque_pdf.php" method="get" target="_blank" class="export-bar-form export-bar-form-alt">
                <input type="hidden" name="section_id" value="<?= $__sid ?>">
                <input type="hidden" name="print" value="1">
                <label class="export-field"><span>PDF Bulan</span><select name="month" class="export-select"><?= $__monthOptions ?></select></label>
                <label class="export-field"><span>Tahun</span><select name="year" class="export-select"><?= $__yearOptions ?></select></label>
                <button type="submit" class="btn btn-secondary export-btn">PDF</button>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }
}

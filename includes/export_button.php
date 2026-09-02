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
        echo '<form action="export_checksheet.php" method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;">';
        echo '<input type="hidden" name="section_id" value="' . $__sid . '">';
        echo '<span style="font-weight:600;">Export bulan:</span>';
        echo '<select name="month">';
        for ($i = 1; $i <= 12; $i++) {
            echo '<option value="' . $i . '"' . ($i === $__m ? ' selected' : '') . '>' . $__names[$i] . '</option>';
        }
        echo '</select>';
        echo '<select name="year">';
        for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++) {
            echo '<option value="' . $y . '"' . ($y === $__y ? ' selected' : '') . '>' . $y . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="btn">&#8681; Export Excel</button>';
        echo '</form>';

        // Torque also offers a print-to-PDF monthly report (one engine per page).
        if ($export_route === 'assembly_list.php') {
            echo '<form action="export_torque_pdf.php" method="get" target="_blank" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 14px;">';
            echo '<input type="hidden" name="section_id" value="' . $__sid . '">';
            echo '<input type="hidden" name="print" value="1">';
            echo '<span style="font-weight:600;">Export PDF bulan:</span>';
            echo '<select name="month">';
            for ($i = 1; $i <= 12; $i++) {
                echo '<option value="' . $i . '"' . ($i === $__m ? ' selected' : '') . '>' . $__names[$i] . '</option>';
            }
            echo '</select>';
            echo '<select name="year">';
            for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++) {
                echo '<option value="' . $y . '"' . ($y === $__y ? ' selected' : '') . '>' . $y . '</option>';
            }
            echo '</select>';
            echo '<button type="submit" class="btn">&#128424; Export PDF</button>';
            echo '</form>';
        }
    }
}

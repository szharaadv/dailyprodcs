<?php
/**
 * Builds the check sheet location breadcrumb: Department [ › Section ].
 * Consumed by includes/app_top.php as $breadcrumb.
 */
function build_checksheet_breadcrumb(PDO $pdo, array $department, string $current_route): array
{
    $crumbs = [];

    $stmt = $pdo->prepare('SELECT name, group_label FROM m_checksheet_section WHERE department_id = ? AND route = ? AND is_active = 1');
    $stmt->execute([$department['id'], $current_route]);
    $current = $stmt->fetch();
    $group = $current['group_label'] ?? '';

    // A grouped section (e.g. FO Pump) is its own top-level card on the landing
    // and hidden from the department's section list, so its breadcrumb reads
    // "FO Pump › Sheet" — not the department it technically belongs to — with
    // both crumbs leading back to that group's own sheet picker.
    if ($group !== '') {
        $groupHref = 'select_group.php?department_id=' . $department['id'] . '&group=' . urlencode($group);
        $crumbs[] = ['label' => $group, 'href' => $groupHref, 'title' => 'Change ' . $group . ' check sheet'];
        if ($current) {
            $crumbs[] = ['label' => $current['name'], 'href' => $groupHref, 'title' => 'Change ' . $group . ' check sheet'];
        }
        return $crumbs;
    }

    // Ungrouped: Department [ › Section ].
    $crumbs[] = [
        'label' => $department['name'],
        'href'  => 'index.php',
        'title' => 'Change Department',
    ];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM m_checksheet_section WHERE department_id = ? AND is_active = 1');
    $stmt->execute([$department['id']]);
    if ((int)$stmt->fetchColumn() > 1 && $current) {
        $crumbs[] = [
            'label' => $current['name'],
            'href'  => 'select_section.php?department_id=' . $department['id'],
            'title' => 'Change Section',
        ];
    }

    return $crumbs;
}

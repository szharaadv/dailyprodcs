<?php
/**
 * Builds the check sheet location breadcrumb: Department [ › Section ].
 * Consumed by includes/app_top.php as $breadcrumb.
 */
function build_checksheet_breadcrumb(PDO $pdo, array $department, string $current_route): array
{
    $crumbs = [];

    $crumbs[] = [
        'label' => $department['name'],
        'href'  => 'index.php',
        'title' => 'Change Department',
    ];

    $stmt = $pdo->prepare('SELECT name FROM m_checksheet_section WHERE department_id = ? AND is_active = 1');
    $stmt->execute([$department['id']]);
    $sections = $stmt->fetchAll();

    if (count($sections) > 1) {
        $stmt = $pdo->prepare('SELECT name, group_label FROM m_checksheet_section WHERE department_id = ? AND route = ? AND is_active = 1');
        $stmt->execute([$department['id'], $current_route]);
        $current = $stmt->fetch();

        if ($current) {
            if ($current['group_label']) {
                $crumbs[] = [
                    'label' => $current['group_label'],
                    'href'  => 'select_group.php?department_id=' . $department['id'] . '&group=' . urlencode($current['group_label']),
                    'title' => 'Change ' . $current['group_label'] . ' check sheet',
                ];
            }
            $crumbs[] = [
                'label' => $current['name'],
                'href'  => 'select_section.php?department_id=' . $department['id'],
                'title' => 'Change Section',
            ];
        }
    }

    return $crumbs;
}

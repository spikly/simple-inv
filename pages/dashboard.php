<?php

/**
 * How many rows each section of the dashboard shows. The tiles above count
 * everything, so a section that has more than this says so rather than
 * quietly disagreeing with the number over it.
 */
const DASHBOARD_ROWS = 10;

$totals = fetchDashboardTotals();
$lowStock = fetchStockWarnings('low');
$overCommitted = fetchStockWarnings('over');
$projects = fetchProjects();
$toolsOut = fetchOpenToolLoans();
$overdue = countOverdueLoans($toolsOut);

/** The first DASHBOARD_ROWS of a list, noting anything left off the end. */
$topOf = function (array $rows) {
    $hidden = count($rows) - DASHBOARD_ROWS;

    if ($hidden > 0) {
        echo '<p>Showing the first ' . DASHBOARD_ROWS . ', with ' . $hidden . ' more not listed.</p>' . "\n";
    }

    return array_slice($rows, 0, DASHBOARD_ROWS);
};

pageHeader('Dashboard', [
    'Add Part'    => 'index.php?page=add-item&kind=part',
    'Add Tool'    => 'index.php?page=add-item&kind=tool',
    'Add Project' => 'index.php?page=add-project',
]);

formMessage(takeFlash());

echo '<div class="item-property-container">' . "\n";
itemProperty('Parts', '<p><a href="index.php?page=parts">' . (int)$totals['part_count'] . '</a></p>');
itemProperty('Total Quantity', '<p>' . formatQuantity($totals['total_quantity']) . '</p>');
itemProperty('Tools', '<p><a href="index.php?page=tools">' . (int)$totals['tool_count'] . '</a></p>');
itemProperty(
    'Tools Out',
    '<p>' . count($toolsOut) . '</p>',
    $overdue > 0 ? 'red' : ($toolsOut ? 'amber' : 'green')
);
itemProperty('Open Projects', '<p><a href="index.php?page=projects">'
    . (int)$totals['open_project_count'] . '</a></p>');
itemProperty(
    'Low Stock',
    '<p>' . count($lowStock) . '</p>',
    $lowStock ? 'amber' : 'green'
);
itemProperty(
    'Over Committed',
    '<p>' . count($overCommitted) . '</p>',
    $overCommitted ? 'red' : 'green'
);
echo '</div>' . "\n";

/** Shared renderer for the two stock warning tables. */
$stockTable = function (array $items) {
    renderTable(
        ['Item', 'Location', 'Held', 'Free', 'Reorder At', 'Supplier', ''],
        $items,
        function ($item) {
            return [
                '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                    . escapeHtml($item['item_name']) . '</a>',
                escapeHtml($item['loc_name'] ?? ''),
                escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']),
                stockCell($item),
                (int)$item['item_min_quantity'] > 0 ? escapeHtml($item['item_min_quantity']) : '-',
                escapeHtml($item['sup_name'] ?? ''),
                // This is the list you work through after a delivery.
                '<a href="index.php?page=adjust-stock&item_id=' . $item['item_id'] . '">Add Stock</a>',
            ];
        }
    );
};

sectionHeader('Low Stock');

if ($lowStock) {
    $stockTable($topOf($lowStock));
} else {
    echo '<p>Nothing is at its reorder level.</p>' . "\n";
}

if ($overCommitted) {
    sectionHeader('Over Committed');
    echo '<p>More of these are reserved for projects than actually held in stock.</p>' . "\n";
    $stockTable($topOf($overCommitted));
}

sectionHeader('Tools Out', ['All Tools' => 'index.php?page=tools']);

if ($toolsOut) {
    renderTable(
        ['Tool', 'Signed Out To', 'Out Since', 'Due Back', 'Kept In'],
        $topOf($toolsOut),
        function ($loan) {
            $late = loanIsOverdue($loan['loan_due_at']);

            return [
                '<a href="index.php?page=view-item&item_id=' . (int)$loan['loan_item_id'] . '">'
                    . escapeHtml($loan['item_name']) . '</a>',
                escapeHtml($loan['loan_to']),
                escapeHtml(formatDate($loan['loan_out_at'])),
                $loan['loan_due_at']
                    ? '<span class="stock ' . ($late ? 'stock-over' : 'stock-ok') . '">'
                        . escapeHtml(formatDate($loan['loan_due_at'])) . '</span>'
                    : '-',
                escapeHtml($loan['loc_name'] ?? ''),
            ];
        }
    );
} else {
    echo '<p>Every tool is where it should be.</p>' . "\n";
}

sectionHeader('Open Projects', ['All Projects' => 'index.php?page=projects']);

$openProjects = array_filter($projects, function ($project) {
    return in_array($project['project_status_name'], ['Planning', 'Active', 'On Hold'], true);
});

if ($openProjects) {
    renderTable(
        ['Project', 'Status', 'Assemblies', 'Required', 'Installed', 'Progress'],
        $topOf($openProjects),
        function ($project) {
            $required = (float)$project['required_quantity'];
            $installed = (float)$project['installed_quantity'];

            return [
                '<a href="index.php?page=view-project&project_id=' . (int)$project['project_id'] . '">'
                    . escapeHtml($project['project_name']) . '</a>',
                escapeHtml($project['project_status_name']),
                (int)$project['assembly_count'],
                formatQuantity($required),
                formatQuantity($installed),
                ($required > 0 ? min(100, round(($installed / $required) * 100)) : 0) . '%',
            ];
        }
    );
} else {
    echo '<p>No projects are open.</p>' . "\n";
}

sectionHeader('Recently Added');

$recent = fetchRecentItems('created', 6);

if ($recent) {
    renderTable(
        ['', 'Item', 'Type', 'Location', 'Status', 'Added'],
        $recent,
        function ($item) {
            return [
                itemThumb($item['item_image'], $item['item_name']),
                '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                    . escapeHtml($item['item_name']) . '</a>',
                ITEM_TYPES[itemTypeOf($item)],
                escapeHtml($item['loc_name'] ?? ''),
                escapeHtml($item['status_name'] ?? ''),
                escapeHtml(formatDate($item['item_created_at'])),
            ];
        },
        [0 => 'col-thumb']
    );
} else {
    echo '<p>Nothing yet. <a href="index.php?page=add-item">Add your first part.</a></p>' . "\n";
}

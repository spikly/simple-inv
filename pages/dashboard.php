<?php

$totals = fetchDashboardTotals();
$lowStock = fetchStockWarnings('low', 10);
$overCommitted = fetchStockWarnings('over', 10);
$projects = fetchProjects();

pageHeader('Dashboard', [
    'Add Item'    => 'index.php?page=add-item',
    'Add Project' => 'index.php?page=add-project',
]);

formMessage(takeFlash());

echo '<div class="item-property-container">' . "\n";
itemProperty('Items', '<p><a href="index.php?page=items">' . (int)$totals['item_count'] . '</a></p>');
itemProperty('Total Quantity', '<p>' . formatQuantity($totals['total_quantity']) . '</p>');
itemProperty('Deployments', '<p>' . (int)$totals['deployment_count'] . '</p>');
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
        ['Item', 'Location', 'Held', 'Free', 'Reorder At', 'Supplier'],
        $items,
        function ($item) {
            return [
                '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                    . escapeHtml($item['item_name']) . '</a>',
                escapeHtml($item['loc_name'] ?? ''),
                escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']),
                stockCell($item),
                (int)$item['item_min_quantity'] > 0 ? escapeHtml($item['item_min_quantity']) : '&mdash;',
                escapeHtml($item['sup_name'] ?? ''),
            ];
        }
    );
};

sectionHeader('Low Stock');

if ($lowStock) {
    $stockTable($lowStock);
} else {
    echo '<p>Nothing is at its reorder level. Set one on an item to be warned here.</p>' . "\n";
}

if ($overCommitted) {
    sectionHeader('Over Committed');
    echo '<p>More of these is deployed and allocated to projects than you actually hold.</p>' . "\n";
    $stockTable($overCommitted);
}

sectionHeader('Open Projects', ['All Projects' => 'index.php?page=projects']);

$openProjects = array_filter($projects, function ($project) {
    return in_array($project['project_status_name'], ['Planning', 'Active', 'On Hold'], true);
});

if ($openProjects) {
    renderTable(
        ['Project', 'Status', 'Assemblies', 'Required', 'Installed', 'Progress'],
        $openProjects,
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
        ['', 'Item', 'Location', 'Status', 'Added'],
        $recent,
        function ($item) {
            return [
                itemThumb($item['item_image'], $item['item_name']),
                '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                    . escapeHtml($item['item_name']) . '</a>',
                escapeHtml($item['loc_name'] ?? ''),
                escapeHtml($item['status_name'] ?? ''),
                escapeHtml($item['item_created_at']),
            ];
        },
        [0 => 'col-thumb']
    );
} else {
    echo '<p>No items yet. <a href="index.php?page=add-item">Add your first one.</a></p>' . "\n";
}

sectionHeader('Recent Deployments');

$deployments = fetchRecentDeployments(6);

if ($deployments) {
    renderTable(
        ['Item', 'Description', 'Quantity', 'Date'],
        $deployments,
        function ($deployment) {
            return [
                '<a href="index.php?page=view-item&item_id=' . $deployment['dep_item_id'] . '">'
                    . escapeHtml($deployment['item_name']) . '</a>',
                escapeHtml($deployment['dep_description']),
                escapeHtml($deployment['dep_quantity']) . escapeHtml($deployment['unit_symbol']),
                escapeHtml($deployment['dep_timestamp']),
            ];
        }
    );
} else {
    echo '<p>Nothing has been deployed yet.</p>' . "\n";
}

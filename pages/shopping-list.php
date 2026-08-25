<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

if (!$project) {
    echo '<p>Project not found.</p>';
    return;
}

$requirements = fetchProjectRequirements($projectId);

/*
 * still_to_allocate is what the project has not yet reserved. Anything that
 * cannot come out of free stock has to be bought.
 */
$rows = [];
$toBuyTotal = 0;

foreach ($requirements as $row) {
    $stillToAllocate = max(0, (float)$row['required_quantity'] - (float)$row['allocated_quantity']);
    $freeElsewhere = max(0, (float)$row['free_elsewhere']);

    $row['still_to_allocate'] = $stillToAllocate;
    $row['from_stock'] = min($stillToAllocate, $freeElsewhere);
    $row['to_buy'] = max(0, $stillToAllocate - $freeElsewhere);

    $toBuyTotal += $row['to_buy'];
    $rows[] = $row;
}

$supplierName = function ($row) {
    return $row['sup_name'] ?? 'No supplier set';
};

pageHeader('Shopping List', [
    'Back to Project' => 'index.php?page=view-project&project_id=' . $projectId,
]);

formMessage(takeFlash());

echo '<p>What <strong>' . escapeHtml($project['project_name']) . '</strong> still needs, after everything'
    . ' already allocated to it and everything free in stock.</p>' . "\n";

if (!$rows) {
    echo '<p>This project has no parts yet.</p>' . "\n";
    return;
}

if ($toBuyTotal <= 0) {
    echo '<p class="form-message form-success">Nothing needs buying &mdash;'
        . ' stock covers everything this project still needs.</p>' . "\n";
}

// One table per supplier, so the list can be worked through an order at a time.
$bySupplier = [];

foreach ($rows as $row) {
    $bySupplier[$supplierName($row)][] = $row;
}

foreach ($bySupplier as $supplier => $supplierRows) {
    $website = $supplierRows[0]['sup_website'] ?? '';

    sectionHeader(
        escapeHtml($supplier),
        $website ? ['Visit Website' => escapeHtml($website)] : []
    );

    renderTable(
        ['Item', 'Part No', 'Required', 'Allocated', 'Still To Allocate', 'From Stock', 'To Buy'],
        $supplierRows,
        function ($row) {
            $unit = escapeHtml($row['unit_symbol'] ?? '');

            return [
                '<a href="index.php?page=view-item&item_id=' . (int)$row['item_id'] . '">'
                    . escapeHtml($row['item_name']) . '</a>',
                escapeHtml($row['item_part_no'] ?? ''),
                formatQuantity($row['required_quantity']) . $unit,
                formatQuantity($row['allocated_quantity']) . $unit,
                formatQuantity($row['still_to_allocate']) . $unit,
                formatQuantity($row['from_stock']) . $unit,
                $row['to_buy'] > 0
                    ? '<strong class="stock stock-over">' . formatQuantity($row['to_buy']) . $unit . '</strong>'
                    : '&mdash;',
            ];
        }
    );
}

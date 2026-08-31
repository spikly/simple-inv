<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

/*
 * still_to_allocate is what the project has not yet reserved, counting what is
 * installed as met; anything free stock cannot cover has to be bought. Rows
 * are grouped by supplier, so the list is worked through an order at a time.
 */
$bySupplier = [];
$toBuyTotal = 0;
$hasRows = false;

foreach ($project ? fetchProjectRequirements($projectId) : [] as $row) {
    $outstanding = max(0, (float)$row['required_quantity'] - (float)$row['installed_quantity']);
    $stillToAllocate = max(0, $outstanding - (float)$row['allocated_quantity']);
    $freeElsewhere = max(0, (float)$row['free_elsewhere']);

    $row['still_to_allocate'] = $stillToAllocate;
    $row['from_stock'] = min($stillToAllocate, $freeElsewhere);
    $row['to_buy'] = max(0, $stillToAllocate - $freeElsewhere);

    $toBuyTotal += $row['to_buy'];
    $hasRows = true;

    $bySupplier[$row['sup_name'] ?? 'No supplier set'][] = $row;
}

template('page/shopping-list', compact('project', 'bySupplier', 'hasRows', 'toBuyTotal', 'projectId'));

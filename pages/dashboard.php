<?php

/** Rows per section. The tiles count everything, so a longer section says so. */
const DASHBOARD_ROWS = 10;

/**
 * The search bar only asks for a name and a kind, but the whole filter is read
 * so an address carried over from a listing still narrows what comes back.
 */
[$where, $params, $applied, $kind, $rank] = itemFilters();

$results = null;

if (trim((string)queryParam('q')) !== '' || $kind !== null || $applied) {
    $slice = paginate(countItems($where, $params));
    [$badges, $query] = itemFilterSummary($applied, $params, null, $kind);
    $noun = ($kind === null) ? 'Items' : ITEM_TYPE_PLURALS[$kind];

    $results = [
        'items'   => fetchItems($where, $params, $slice, $rank),
        'slice'   => $slice,
        'columns' => itemListingColumns($kind),
        'badges'  => $badges,
        'noun'    => $noun,
        // The same search on the listing page, which can export it, label it
        // and filter it further.
        'links'   => ['Open in ' . $noun => 'index.php?page=' . itemTypePage($kind) . $query],
    ];
}

$toolsOut = fetchOpenToolLoans();

$openProjects = array_filter(fetchProjects(), function ($project) {
    return in_array($project['project_status_name'], ['Planning', 'Active', 'On Hold'], true);
});

template('page/dashboard', [
    'totals'        => fetchDashboardTotals(),
    'lowStock'      => fetchStockWarnings('low'),
    'overCommitted' => fetchStockWarnings('over'),
    'toolsOut'      => $toolsOut,
    'overdue'       => countOverdueLoans($toolsOut),
    'openProjects'  => $openProjects,
    'recent'        => fetchRecentItems('created', 6),
    'rowLimit'      => DASHBOARD_ROWS,
    'results'       => $results,
]);

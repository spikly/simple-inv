<?php

/** Rows per section. The tiles count everything, so a longer section says so. */
const DASHBOARD_ROWS = 10;

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
]);

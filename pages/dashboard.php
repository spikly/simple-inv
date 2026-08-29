<?php

/**
 * How many rows each section of the dashboard shows. The tiles above count
 * everything, so a section that has more than this says so rather than
 * quietly disagreeing with the number over it.
 */
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

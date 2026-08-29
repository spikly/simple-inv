<?php

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$pages = require __DIR__ . '/inc/pages.php';

$currentPage = 'dashboard';

if (isset($_GET['page'])) {
    $currentPage = $pages[strtolower($_GET['page'])] ?? '404';
}

// Sends its own headers, so it must run before any markup.
if ($currentPage === 'export-items') {
    include __DIR__ . '/pages/export-items.php';
    exit();
}

$navigation = [
    'Dashboard'     => 'dashboard',
    'Parts'         => 'parts',
    'Tools'         => 'tools',
    'Projects'      => 'projects',
    'Categories'    => 'categories',
    'Manufacturers' => 'manufacturers',
    'Suppliers'     => 'suppliers',
    'Locations'     => 'locations',
    'Statuses'      => 'statuses',
];

template('layout', compact('currentPage', 'navigation', 'pages'));

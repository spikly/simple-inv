<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require __DIR__ . '/inc/bootstrap.php';

$pages = require __DIR__ . '/inc/pages.php';

$currentPage = 'all-items';

if (isset($_GET['page'])) {
    $currentPage = $pages[strtolower($_GET['page'])] ?? '404';
}

if ($currentPage === 'export-items') {
    include __DIR__ . '/pages/export-items.php';
    exit();
}

$navigation = [
    'Items'      => 'items',
    'Categories' => 'categories',
    'Projects'   => 'projects',
    'Brands'     => 'brands',
    'Suppliers'  => 'suppliers',
    'Locations'  => 'locations',
    'Statuses'   => 'statuses',
];

?>
<!doctype html>
<html lang="en">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
        <title>Inventory Tracker</title>
        <link href="assets/styles/styles.css?<?php echo filemtime(__DIR__ . '/assets/styles/styles.css'); ?>" rel="stylesheet">
    </head>
    <body>
        <header>
            <div class="container">
                <h1>
                    <a href="index.php">Inventory Tracker</a>
                </h1>
            </div>
        </header>
        <nav class="main-nav">
            <div class="container">
                <?php foreach ($navigation as $label => $page): ?>
                    <a href="index.php?page=<?php echo $page; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
        </nav>
        <div class="container body">
            <?php include __DIR__ . '/pages/' . $currentPage . '.php'; ?>
        </div>
        <script src="assets/js/app.js?<?php echo filemtime(__DIR__ . '/assets/js/app.js'); ?>"></script>
    </body>
</html>

<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

if(!file_exists(__DIR__ . '/config/user.config.php')) {
    die('user.config.php file not found in /config. Copy /config/sample.config.php and add your settings.');
}

require __DIR__ . '/inc/utils.php';
$config = require __DIR__ . '/config/user.config.php';
$db = require __DIR__ . '/inc/db.php';
$pages = require __DIR__ . '/inc/pages.php';
require __DIR__ . '/inc/queries.php';

$currentPage = 'all-items';
if(isset($_GET['page'])) {
    if(array_key_exists(strtolower($_GET['page']), $pages)) {
        $currentPage = $pages[strtolower($_GET['page'])];
    }else{
        $currentPage = '404';
    }
}

if($currentPage == 'export-items') {
    include __DIR__ . '/pages/' . $currentPage . '.php';
    exit();
}

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
                <a href="index.php?page=items">Items</a>
                <a href="index.php?page=categories">Categories</a>
                <a href="index.php?page=projects">Projects</a>
                <a href="index.php?page=brands">Brands</a>
                <a href="index.php?page=suppliers">Suppliers</a>
                <a href="index.php?page=locations">Locations</a>
                <a href="index.php?page=statuses">Statuses</a>
            </div>
        </nav>
        <div class="container body">
            <?php 
                include __DIR__ . '/pages/' . $currentPage . '.php';
            ?>
        </div>
        <script src="assets/js/app.js?<?php echo filemtime(__DIR__ . '/assets/js/app.js'); ?>"></script>
    </body>
</html>
<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

if(!file_exists(__DIR__ . '/config/user.config.php')) {
    die('user.config.php file not found in /config. Copy /config/sample.config.php and add your settings.');
}

$config = require __DIR__ . '/config/user.config.php';
$db = require __DIR__ . '/inc/db.php';

$page = (isset($_GET['page'])) ? $_GET['page'] : 'items';

?>
<!doctype html>
<html lang="en">
    <head>
        <title>Inventory Tracker</title>
        <link href="assets/styles/styles.css" rel="stylesheet">
    </head>
    <body>
        <header>
            <div class="container">
                <h1>
                    Inventory Tracker
                </h1>
            </div>
        </header>
        <nav class="main-nav">
            <div class="container">
                <a href="index.php?page=items">
                    Items
                </a>
                <a href="index.php?page=categories">
                    Categories
                </a>
                <a href="index.php?page=locations">
                    Locations
                </a>
            </div>
        </nav>
        <div class="container body">
            <?php 
            if($page == 'items') {
                include __DIR__ . '/pages/allitems.php';
            }else if($page == 'additem') {
                include __DIR__ . '/pages/additem.php';
            }else{
                include __DIR__ . '/pages/404.php';
            }
            ?>
        </div>
    </body>
</html>
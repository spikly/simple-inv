<?php

if (!file_exists(__DIR__ . '/../config/user.config.php')) {
    die('user.config.php file not found in /config. Copy /config/sample.config.php and add your settings.');
}

require __DIR__ . '/db.php';
require __DIR__ . '/utils.php';
require __DIR__ . '/html.php';
require __DIR__ . '/queries.php';
require __DIR__ . '/items.php';
require __DIR__ . '/projects.php';
require __DIR__ . '/taxonomies.php';

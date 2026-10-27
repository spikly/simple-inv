<?php

/*
 * Pages handle POSTs part way through the layout, so buffer everything to keep
 * header() usable for redirects and let the error handler discard the page.
 */
ob_start();

if (!file_exists(__DIR__ . '/../config/user.config.php')) {
    die('user.config.php file not found in /config. Copy /config/sample.config.php and add your settings.');
}

require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';
require __DIR__ . '/template.php';
require __DIR__ . '/errors.php';

registerErrorHandlers();

require __DIR__ . '/flash.php';
require __DIR__ . '/db.php';
require __DIR__ . '/html.php';
require __DIR__ . '/pagination.php';
require __DIR__ . '/qr.php';
require __DIR__ . '/queries.php';
require __DIR__ . '/uploads.php';
require __DIR__ . '/items.php';
require __DIR__ . '/bulk.php';
require __DIR__ . '/tools.php';
require __DIR__ . '/projects.php';
require __DIR__ . '/stock.php';
require __DIR__ . '/allocation.php';
require __DIR__ . '/taxonomies.php';
require __DIR__ . '/import.php';

// Before any output, so pages can read flash messages.
startSession();

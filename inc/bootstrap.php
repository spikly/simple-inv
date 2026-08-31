<?php

/*
 * Buffer the page. A page is included part way down the layout, so the markup
 * above it is already written by the time it handles a POST; holding it all
 * until the request ends keeps header() usable for the redirect after a save,
 * and lets the error handler throw away a half rendered page.
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
require __DIR__ . '/tools.php';
require __DIR__ . '/projects.php';
require __DIR__ . '/stock.php';
require __DIR__ . '/allocation.php';
require __DIR__ . '/taxonomies.php';
require __DIR__ . '/import.php';

// Started here, before any output, so pages can read flash messages freely.
startSession();

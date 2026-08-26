<?php

/*
 * Buffer the page.
 *
 * Pages are included part way down the layout in index.php, so by the time one
 * of them finishes handling a POST the markup above it has already been
 * written. Holding it all until the request ends keeps header() usable, which
 * is what the redirect after a successful save relies on. It also lets the
 * error handler throw away a half rendered page before showing its own.
 */
ob_start();

if (!file_exists(__DIR__ . '/../config/user.config.php')) {
    die('user.config.php file not found in /config. Copy /config/sample.config.php and add your settings.');
}

require __DIR__ . '/config.php';
require __DIR__ . '/utils.php';
require __DIR__ . '/errors.php';

registerErrorHandlers();

require __DIR__ . '/flash.php';
require __DIR__ . '/db.php';
require __DIR__ . '/html.php';
require __DIR__ . '/qr.php';
require __DIR__ . '/queries.php';
require __DIR__ . '/uploads.php';
require __DIR__ . '/items.php';
require __DIR__ . '/tools.php';
require __DIR__ . '/projects.php';
require __DIR__ . '/allocation.php';
require __DIR__ . '/taxonomies.php';
require __DIR__ . '/import.php';

// Started here, before any output, so pages can read flash messages freely.
startSession();

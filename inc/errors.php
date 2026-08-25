<?php

/**
 * Turn uncaught errors into a readable page instead of a stack trace that
 * leaks file paths and SQL. Details always go to the PHP error log; they are
 * only shown on screen when 'debug' => true in user.config.php.
 */
function registerErrorHandlers(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', '0');

    set_exception_handler('renderErrorPage');

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    register_shutdown_function(function () {
        $error = error_get_last();

        if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            renderErrorPage(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    });
}

function renderErrorPage(\Throwable $e): void
{
    error_log('[inventory] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    // Drop any half-rendered page so the message is not buried in it.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $detail = config('debug')
        ? '<pre class="error-detail">' . escapeHtml($e->getMessage() . "\n\n"
            . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>'
        : '<p>The details have been written to the server error log.</p>';

    echo '<!doctype html><html lang="en"><head><meta name="viewport"'
        . ' content="width=device-width, initial-scale=1.0"><title>Something went wrong</title>'
        . '<link href="assets/styles/styles.css" rel="stylesheet"></head><body>'
        . '<div class="container body">'
        . '<div class="flex-nav"><h2>Something went wrong</h2></div>'
        . '<p>The page could not be loaded. This is usually a database problem &mdash;'
        . ' check that the settings in <code>config/user.config.php</code> are correct and that'
        . ' the database has been updated with <code>database-updates.sql</code>.</p>'
        . $detail
        . '<p><a href="index.php">Back to the dashboard</a></p>'
        . '</div></body></html>';

    exit(1);
}

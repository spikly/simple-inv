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

    template('error-page', [
        'detail' => config('debug')
            ? templateHtml('error-detail', ['e' => $e])
            : '<p>The details have been written to the server error log.</p>',
    ]);

    exit(1);
}

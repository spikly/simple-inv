<?php

/**
 * Form results survive the redirect that follows a successful write, so a
 * refresh never resubmits the form.
 */

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Store a message created by successMessage()/errorMessage() for the next
 * request, then send the browser to $url.
 */
function redirectWith(string $url, array $message): void
{
    startSession();
    $_SESSION['flash'] = $message;

    redirect($url);
}

function redirect(string $url): void
{
    // Output is buffered in bootstrap.php so this is the normal path. If a
    // page has somehow already flushed, send the browser on with markup
    // rather than failing outright.
    if (headers_sent()) {
        template('redirect', compact('url'));
        exit;
    }

    // Drop the half rendered page; the redirected request draws its own.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Location: ' . $url);
    exit;
}

/**
 * The message stored by the previous request, removed as it is read.
 */
function takeFlash()
{
    startSession();

    if (!isset($_SESSION['flash'])) {
        return false;
    }

    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $message;
}

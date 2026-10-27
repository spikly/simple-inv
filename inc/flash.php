<?php

function startSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function redirectWith(string $url, array $message): void
{
    startSession();
    $_SESSION['flash'] = $message;

    redirect($url);
}

function redirect(string $url): void
{
    // Output is buffered in bootstrap.php, so this is only a fallback.
    if (headers_sent()) {
        template('redirect', compact('url'));
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Location: ' . $url);
    exit;
}

/** Removed as it is read. Returns false when there is none. */
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

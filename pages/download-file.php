<?php

/**
 * Served here rather than linked, to keep the uploaded name and to force a download
 * for anything but safe inline types, since a rendered file could carry script.
 * Sends its own headers, so index.php includes it before any markup.
 */

$file = fetchItemFile(queryId('file_id'));

// Only names this app generates, whatever is in the database.
$stored = $file ? (string)$file['file_stored_name'] : '';
$path = preg_match(ITEM_FILE_NAME_PATTERN, $stored) ? itemFilePath($stored) : '';

if (!$path || !is_file($path)) {
    http_response_code(404);

    header('Content-Type: text/plain; charset=utf-8');
    echo 'That file is no longer here.';

    return;
}

// Sanitised so it cannot break the header; filename* carries the real name.
$name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$file['file_name']);
$name = trim($name) !== '' ? $name : 'download';

header('Content-Type: ' . itemFileMime($stored));
header('Content-Disposition: ' . (itemFileIsInline($stored) ? 'inline' : 'attachment')
    . '; filename="' . $name . '"'
    . "; filename*=UTF-8''" . rawurlencode((string)$file['file_name']));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

// Drop output buffering so large files stream rather than sit in memory.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);

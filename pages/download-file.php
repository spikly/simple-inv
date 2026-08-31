<?php

/**
 * Hand back one of an item's documents.
 *
 * Files are served through here rather than linked to directly so they keep
 * the name they were uploaded with: on disk they are all
 * "9f8a7b6c5d4e3f21.pdf", which is no use to anyone saving one.
 *
 * It also settles how the browser treats each one. A picture or a PDF is shown
 * in a tab; anything else is sent as a download, because a file the browser
 * would render is a file that could carry a script, and a download cannot run.
 *
 * Sends its own headers, so index.php includes this before any markup.
 */

$file = fetchItemFile(queryId('file_id'));

// The stored name is checked against the pattern the app generates, so nothing
// but a file this app wrote can be read, whatever is in the database.
$stored = $file ? (string)$file['file_stored_name'] : '';
$path = preg_match(ITEM_FILE_NAME_PATTERN, $stored) ? itemFilePath($stored) : '';

if (!$path || !is_file($path)) {
    http_response_code(404);

    header('Content-Type: text/plain; charset=utf-8');
    echo 'That file is no longer here.';

    return;
}

// A name for the saved copy that cannot break out of the header it sits in.
// filename* carries the real one for anything that understands it, and the
// plain filename is the stripped-back fallback for anything that does not.
$name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$file['file_name']);
$name = trim($name) !== '' ? $name : 'download';

header('Content-Type: ' . itemFileMime($stored));
header('Content-Disposition: ' . (itemFileIsInline($stored) ? 'inline' : 'attachment')
    . '; filename="' . $name . '"'
    . "; filename*=UTF-8''" . rawurlencode((string)$file['file_name']));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

// bootstrap.php buffers the page so a redirect can still send headers. Nothing
// is waiting here, and a 16MB document should not be held in memory to be
// handed over in one piece, so the buffer is dropped and the file streamed.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);

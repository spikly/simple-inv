<?php

/**
 * Served through here rather than linked to directly, so a file keeps the name
 * it was uploaded with and so the browser is told how to treat it: a picture
 * or a PDF in a tab, everything else as a download, since a file the browser
 * would render could carry a script and a download cannot run.
 *
 * Sends its own headers, so index.php includes this before any markup.
 */

$file = fetchItemFile(queryId('file_id'));

// Checked against the pattern the app generates, so nothing but a file this
// app wrote can be read, whatever is in the database.
$stored = $file ? (string)$file['file_stored_name'] : '';
$path = preg_match(ITEM_FILE_NAME_PATTERN, $stored) ? itemFilePath($stored) : '';

if (!$path || !is_file($path)) {
    http_response_code(404);

    header('Content-Type: text/plain; charset=utf-8');
    echo 'That file is no longer here.';

    return;
}

// A name that cannot break out of the header. filename* carries the real one;
// the plain filename is the fallback for anything that cannot read it.
$name = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string)$file['file_name']);
$name = trim($name) !== '' ? $name : 'download';

header('Content-Type: ' . itemFileMime($stored));
header('Content-Disposition: ' . (itemFileIsInline($stored) ? 'inline' : 'attachment')
    . '; filename="' . $name . '"'
    . "; filename*=UTF-8''" . rawurlencode((string)$file['file_name']));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

// Nothing is waiting in the buffer, and a large document should not be held in
// memory to be handed over in one piece, so it is dropped and the file streamed.
while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($path);

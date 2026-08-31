<?php

/**
 * Item photos and item attachments.
 *
 * Both are stored with a generated name, so nothing a browser sends is ever
 * used as a path: photos under assets/uploads/items/, attachments under
 * assets/uploads/files/. The folder above them turns PHP off and sends
 * X-Content-Type-Options, see assets/uploads/.htaccess.
 *
 */

const UPLOAD_MAX_BYTES = 8388608; // 8MB

/**
 * Longest side an item photo is stored at. A photo is scaled down so neither
 * side is larger than this, keeping its proportions, so a 1200x900 shot ends
 * up 300x225 rather than being squashed into a square.
 */
const UPLOAD_MAX_DIMENSION = 300;

/** Image types accepted, mapped to the extension they are saved with. */
const UPLOAD_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

function uploadPath(string $file = ''): string
{
    return __DIR__ . '/../assets/uploads/items/' . $file;
}

/** Web path for an item photo, or null when there is not one. */
function itemImageUrl(?string $file): ?string
{
    return $file ? 'assets/uploads/items/' . rawurlencode($file) : null;
}

/**
 * Save one uploaded photo.
 *
 * Returns ['name' => storedFilename], ['name' => null] when no file was
 * chosen, or ['error' => message] when the upload is not usable.
 */
function storeItemImage(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['name' => null];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The photo could not be uploaded. It may be larger than the server allows.'];
    }

    if ($upload['size'] > UPLOAD_MAX_BYTES) {
        return ['error' => 'The photo must be 8MB or smaller.'];
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        return ['error' => 'The photo could not be read.'];
    }

    // Trust what the file actually contains, never its name or reported type.
    $details = @getimagesize($upload['tmp_name']);

    if (!$details || !isset(UPLOAD_TYPES[$details[2]])) {
        return ['error' => 'That file is not a JPEG, PNG, GIF or WebP image.'];
    }

    if (!is_dir(uploadPath()) && !@mkdir(uploadPath(), 0775, true)) {
        return ['error' => 'The upload folder assets/uploads/items/ could not be created.'];
    }

    if (!is_writable(uploadPath())) {
        return ['error' => 'The upload folder assets/uploads/items/ is not writable.'];
    }

    $name = bin2hex(random_bytes(8)) . '.' . UPLOAD_TYPES[$details[2]];

    if (!move_uploaded_file($upload['tmp_name'], uploadPath($name))) {
        return ['error' => 'The photo could not be saved.'];
    }

    shrinkImage(uploadPath($name), $details[2], $details[0], $details[1]);

    return ['name' => $name];
}

/**
 * Scale a stored photo down to UPLOAD_MAX_DIMENSION and straighten it if the
 * camera recorded it sideways. The file is left alone when it is already small
 * enough and the right way up, so nothing is re-encoded for no reason.
 */
function shrinkImage(string $path, int $type, int $width, int $height): void
{
    $loaders = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    $rotation = ($type === IMAGETYPE_JPEG) ? exifRotation($path) : 0;

    if (max($width, $height) <= UPLOAD_MAX_DIMENSION && $rotation === 0) {
        return;
    }

    // Reading a very large photo can take more memory than PHP is allowed.
    // Keeping the original at full size is better than failing the upload.
    if (!isset($loaders[$type]) || !function_exists($loaders[$type]) || !imageFitsInMemory($width, $height)) {
        return;
    }

    $image = @$loaders[$type]($path);

    if (!$image) {
        return;
    }

    if ($rotation !== 0) {
        $rotated = @imagerotate($image, $rotation, 0);

        if ($rotated) {
            imagedestroy($image);
            $image = $rotated;
        }
    }

    $image = scaleToFit($image, UPLOAD_MAX_DIMENSION, $type);

    saveImage($image, $path, $type);
    imagedestroy($image);
}

/** Degrees to rotate a JPEG by, from the orientation its camera recorded. */
function exifRotation(string $path): int
{
    if (!function_exists('exif_read_data')) {
        return 0;
    }

    $exif = @exif_read_data($path);

    switch ($exif['Orientation'] ?? 1) {
        case 3: return 180;
        case 6: return -90;
        case 8: return 90;
        default: return 0;
    }
}

/** Resample an image so its longest side is at most $max. */
function scaleToFit($image, int $max, int $type)
{
    $width = imagesx($image);
    $height = imagesy($image);
    $longest = max($width, $height);

    if ($longest <= $max) {
        return $image;
    }

    $scale = $max / $longest;
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));

    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // JPEG has no transparency; the rest keep theirs.
    if ($type !== IMAGETYPE_JPEG) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
    }

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    return $resized;
}

function saveImage($image, string $path, int $type): bool
{
    switch ($type) {
        case IMAGETYPE_JPEG: return imagejpeg($image, $path, 85);
        case IMAGETYPE_PNG:  return imagepng($image, $path, 6);
        case IMAGETYPE_GIF:  return imagegif($image, $path);
        case IMAGETYPE_WEBP: return function_exists('imagewebp') && imagewebp($image, $path, 85);
        default:             return false;
    }
}

/** Whether PHP has room to hold this image, plus the copy made from it. */
function imageFitsInMemory(int $width, int $height): bool
{
    $limit = memoryLimitBytes();

    if ($limit <= 0) {
        return true;
    }

    // Four bytes a pixel for the source, plus headroom for the resized copy.
    $needed = ($width * $height * 4) + 8388608;

    return (memory_get_usage(true) + $needed) < $limit;
}

function memoryLimitBytes(): int
{
    $limit = trim((string)ini_get('memory_limit'));

    if ($limit === '' || $limit === '-1') {
        return 0;
    }

    $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
    $suffix = strtolower(substr($limit, -1));

    return (int)$limit * ($units[$suffix] ?? 1);
}

/** Remove a stored photo, ignoring one that has already gone. */
function deleteItemImage(?string $file): void
{
    // Guard against anything that is not one of our generated names.
    if ($file && preg_match('/^[0-9a-f]{16}\.(jpg|png|gif|webp)$/', $file) && is_file(uploadPath($file))) {
        @unlink(uploadPath($file));
    }
}

/**
 * Copy a stored photo under a new name, so a duplicated item owns its own
 * file. Returns the new name, or null when there was nothing to copy.
 */
function copyItemImage(?string $file): ?string
{
    if (!$file || !preg_match('/^[0-9a-f]{16}\.(jpg|png|gif|webp)$/', $file, $matches)) {
        return null;
    }

    if (!is_file(uploadPath($file))) {
        return null;
    }

    $name = bin2hex(random_bytes(8)) . '.' . $matches[1];

    return @copy(uploadPath($file), uploadPath($name)) ? $name : null;
}

/*
 * Item attachments: spec sheets, manuals, drawings and any other document or
 * extra picture kept against an item.
 *
 * Unlike the photo above, an attachment is never re-encoded or resized. It is
 * the document you uploaded, handed back byte for byte, so a drawing stays
 * readable and a PDF stays the PDF the manufacturer published.
 */

/** Largest attachment accepted. PHP's own upload_max_filesize may be lower. */
const ITEM_FILE_MAX_BYTES = 16777216; // 16MB

/**
 * What may be uploaded, as the stored extension => the type it is served as.
 *
 * This list is the whole of what decides the extension a file is saved with,
 * so nothing the webserver would execute can ever be written: an upload whose
 * extension is not one of these keys is turned away rather than corrected.
 */
const ITEM_FILE_TYPES = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
    'rtf'  => 'application/rtf',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'zip'  => 'application/zip',
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

/** Extensions meaning one of the above under another name. */
const ITEM_FILE_ALIASES = ['jpeg' => 'jpg'];

/**
 * The types shown in the browser rather than downloaded. A picture and a PDF
 * are things you look at; everything else is a file you open in its own
 * program, so it is sent as a download.
 */
const ITEM_FILE_INLINE = ['pdf', 'jpg', 'png', 'gif', 'webp'];

/** The pattern every generated attachment name matches. */
const ITEM_FILE_NAME_PATTERN = '/^[0-9a-f]{16}\.[a-z0-9]{1,5}$/';

function itemFilePath(string $file = ''): string
{
    return __DIR__ . '/../assets/uploads/files/' . $file;
}

/** A readable list of what may be uploaded, for the form and its errors. */
function itemFileTypeList(): string
{
    return strtoupper(implode(', ', array_keys(ITEM_FILE_TYPES)));
}

/**
 * Save one uploaded attachment.
 *
 * Returns ['name' => storedName, 'original' => whatItWasCalled, 'size' => bytes],
 * ['name' => null] when nothing was chosen, or ['error' => message].
 */
function storeItemFile(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['name' => null];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The file could not be uploaded. It may be larger than the server allows.'];
    }

    if ($upload['size'] > ITEM_FILE_MAX_BYTES) {
        return ['error' => 'Each file must be 16MB or smaller.'];
    }

    if ($upload['size'] === 0) {
        return ['error' => 'That file is empty.'];
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        return ['error' => 'The file could not be read.'];
    }

    // Only ever read for its extension; the name itself is never used as a
    // path, and basename() keeps a directory out of what is shown.
    $original = basename(str_replace('\\', '/', (string)$upload['name']));
    $extension = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
    $extension = ITEM_FILE_ALIASES[$extension] ?? $extension;

    if (!isset(ITEM_FILE_TYPES[$extension])) {
        return ['error' => 'A ' . ($extension === '' ? 'file with no extension' : strtoupper($extension) . ' file')
            . ' cannot be attached. Accepted types are ' . itemFileTypeList() . '.'];
    }

    if (!is_dir(itemFilePath()) && !@mkdir(itemFilePath(), 0775, true)) {
        return ['error' => 'The upload folder assets/uploads/files/ could not be created.'];
    }

    if (!is_writable(itemFilePath())) {
        return ['error' => 'The upload folder assets/uploads/files/ is not writable.'];
    }

    $name = bin2hex(random_bytes(8)) . '.' . $extension;

    if (!move_uploaded_file($upload['tmp_name'], itemFilePath($name))) {
        return ['error' => 'The file could not be saved.'];
    }

    return [
        'name'     => $name,
        // Kept only to show and to name the download; 255 is what the column
        // holds, and mb_substr so a long name is not cut through a character.
        'original' => mb_substr($original, 0, 255),
        'size'     => (int)$upload['size'],
    ];
}

/**
 * The $_FILES entry for each file chosen in one multiple upload control, as
 * the single-file shape the rest of this file works in.
 *
 * PHP turns <input name="x[]" multiple> inside out, giving one array per
 * property rather than one entry per file, which nothing else here expects.
 */
function uploadedFileList(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $list = [];

    foreach (array_keys($files['name']) as $index) {
        // A control submitted with nothing chosen still sends one empty entry.
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $list[] = [
            'name'     => $files['name'][$index] ?? '',
            'type'     => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$index] ?? 0,
        ];
    }

    return $list;
}

/** The type a stored attachment is served as, from the extension it was saved with. */
function itemFileMime(string $stored): string
{
    $extension = strtolower((string)pathinfo($stored, PATHINFO_EXTENSION));

    return ITEM_FILE_TYPES[$extension] ?? 'application/octet-stream';
}

/** Whether a stored attachment is shown in the browser rather than downloaded. */
function itemFileIsInline(string $stored): bool
{
    return in_array(strtolower((string)pathinfo($stored, PATHINFO_EXTENSION)), ITEM_FILE_INLINE, true);
}

/** Remove a stored attachment, ignoring one that has already gone. */
function deleteItemFile(?string $stored): void
{
    // Guard against anything that is not one of our generated names.
    if ($stored && preg_match(ITEM_FILE_NAME_PATTERN, $stored) && is_file(itemFilePath($stored))) {
        @unlink(itemFilePath($stored));
    }
}

/**
 * Copy a stored attachment under a new name, so a duplicated item owns its own
 * files. Returns the new name, or null when there was nothing to copy.
 */
function copyItemFile(?string $stored): ?string
{
    if (!$stored || !preg_match(ITEM_FILE_NAME_PATTERN, $stored) || !is_file(itemFilePath($stored))) {
        return null;
    }

    $name = bin2hex(random_bytes(8)) . '.' . strtolower((string)pathinfo($stored, PATHINFO_EXTENSION));

    return @copy(itemFilePath($stored), itemFilePath($name)) ? $name : null;
}

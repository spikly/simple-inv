<?php

/**
 * Item photos and item attachments. Both are stored with a generated name, so
 * nothing a browser sends is ever used as a path: photos under
 * assets/uploads/items/, attachments under assets/uploads/files/. The folder
 * above them turns PHP off, see assets/uploads/.htaccess.
 */

const UPLOAD_MAX_BYTES = 8388608; // 8MB

/** Longest side a photo is stored at, keeping its proportions. */
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

/** Returns ['name' => filename], ['name' => null] if none chosen, or ['error' => message]. */
function storeItemImage(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['name' => null];
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The photo could not be uploaded. It may be larger than the server allows.'];
    }

    if ($upload['size'] > UPLOAD_MAX_BYTES) {
        return ['error' => 'The photo must be ' . formatFileSize(UPLOAD_MAX_BYTES) . ' or smaller.'];
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
 * Scale down to UPLOAD_MAX_DIMENSION and straighten a sideways photo. One
 * already small enough and upright is left alone rather than re-encoded.
 */
function shrinkImage(string $path, int $type, int $width, int $height): void
{
    $rotation = ($type === IMAGETYPE_JPEG) ? exifRotation($path) : 0;

    if (max($width, $height) <= UPLOAD_MAX_DIMENSION && $rotation === 0) {
        return;
    }

    // Keeping the original at full size beats failing the upload on memory.
    if (!imageFitsInMemory($width, $height)) {
        return;
    }

    $image = loadImage($path, $type);

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

/** The image held in a file, or null when PHP cannot read that type. */
function loadImage(string $path, int $type)
{
    $loaders = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG  => 'imagecreatefrompng',
        IMAGETYPE_GIF  => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
    ];

    if (!isset($loaders[$type]) || !function_exists($loaders[$type])) {
        return null;
    }

    return @$loaders[$type]($path) ?: null;
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

/** A duplicate owns its own file. Null when there was nothing to copy. */
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
 * Item attachments: spec sheets, manuals, drawings and extra pictures.
 *
 * Unlike the photo above, an attachment is never re-encoded or resized, so a
 * drawing stays readable and a PDF stays the one the manufacturer published.
 */

/** Largest attachment accepted. PHP's own upload_max_filesize may be lower. */
const ITEM_FILE_MAX_BYTES = 16777216; // 16MB

/**
 * Stored extension => the type it is served as. This list is the whole of what
 * decides the extension a file is saved with, so nothing the webserver would
 * execute can be written: anything else is turned away, never corrected.
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

/** Shown in the browser rather than downloaded. Everything else is a download. */
const ITEM_FILE_INLINE = ['pdf', 'jpg', 'png', 'gif', 'webp'];

/**
 * The attachments a thumbnail can be made from, mapped to the image type the
 * extension promises. Anything else is shown as a document icon instead.
 */
const ITEM_FILE_IMAGE_TYPES = [
    'jpg'  => IMAGETYPE_JPEG,
    'png'  => IMAGETYPE_PNG,
    'gif'  => IMAGETYPE_GIF,
    'webp' => IMAGETYPE_WEBP,
];

/** Longest side a thumbnail is kept at, matching .file-thumb in the stylesheet. */
const ITEM_FILE_THUMB_DIMENSION = 50;

/** The pattern every generated attachment name matches. */
const ITEM_FILE_NAME_PATTERN = '/^[0-9a-f]{16}\.[a-z0-9]{1,5}$/';

function itemFilePath(string $file = ''): string
{
    return __DIR__ . '/../assets/uploads/files/' . $file;
}

/** Thumbnails sit under the attachments themselves, in their own folder. */
function itemFileThumbPath(string $file = ''): string
{
    return itemFilePath('thumbs/' . $file);
}

function itemFileTypeList(): string
{
    return strtoupper(implode(', ', array_keys(ITEM_FILE_TYPES)));
}

/**
 * Returns ['name' => stored, 'original' => as uploaded, 'size' => bytes],
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
        return ['error' => 'Each file must be ' . formatFileSize(ITEM_FILE_MAX_BYTES) . ' or smaller.'];
    }

    if ($upload['size'] === 0) {
        return ['error' => 'That file is empty.'];
    }

    if (!is_uploaded_file($upload['tmp_name'])) {
        return ['error' => 'The file could not be read.'];
    }

    // Read only for its extension; basename() keeps a directory out of it.
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
        // mb_substr, so a long name is not cut through a character.
        'original' => mb_substr($original, 0, 255),
        'size'     => (int)$upload['size'],
    ];
}

/**
 * PHP turns <input name="x[]" multiple> inside out, giving one array per
 * property rather than one entry per file. This puts it back.
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

/** The extension a stored attachment was saved with, which decides its type. */
function itemFileExtension(string $stored): string
{
    return strtolower((string)pathinfo($stored, PATHINFO_EXTENSION));
}

/** The type a stored attachment is served as, from the extension it was saved with. */
function itemFileMime(string $stored): string
{
    return ITEM_FILE_TYPES[itemFileExtension($stored)] ?? 'application/octet-stream';
}

/** Whether a stored attachment is shown in the browser rather than downloaded. */
function itemFileIsInline(string $stored): bool
{
    return in_array(itemFileExtension($stored), ITEM_FILE_INLINE, true);
}

/** Whether a stored attachment is a picture, so it can be shown rather than named. */
function itemFileIsImage(string $stored): bool
{
    return isset(ITEM_FILE_IMAGE_TYPES[itemFileExtension($stored)]);
}

/**
 * Web path to a picture attachment's small copy, or null when there is not one
 * to show: not a picture, or an image PHP could not read. Made on first use
 * rather than at upload, so files attached before this existed get one too.
 */
function itemFileThumbUrl(string $stored): ?string
{
    // The generated name only, so nothing but a file this app wrote is read.
    if (!preg_match(ITEM_FILE_NAME_PATTERN, $stored) || !itemFileIsImage($stored)) {
        return null;
    }

    if (!is_file(itemFileThumbPath($stored)) && !makeItemFileThumb($stored)) {
        return null;
    }

    return 'assets/uploads/files/thumbs/' . rawurlencode($stored);
}

/**
 * Write the small copy of a picture attachment, which is what is put on the
 * page: the original is left alone, and a 4MB drawing is never sent to fill a
 * 50px square. False when it could not be made.
 */
function makeItemFileThumb(string $stored): bool
{
    $source = itemFilePath($stored);
    $details = is_file($source) ? @getimagesize($source) : false;

    // The extension it was saved with is the type it is written back as, so a
    // file whose contents disagree with its name is left without a thumbnail.
    if (!$details || $details[2] !== (ITEM_FILE_IMAGE_TYPES[itemFileExtension($stored)] ?? 0)) {
        return false;
    }

    // Going without a thumbnail beats taking the page down over memory.
    if (!imageFitsInMemory($details[0], $details[1])) {
        return false;
    }

    if (!is_dir(itemFileThumbPath()) && !@mkdir(itemFileThumbPath(), 0775, true)) {
        return false;
    }

    $image = loadImage($source, $details[2]);

    if (!$image) {
        return false;
    }

    $image = scaleToFit($image, ITEM_FILE_THUMB_DIMENSION, $details[2]);
    $saved = saveImage($image, itemFileThumbPath($stored), $details[2]);
    imagedestroy($image);

    return $saved;
}

/** Remove a stored attachment and its thumbnail, ignoring one already gone. */
function deleteItemFile(?string $stored): void
{
    // Guard against anything that is not one of our generated names.
    if (!$stored || !preg_match(ITEM_FILE_NAME_PATTERN, $stored)) {
        return;
    }

    foreach ([itemFilePath($stored), itemFileThumbPath($stored)] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** A duplicate owns its own files. Null when there was nothing to copy. */
function copyItemFile(?string $stored): ?string
{
    if (!$stored || !preg_match(ITEM_FILE_NAME_PATTERN, $stored) || !is_file(itemFilePath($stored))) {
        return null;
    }

    // The copy makes its own thumbnail when it is first shown.
    $name = bin2hex(random_bytes(8)) . '.' . itemFileExtension($stored);

    return @copy(itemFilePath($stored), itemFilePath($name)) ? $name : null;
}

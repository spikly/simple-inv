<?php

/**
 * Item photos. Files are stored under assets/uploads/items/ with a generated
 * name, so nothing a browser sends is ever used as a path.
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

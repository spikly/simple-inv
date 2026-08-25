<?php

/**
 * Item photos. Files are stored under assets/uploads/items/ with a generated
 * name, so nothing a browser sends is ever used as a path.
 */

const UPLOAD_MAX_BYTES = 8388608; // 8MB

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

    return ['name' => $name];
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

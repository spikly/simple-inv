<?php

/** Reads the columns the export writes, so an export can be edited and read back. */

/** Lowercased heading => whether a value is required. */
const IMPORT_COLUMNS = [
    'name'         => true,
    'part no'      => false,
    'colour'       => false,
    'product url'  => false,
    'manufacturer' => true,
    'supplier'     => false,
    'categories'   => true,
    'location'     => true,
    'status'       => true,
    'type'         => false,
    'quantity'     => false,
    'min quantity' => false,
    'unit'         => false,
    'notes'        => false,
];

/** Headings an older export used, read as the column that replaced them. */
const IMPORT_COLUMN_ALIASES = [
    'brand' => 'manufacturer',
    'category' => 'categories',
];

/**
 * A spreadsheet runs a cell starting with one of these as a formula. Export
 * puts a ' before them, which spreadsheets hide and import takes off again.
 */
const CSV_FORMULA_START = '/^[=+\-@\t\r]/';

function csvSafeCell($value)
{
    return (is_string($value) && preg_match(CSV_FORMULA_START, $value)) ? "'" . $value : $value;
}

const IMPORT_TAXONOMY_COLUMNS = [
    'brand'    => 'manufacturer',
    'supplier' => 'supplier',
    'location' => 'location',
    'status'   => 'status',
];

/*
 * Reviewed rows sit in the session between preview and confirm, so can outlive
 * the code that made them. Bump IMPORT_ROW_VERSION when importRow() changes.
 */

const IMPORT_SESSION_KEY = 'import_rows';

const IMPORT_ROW_VERSION = 4;

function storeImportPreview(array $rows): void
{
    $_SESSION[IMPORT_SESSION_KEY] = ['version' => IMPORT_ROW_VERSION, 'rows' => $rows];
}

function clearImportPreview(): void
{
    unset($_SESSION[IMPORT_SESSION_KEY]);
}

/** Returns ['rows' => [...], 'stale' => bool]. An older version is cleared as stale. */
function storedImportPreview(): array
{
    $stored = $_SESSION[IMPORT_SESSION_KEY] ?? null;

    if ($stored === null) {
        return ['rows' => [], 'stale' => false];
    }

    if (!is_array($stored) || ($stored['version'] ?? null) !== IMPORT_ROW_VERSION) {
        clearImportPreview();

        return ['rows' => [], 'stale' => true];
    }

    return ['rows' => $stored['rows'], 'stale' => false];
}

/** Returns ['error' => message] or ['rows' => [...]]; each row may carry its own error. */
function parseItemCsv(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return ['error' => 'Choose a CSV file to import.'];
    }

    $contents = file_get_contents($upload['tmp_name']);

    if ($contents === false) {
        return ['error' => 'That file could not be read.'];
    }

    // Excel's "CSV UTF-8" starts with a byte order mark, and its plain "CSV"
    // is Windows-1252, which would be stored as garbled or rejected text.
    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    } elseif (!mb_check_encoding($contents, 'UTF-8')) {
        $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $contents);
    rewind($handle);

    $heading = fgetcsv($handle, 0, ',', '"', '\\');

    if (!$heading) {
        return ['error' => 'That file is empty.'];
    }

    // Column order and heading case do not matter.
    $columns = [];

    foreach ($heading as $index => $label) {
        $label = strtolower(trim((string)$label));
        $columns[IMPORT_COLUMN_ALIASES[$label] ?? $label] = $index;
    }

    foreach (IMPORT_COLUMNS as $column => $required) {
        if ($required && !isset($columns[$column])) {
            return ['error' => 'The file needs a "' . ucwords($column) . '" column.'];
        }
    }

    $rows = [];
    $known = importKnownNames();
    $existing = importExistingItems();
    $line = 1;

    while (($record = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $line++;

        // A blank line comes back as [null].
        if (count(array_filter($record, fn ($value) => trim((string)$value) !== '')) === 0) {
            continue;
        }

        $row = importRow($record, $columns, $known, $line);

        if ($row['error'] === null) {
            $key = importItemKey($row['name'], $row['manufacturer']);

            if (isset($existing[$key])) {
                $row['duplicate'] = ($existing[$key] === 0)
                    ? 'An item with this name and manufacturer already exists.'
                    : 'Same name and manufacturer as line ' . $existing[$key] . '.';
            } else {
                $existing[$key] = $line;
            }

            // A category this file creates files this row's kind, so later
            // rows are checked against it as if it already existed.
            foreach ($row['categories'] as $category) {
                $known['category'][mb_strtolower($category)] ??= $row['type'];
            }
        }

        $rows[] = $row;
    }

    fclose($handle);

    if (!$rows) {
        return ['error' => 'That file has headings but no rows.'];
    }

    return ['rows' => $rows];
}

/**
 * Existing names, lowercased. Categories map to the kind they file. Locations are
 * full paths, so a sub-location is recognised rather than created again.
 */
function importKnownNames(): array
{
    $known = ['category' => []];

    foreach (array_keys(IMPORT_TAXONOMY_COLUMNS) as $key) {
        $known[$key] = array_map('mb_strtolower', array_map('strval', taxonomyOptions($key)));
    }

    foreach (dbAll('SELECT cat_name, cat_type FROM inv_categories') as $row) {
        $known['category'][mb_strtolower($row['cat_name'])] = $row['cat_type'];
    }

    return $known;
}

/** Matches an imported row to an item, ignoring case and spacing at the ends. */
function importItemKey(string $name, string $manufacturer): string
{
    return mb_strtolower(trim($name)) . "\0" . mb_strtolower(trim($manufacturer));
}

/** importItemKey() => 0, for every item already held. */
function importExistingItems(): array
{
    $existing = [];

    foreach (dbAll(
        'SELECT i.item_name, b.brand_name
         FROM inv_items i
         LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id'
    ) as $item) {
        $existing[importItemKey((string)$item['item_name'], (string)$item['brand_name'])] = 0;
    }

    return $existing;
}

/** Changing which keys a row holds means bumping IMPORT_ROW_VERSION. */
function importRow(array $record, array $columns, array $known, int $line): array
{
    $value = function (string $column) use ($record, $columns) {
        $index = $columns[$column] ?? null;

        $cell = ($index === null) ? '' : trim((string)($record[$index] ?? ''));

        // Undo csvSafeCell().
        return (str_starts_with($cell, "'") && preg_match(CSV_FORMULA_START, substr($cell, 1)))
            ? substr($cell, 1)
            : $cell;
    };

    $categories = array_values(array_filter(array_map('trim', explode('|', $value('categories'))), 'strlen'));

    $row = [
        'line'         => $line,
        'name'         => $value('name'),
        'part_no'      => $value('part no'),
        'colour'       => $value('colour'),
        'product_url'  => $value('product url'),
        'manufacturer' => $value('manufacturer'),
        'supplier'     => $value('supplier'),
        'categories'   => $categories,
        'location'     => $value('location'),
        'status'       => $value('status'),
        'type'         => itemType($value('type')),
        'quantity'     => $value('quantity'),
        'min_quantity' => $value('min quantity'),
        'unit'         => $value('unit'),
        'notes'        => $value('notes'),
        'creates'      => [],
        'duplicate'    => null,
        'error'        => null,
    ];

    foreach (IMPORT_COLUMNS as $column => $required) {
        $key = str_replace(' ', '_', $column);

        if ($required && $key !== 'categories' && $row[$key] === '') {
            $row['error'] = ucwords($column) . ' is missing.';

            return $row;
        }
    }

    if (!$categories) {
        $row['error'] = 'Categories is missing.';

        return $row;
    }

    foreach (['quantity' => 'Quantity', 'min_quantity' => 'Min Quantity'] as $key => $label) {
        if ($row[$key] !== '' && (!ctype_digit($row[$key]) || (int)$row[$key] > MAX_ITEM_QUANTITY)) {
            $row['error'] = $label . ' "' . $row[$key] . '" is not a whole number, 0 or more.';

            return $row;
        }
    }

    // Column widths, so an overlong value is an error here rather than a failed import.
    $limits = ['colour' => 50, 'product_url' => 255, 'manufacturer' => 191, 'supplier' => 191, 'status' => 191];

    foreach ($limits as $key => $limit) {
        if (mb_strlen($row[$key]) > $limit) {
            $row['error'] = ucwords(str_replace('_', ' ', $key)) . ' is longer than ' . $limit . ' characters.';

            return $row;
        }
    }

    foreach (array_merge(explode(LOCATION_PATH_SEPARATOR, $row['location']), $categories) as $name) {
        if (mb_strlen(trim($name)) > 191) {
            $row['error'] = '"' . mb_substr(trim($name), 0, 40) . '..." is longer than 191 characters.';

            return $row;
        }
    }

    if (!isWebUrl($row['product_url'] !== '' ? $row['product_url'] : null)) {
        $row['error'] = 'Product URL "' . $row['product_url']
            . '" is not a web address starting http:// or https://.';

        return $row;
    }

    if ($row['unit'] !== '' && resolveUnitId($row['unit']) === null) {
        $row['error'] = 'Unit "' . $row['unit'] . '" is not one of the measurement units.';

        return $row;
    }

    // For the preview to list as created.
    foreach (IMPORT_TAXONOMY_COLUMNS as $key => $column) {
        $name = $row[$column];

        if ($name !== '' && !in_array(mb_strtolower($name), $known[$key], true)) {
            $row['creates'][] = taxonomy($key)['label'] . ': ' . $name;
        }
    }

    foreach ($categories as $category) {
        $files = $known['category'][mb_strtolower($category)] ?? null;

        if ($files === null) {
            $row['creates'][] = ITEM_TYPES[$row['type']] . ' Category: ' . $category;
            continue;
        }

        if ($files !== $row['type']) {
            $row['error'] = 'Category "' . $category . '" files '
                . strtolower(ITEM_TYPE_PLURALS[$files]) . ', but this row is a '
                . strtolower(ITEM_TYPES[$row['type']]) . '.';

            return $row;
        }
    }

    return $row;
}

/** Matches a symbol or label. Blank gives pieces; no match gives null. */
function resolveUnitId(?string $value): ?int
{
    $value = trim((string)$value);

    if ($value === '') {
        return pieceUnitId();
    }

    $id = dbValue(
        'SELECT unit_id FROM inv_measurement_units WHERE unit_symbol = :symbol OR unit_label = :label LIMIT 1',
        ['symbol' => $value, 'label' => $value]
    );

    return ($id === null) ? null : (int)$id;
}

/** $extra is written onto anything created, eg the kind a category files. */
function resolveTaxonomyId(string $key, string $name, bool $create, array $extra = []): ?int
{
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    $tax = taxonomy($key);

    $id = dbValue(
        'SELECT ' . $tax['id'] . ' FROM ' . $tax['table']
        . ' WHERE ' . taxonomyNameField($tax) . ' = :name LIMIT 1',
        ['name' => $name]
    );

    if ($id !== null) {
        return (int)$id;
    }

    if (!$create) {
        return null;
    }

    $result = taxonomyInsert($key, $extra + [taxonomyNameField($tax) => $name]);

    return $result['success'] ? (int)$result['newId'] : null;
}

/**
 * "Tool Chest > Drawer 1" is the drawer in the chest. Nesting is one level, so
 * anything further is part of the sub-location's name. One name is top level.
 */
function resolveLocationId(string $value, bool $create): ?int
{
    $names = array_values(array_filter(
        array_map('trim', explode(LOCATION_PATH_SEPARATOR, $value)),
        'strlen'
    ));

    if (!$names) {
        return null;
    }

    $parentId = locationIdByName(array_shift($names), null, $create);

    if (!$names || $parentId === null) {
        return $parentId;
    }

    return locationIdByName(implode(LOCATION_PATH_SEPARATOR, $names), $parentId, $create);
}

function locationIdByName(string $name, ?int $parentId, bool $create): ?int
{
    $id = dbValue(
        'SELECT loc_id FROM inv_locations WHERE loc_name = :name'
        . ' AND loc_parent_id ' . ($parentId === null ? 'IS NULL' : '= :parent')
        . ' LIMIT 1',
        ($parentId === null) ? ['name' => $name] : ['name' => $name, 'parent' => $parentId]
    );

    if ($id !== null) {
        return (int)$id;
    }

    if (!$create) {
        return null;
    }

    $result = taxonomyInsert('location', ['loc_name' => $name, 'loc_parent_id' => $parentId]);

    return $result['success'] ? (int)$result['newId'] : null;
}

/**
 * Whether a category the row names files the other kind. The preview checks
 * this too, but the database decides which names match.
 */
function importCategoryKindClash(array $row): bool
{
    foreach ($row['categories'] as $category) {
        $files = dbValue('SELECT cat_type FROM inv_categories WHERE cat_name = :name LIMIT 1', ['name' => $category]);

        if ($files !== null && $files !== $row['type']) {
            return true;
        }
    }

    return false;
}

/** Rows carrying an error are skipped. One transaction, so a failure leaves nothing. */
function importItemRows(array $rows): array
{
    $imported = 0;
    $skipped = 0;

    dbTransaction(function () use ($rows, &$imported, &$skipped) {
        foreach ($rows as $row) {
            if (!empty($row['error']) || importCategoryKindClash($row)) {
                $skipped++;
                continue;
            }

            $ids = [];

            foreach (IMPORT_TAXONOMY_COLUMNS as $key => $column) {
                // Locations nest, so their column can name two of them.
                $ids[$key] = ($key === 'location')
                    ? resolveLocationId($row[$column], true)
                    : resolveTaxonomyId($key, $row[$column], true);
            }

            // Once, so the history records the figure written to the item.
            $quantity = ($row['type'] === 'tool' || $row['quantity'] === '')
                ? 1
                : (int)$row['quantity'];

            $itemId = dbInsert(
                'INSERT INTO inv_items
                    (item_name, item_part_no, item_colour, item_product_url,
                     item_quantity, item_min_quantity, item_measurement_unit,
                     item_brand_id, item_sup_id, item_loc_id, item_status, item_notes)
                 VALUES
                    (:item_name, :item_part_no, :item_colour, :item_product_url,
                     :item_quantity, :item_min_quantity, :item_measurement_unit,
                     :item_brand, :item_supplier, :item_location, :item_status, :item_notes)',
                [
                    'item_name'             => $row['name'],
                    'item_part_no'          => $row['part_no'] !== '' ? $row['part_no'] : null,
                    'item_colour'           => $row['colour'] !== '' ? $row['colour'] : null,
                    'item_product_url'      => $row['product_url'] !== '' ? $row['product_url'] : null,
                    // A tool has no stock, whatever the spreadsheet said.
                    'item_quantity'         => $quantity,
                    'item_min_quantity'     => $row['type'] === 'tool'
                        ? 0
                        : ($row['min_quantity'] !== '' ? (int)$row['min_quantity'] : 0),
                    'item_measurement_unit' => $row['type'] === 'tool'
                        ? pieceUnitId()
                        : resolveUnitId($row['unit']),
                    'item_brand'            => $ids['brand'],
                    'item_supplier'         => $ids['supplier'],
                    'item_location'         => $ids['location'],
                    'item_status'           => $ids['status'],
                    'item_notes'            => $row['notes'] !== '' ? $row['notes'] : null,
                ]
            );

            $categoryIds = [];

            foreach ($row['categories'] as $category) {
                $categoryIds[] = resolveTaxonomyId('category', $category, true, ['cat_type' => $row['type']]);
            }

            saveItemCategories($itemId, array_filter($categoryIds));

            if ($row['type'] === 'part') {
                recordStockMovement($itemId, (float)$quantity, 'imported', 'Line ' . $row['line']);
            }

            $imported++;
        }
    });

    return ['imported' => $imported, 'skipped' => $skipped];
}

<?php

/**
 * CSV import for items. The columns are the ones the export produces, so a
 * file exported from here can be edited in a spreadsheet and read back.
 */

/** Column heading => whether a value is required. */
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

/**
 * Headings an older export used, read as the column that replaced them.
 */
const IMPORT_COLUMN_ALIASES = [
    'brand' => 'manufacturer',
    'category' => 'categories',
];

/** Taxonomy key => the CSV column it is read from. */
const IMPORT_TAXONOMY_COLUMNS = [
    'brand'    => 'manufacturer',
    'supplier' => 'supplier',
    'location' => 'location',
    'status'   => 'status',
];

/*
 * Reviewed rows live in the session between the preview and the confirm, so a
 * preview can outlive the code that made it: an update lands, or a tab is left
 * open through one, and the rows waiting there are no longer the shape the
 * preview table reads.
 *
 * A version stamp lets that be spotted and the preview thrown away. Bump
 * IMPORT_ROW_VERSION whenever importRow() changes what a row holds; anything
 * unstamped, including the bare list rows were kept as before this existed, is
 * treated as out of date.
 */

const IMPORT_SESSION_KEY = 'import_rows';

const IMPORT_ROW_VERSION = 3;

function storeImportPreview(array $rows): void
{
    $_SESSION[IMPORT_SESSION_KEY] = ['version' => IMPORT_ROW_VERSION, 'rows' => $rows];
}

function clearImportPreview(): void
{
    unset($_SESSION[IMPORT_SESSION_KEY]);
}

/**
 * The preview waiting in the session, as ['rows' => [...], 'stale' => bool].
 *
 * A preview from an older version is cleared and reported as stale, so the
 * page can say why it is asking for the file again.
 */
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

/**
 * Read an uploaded CSV into rows ready for review.
 *
 * Returns ['error' => message] or ['rows' => [...]] where each row carries the
 * values, anything that would be created, and its own error if it has one.
 */
function parseItemCsv(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        return ['error' => 'Choose a CSV file to import.'];
    }

    $handle = fopen($upload['tmp_name'], 'r');

    if (!$handle) {
        return ['error' => 'That file could not be read.'];
    }

    $heading = fgetcsv($handle, 0, ',', '"', '\\');

    if (!$heading) {
        return ['error' => 'That file is empty.'];
    }

    // Match headings loosely so column order and letter case do not matter.
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
    $line = 1;

    while (($record = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $line++;

        if (count(array_filter($record, 'strlen')) === 0) {
            continue;
        }

        $rows[] = importRow($record, $columns, $known, $line);
    }

    fclose($handle);

    if (!$rows) {
        return ['error' => 'That file has headings but no rows.'];
    }

    return ['rows' => $rows];
}

/**
 * What already exists, lowercased, so a row can be judged before anything is
 * written.
 *
 * Categories are kept as name => the kind it files, which answers both whether
 * one exists and whether it agrees with the row naming it. The rest are plain
 * lists of names, locations named in full so a sub-location is recognised as
 * the one inside the location it names rather than as a new one.
 */
function importKnownNames(): array
{
    $known = ['category' => []];

    foreach (array_keys(IMPORT_TAXONOMY_COLUMNS) as $key) {
        $known[$key] = array_map('strtolower', array_map('strval', taxonomyOptions($key)));
    }

    foreach (dbAll('SELECT cat_name, cat_type FROM inv_categories') as $row) {
        $known['category'][strtolower($row['cat_name'])] = $row['cat_type'];
    }

    return $known;
}

/**
 * Turn one CSV record into a reviewable row.
 *
 * Changing which keys a row holds means bumping IMPORT_ROW_VERSION, or a
 * preview stored by the old code will be handed to a table expecting the new.
 */
function importRow(array $record, array $columns, array $known, int $line): array
{
    $value = function (string $column) use ($record, $columns) {
        $index = $columns[$column] ?? null;

        return ($index === null) ? '' : trim((string)($record[$index] ?? ''));
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

    if ($row['quantity'] !== '' && !is_numeric($row['quantity'])) {
        $row['error'] = 'Quantity "' . $row['quantity'] . '" is not a number.';

        return $row;
    }

    if ($row['min_quantity'] !== '' && !is_numeric($row['min_quantity'])) {
        $row['error'] = 'Min Quantity "' . $row['min_quantity'] . '" is not a number.';

        return $row;
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

    // Note anything that does not exist yet so the preview can say so.
    foreach (IMPORT_TAXONOMY_COLUMNS as $key => $column) {
        $name = $row[$column];

        if ($name !== '' && !in_array(strtolower($name), $known[$key], true)) {
            $row['creates'][] = taxonomy($key)['label'] . ': ' . $name;
        }
    }

    // Categories decide whether the item is a part or a tool, so an existing
    // one filing the other kind is a contradiction rather than a detail.
    foreach ($categories as $category) {
        $files = $known['category'][strtolower($category)] ?? null;

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

/** The measurement unit matching a symbol or label, or null. */
function resolveUnitId(?string $value): ?int
{
    $value = trim((string)$value);

    if ($value === '') {
        return (int)dbValue('SELECT unit_id FROM inv_measurement_units ORDER BY unit_id LIMIT 1', [], 1);
    }

    $id = dbValue(
        'SELECT unit_id FROM inv_measurement_units WHERE unit_symbol = :symbol OR unit_label = :label LIMIT 1',
        ['symbol' => $value, 'label' => $value]
    );

    return ($id === null) ? null : (int)$id;
}

/**
 * Find a taxonomy row by name, creating it when asked to. $extra is written
 * onto anything created, which is how an imported category ends up filing the
 * kind of thing the row said it was.
 */
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
 * Find a location by name, creating it when asked to.
 *
 * A value naming two locations, "Tool Chest > Drawer 1", is the drawer inside
 * the chest, which is how the export writes one and how a sub-location is read
 * back. Nesting goes one level deep, so anything past the second name is taken
 * as part of the sub-location's own name rather than as another level.
 *
 * A single name is always a location at the top level, never a sub-location
 * that happens to be called that, since one name on its own does not say which
 * of them it means.
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

/**
 * The location of this name inside $parentId, or at the top level when that is
 * null, creating it when asked to.
 */
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
 * Write the reviewed rows. Rows carrying an error are skipped.
 *
 * The whole import runs in one transaction, so a failure leaves nothing behind.
 */
function importItemRows(array $rows): array
{
    $imported = 0;
    $skipped = 0;

    dbTransaction(function () use ($rows, &$imported, &$skipped) {
        foreach ($rows as $row) {
            if (!empty($row['error'])) {
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

            // Worked out once, because the stock history has to record the
            // same figure that gets written to the item.
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
                    // A tool is one object with no stock behind it, whatever
                    // the spreadsheet happened to say.
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

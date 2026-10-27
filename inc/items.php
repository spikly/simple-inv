<?php

/** What an item is, its validation, its columns, its form and its listing. */

/** Decided by an item's categories, not a column of its own. */
const ITEM_TYPES = ['part' => 'Part', 'tool' => 'Tool'];

const ITEM_TYPE_PLURALS = ['part' => 'Parts', 'tool' => 'Tools'];

function itemType($value, string $default = 'part'): string
{
    $value = strtolower(trim((string)$value));

    return isset(ITEM_TYPES[$value]) ? $value : $default;
}

function itemTypeOf(array $item): string
{
    return empty($item['item_is_tool']) ? 'part' : 'tool';
}

function isTool(array $item): bool
{
    return itemTypeOf($item) === 'tool';
}

function itemTypePage(?string $type): string
{
    return $type === null ? 'items' : strtolower(ITEM_TYPE_PLURALS[$type]);
}

/** Field => message. A dropdown below 1 counts as unset. */
const ITEM_REQUIRED_FIELDS = [
    'item_name'     => 'Item name cannot be empty',
    'item_brand'    => 'You must select a manufacturer',
    'item_location' => 'You must select a location',
    'item_status'   => 'You must select a status',
];

/** The most inv_items.item_quantity can hold. */
const MAX_ITEM_QUANTITY = 2147483647;

/** Parts only: a tool has no stock. */
const PART_REQUIRED_FIELDS = [
    'item_measurement_unit' => 'You must select a measurement unit',
];

function validateItem(array $post, string $type): array
{
    $required = ($type === 'part')
        ? ITEM_REQUIRED_FIELDS + PART_REQUIRED_FIELDS
        : ITEM_REQUIRED_FIELDS;

    $errors = [];

    foreach ($required as $field => $message) {
        $value = $post[$field] ?? '';
        $missing = ($field === 'item_name') ? (trim((string)$value) === '') : ($value < 1);

        if ($missing) {
            $errors[$field] = $message;
        }
    }

    $categoryIds = array_filter(itemCategoryIds($post));

    if (!$categoryIds) {
        $errors['item_category'] = 'You must select at least one '
            . strtolower(ITEM_TYPES[$type]) . ' category';
    } elseif (categoryTypesFor($categoryIds) !== [$type]) {
        // Catches a stale form or a category that changed kind under it.
        $errors['item_category'] = 'Every category must be a '
            . strtolower(ITEM_TYPES[$type]) . ' category.';
    }

    if ($type === 'part' && (int)($post['item_min_quantity'] ?? 0) < 0) {
        $errors['item_min_quantity'] = 'The reorder level cannot be negative';
    }

    if (!isWebUrl(textOrNull($post, 'item_product_url'))) {
        $errors['item_product_url'] = 'The product page must be a web address starting http:// or https://';
    }

    return $errors;
}

function itemCategoryIds(array $post): array
{
    $categories = $post['item_category'] ?? [];

    return array_map('intval', is_array($categories) ? $categories : [$categories]);
}

/** A tool has no stock, but the columns are not nullable, so it is stored as one piece. */
function itemColumns(array $post, ?string $image, string $type): array
{
    $stock = ($type === 'tool')
        ? ['item_quantity' => 1, 'item_min_quantity' => 0, 'item_measurement_unit' => pieceUnitId()]
        : [
            'item_quantity'         => trim($post['item_quantity']),
            'item_min_quantity'     => (int)($post['item_min_quantity'] ?? 0),
            'item_measurement_unit' => trim($post['item_measurement_unit']),
        ];

    return $stock + [
        'item_name'        => trim($post['item_name']),
        'item_part_no'     => textOrNull($post, 'item_part_no'),
        'item_colour'      => textOrNull($post, 'item_colour'),
        'item_product_url' => textOrNull($post, 'item_product_url'),
        'item_brand'       => $post['item_brand'],
        'item_supplier'    => ($post['item_supplier'] >= 1) ? $post['item_supplier'] : null,
        'item_location'    => $post['item_location'],
        'item_status'      => $post['item_status'],
        'item_notes'       => textOrNull($post, 'item_notes'),
        'item_image'       => $image,
    ];
}

function itemFormValues(array $item): array
{
    return [
        'item_type'             => itemTypeOf($item),
        'item_name'             => $item['item_name'],
        'item_part_no'          => $item['item_part_no'],
        'item_colour'           => $item['item_colour'],
        'item_product_url'      => $item['item_product_url'],
        'item_quantity'         => $item['item_quantity'],
        'item_min_quantity'     => $item['item_min_quantity'],
        'item_measurement_unit' => $item['item_measurement_unit'],
        'item_brand'            => $item['item_brand_id'],
        'item_supplier'         => $item['item_sup_id'],
        'item_category'         => fetchItemCategoryIds($item['item_id']),
        'item_location'         => $item['item_loc_id'],
        'item_status'           => $item['item_status'],
        'item_notes'            => $item['item_notes'],
        'item_image'            => $item['item_image'],
    ];
}

function renderItemForm(array $values, string $submitName, string $type, $formMessage = false): void
{
    template('item/form', [
        'values'      => $values,
        'submitName'  => $submitName,
        'type'        => $type,
        'formMessage' => $formMessage,
        'options'     => fetchItemFormOptions($type),
    ]);
}

function itemTaxonomyField(string $key, array $tax, string $type, array $options, $selected): void
{
    $name = 'item_' . $key;
    $multiple = !empty($tax['multiple']);
    $label = ($key === 'category') ? ITEM_TYPES[$type] . ' ' . $tax['label'] : $tax['label'];

    formRow(
        $name,
        $label . ($multiple ? ' (choose one or more)' : ''),
        templateHtml('item/taxonomy-field', compact('key', 'tax', 'name', 'type', 'multiple', 'options', 'selected'))
    );
}

/** $delta is negative for Remove. Stops at the first problem, as each check needs the last. */
function validateStockChange(array $item, string $amount, int $delta): array
{
    $held = (int)$item['item_quantity'];

    if ($amount === '' || !ctype_digit($amount)) {
        $message = 'Enter how much stock to add or remove, as a whole number.';
    } elseif ((int)$amount < 1) {
        $message = 'Enter an amount greater than zero.';
    } elseif ($held + $delta < 0) {
        $message = 'You only hold ' . $held . escapeHtml($item['unit_symbol'])
            . ', so ' . abs($delta) . escapeHtml($item['unit_symbol']) . ' cannot be removed.';
    } elseif ($held + $delta > MAX_ITEM_QUANTITY) {
        $message = 'That is more stock than this can hold.';
    } else {
        return [];
    }

    return ['stock_amount' => $message];
}

/** $item is as it stands after the stock has moved. */
function stockChangeMessage(array $item, int $delta): string
{
    $unit = escapeHtml($item['unit_symbol']);

    $message = ($delta > 0 ? 'Added ' : 'Removed ') . abs($delta) . $unit
        . '. Now holding ' . (int)$item['item_quantity'] . $unit;

    if ((float)$item['item_allocated_count'] > 0) {
        $message .= ', with ' . formatQuantity($item['item_allocated_count']) . $unit
            . ' reserved for projects and ' . formatQuantity($item['item_free_count']) . $unit . ' free';
    }

    return $message . '.';
}

function renderItemNotes(array $item): void
{
    sectionHeader('Notes');
    notesBox(trim((string)$item['item_notes']) !== '' ? $item['item_notes'] : '-');
}

/** $remove keeps the box ticked when a rejected save is redrawn. */
function renderItemPhotoField(?string $image, bool $remove = false): void
{
    formRow('item_photo', 'Photo (optional)', templateHtml('item/photo-field', compact('image', 'remove')));
}

/** Returns ['name' => filename|null] or ['error' => message]. */
function resolveItemPhoto(?string $current, bool $remove, bool $copy = false): array
{
    $upload = storeItemImage($_FILES['item_photo'] ?? []);

    if (isset($upload['error'])) {
        return $upload;
    }

    // When duplicating, $current belongs to the original and must be left alone.
    if ($upload['name'] !== null) {
        if (!$copy) {
            deleteItemImage($current);
        }

        return ['name' => $upload['name']];
    }

    if ($remove) {
        if (!$copy) {
            deleteItemImage($current);
        }

        return ['name' => null];
    }

    return ['name' => $copy ? copyItemImage($current) : $current];
}

/**
 * Uploads and descriptions share one form, so a description typed while choosing
 * a file is not lost. Returns the message to show.
 */
function saveItemFiles($itemId, array $descriptions, array $upload): array
{
    $described = describeItemFiles($itemId, $descriptions);
    [$stored, $errors] = storeItemFiles($itemId, $upload);

    $done = [];

    if ($stored) {
        $done[] = $stored . ' ' . ($stored === 1 ? 'file' : 'files') . ' uploaded';
    }

    if ($described) {
        $done[] = $described . ' ' . ($described === 1 ? 'description' : 'descriptions') . ' saved';
    }

    $summary = $done ? ucfirst(implode(' and ', $done)) . '.' : 'Nothing to save.';

    if (!$errors) {
        return successMessage($summary);
    }

    // Say what succeeded too, so it is not mistaken for part of the failure.
    return errorMessage($done ? array_merge([$summary], $errors) : $errors);
}

/** Returns [stored count, errors]. One bad file does not stop the rest. */
function storeItemFiles($itemId, array $upload): array
{
    $stored = 0;
    $errors = [];

    foreach (uploadedFileList($upload) as $file) {
        $result = storeItemFile($file);

        if (isset($result['error'])) {
            // Browser-supplied name, shown as HTML.
            $errors[] = escapeHtml(basename(str_replace('\\', '/', (string)$file['name'])))
                . ': ' . $result['error'];
            continue;
        }

        insertItemFile($itemId, $result['name'], $result['original'], $result['size']);
        $stored++;
    }

    return [$stored, $errors];
}

/**
 * Returns how many changed. Walks the item's own files, so a forged id cannot
 * reach another item's.
 */
function describeItemFiles($itemId, array $descriptions): int
{
    $changed = 0;

    foreach (fetchItemFiles($itemId) as $file) {
        $id = (int)$file['file_id'];

        if (!array_key_exists($id, $descriptions)) {
            continue;
        }

        // mb_substr, so a character is not cut in half.
        $wanted = mb_substr(trim((string)$descriptions[$id]), 0, 255);
        $wanted = ($wanted === '') ? null : $wanted;

        if ($wanted !== $file['file_description']) {
            updateItemFileDescription($id, $wanted);
            $changed++;
        }
    }

    return $changed;
}

/** Deleting the item takes the rows but not the files, so this runs first. */
function deleteItemFiles($itemId): void
{
    foreach (fetchItemFiles($itemId) as $file) {
        deleteItemFile($file['file_stored_name']);
        deleteItemFileRow($file['file_id']);
    }
}

/** A file that fails to copy is left out, not recorded pointing at nothing. */
function copyItemFiles($fromItemId, $toItemId): void
{
    foreach (fetchItemFiles($fromItemId) as $file) {
        $copy = copyItemFile($file['file_stored_name']);

        if ($copy !== null) {
            insertItemFile(
                $toItemId,
                $copy,
                $file['file_name'],
                (int)$file['file_size'],
                $file['file_description']
            );
        }
    }
}

function itemsIndexPage(?string $type): void
{
    // Unlike $type, $kind includes a kind chosen by filter.
    [$where, $params, $applied, $kind, $rank] = itemFilters($type);

    $noun = ($type === null) ? 'Items' : ITEM_TYPE_PLURALS[$type];
    [$badges, $query] = itemFilterSummary($applied, $params, $type, $kind);
    $formMessage = takeFlash();
    $selected = [];
    $bulk = ['action' => '', 'values' => [], 'all' => false];

    if (isset($_POST['bulk_submit'])) {
        // The form posts to its own URL, so these are the filters on screen.
        $bulk = [
            'action' => (string)($_POST['bulk_action'] ?? ''),
            'values' => array_map('strval', array_filter(
                $_POST,
                static fn ($value, $name) => str_starts_with((string)$name, 'bulk_') && is_string($value),
                ARRAY_FILTER_USE_BOTH
            )),
            'all'    => !empty($_POST['bulk_all']),
        ];
        $selected = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['item_ids'] ?? [])))));

        $result = runBulkAction(
            $bulk['action'],
            $bulk['all'] ? fetchItemIds($where, $params) : $selected,
            $_POST,
            strtolower($noun),
            $bulk['all'] ? $query : null
        );

        // An error is shown in place so the ticks survive.
        if ($result['status'] === 'success') {
            redirectWith('index.php?' . ($_SERVER['QUERY_STRING'] ?? ''), $result);
        }

        $formMessage = $result;
    }

    $slice = paginate(countItems($where, $params));

    $links = [];

    if ($slice['total'] > 0) {
        $links['Export'] = 'index.php?page=export-items' . $query;
        $links['Labels'] = 'index.php?page=labels&amp;type=item' . $query;
    }

    $links['Import'] = 'index.php?page=import-items';
    $links['Add New ' . ($kind === null ? 'Item' : ITEM_TYPES[$kind])] =
        'index.php?page=add-item' . ($kind === null ? '' : '&amp;kind=' . $kind);

    template('page/items-index', [
        'type'        => $type,
        'items'       => fetchItems($where, $params, $slice, $rank),
        'slice'       => $slice,
        'applied'     => $applied,
        'noun'        => $noun,
        'badges'      => $badges,
        'links'       => $links,
        'columns'     => itemListingColumns($type, $selected),
        'formMessage' => $formMessage,
        'bulk'        => $bulk + ['ticked' => count($selected), 'fields' => bulkActionFields($type)],
    ]);
}

/**
 * Returns [badges, query string for export and labels]. A pinned page has no
 * Type badge, as it would repeat the heading.
 */
function itemFilterSummary(array $applied, array $params, ?string $pinnedKind, ?string $kind): array
{
    $badges = [];
    $query = '';

    foreach ($applied as $key) {
        $tax = taxonomy($key);
        $id = (string)$params[$tax['param']];

        $badges[] = '<span>' . $tax['label'] . ': '
            . escapeHtml(taxonomyName($key, $id) ?? 'unknown') . '</span>';
        $query .= '&amp;' . $tax['param'] . '=' . escapeHtml(urlencode($id));
    }

    $search = trim((string)queryParam('q'));

    if ($search !== '') {
        $badges[] = '<span>Search: ' . escapeHtml($search) . '</span>';
        $query .= '&amp;q=' . urlencode($search);
    }

    if ($kind !== null) {
        if ($pinnedKind === null) {
            $badges[] = '<span>Type: ' . ITEM_TYPES[$kind] . '</span>';
        }

        // The labels page has a ?type= of its own, hence ?kind=.
        $query .= '&amp;kind=' . $kind;
    }

    return [$badges ? ' ' . implode(' ', $badges) : '', $query];
}

/** Unnamed, so never submitted. */
const ITEM_SELECT_HEADING = '<input type="checkbox" class="select-all" aria-label="Select every row on this page">';

/** Heading => cell function. $selected is the item ids to show ticked. */
function itemListingColumns(?string $type, array $selected = []): array
{
    $columns = [
        // The bar's form sits outside the table.
        ITEM_SELECT_HEADING => function (array $item) use ($selected) {
            return '<input type="checkbox" name="item_ids[]" value="' . (int)$item['item_id'] . '"'
                . ' form="bulk-items" aria-label="Select ' . escapeHtml($item['item_name']) . '"'
                . (in_array((int)$item['item_id'], $selected, true) ? ' checked' : '') . '>';
        },
        '' => function (array $item) {
            return itemThumbLink($item);
        },
        'Name' => function (array $item) {
            return '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                . escapeHtml($item['item_name']) . '</a>'
                . ($item['cat_names']
                    ? '<small class="row-note">' . escapeHtml($item['cat_names']) . '</small>'
                    : '');
        },
    ];

    if ($type === null) {
        $columns['Type'] = function (array $item) {
            return ITEM_TYPES[itemTypeOf($item)];
        };
    }

    $columns['Location'] = 'locationCell';

    $columns['Status'] = function (array $item) {
        return nameOrDeleted($item['status_name'] ?? null);
    };

    if ($type === 'tool') {
        $columns['Signed Out To'] = 'toolBorrowerCell';
        $columns['Due Back'] = 'toolDueCell';
    } elseif ($type === 'part') {
        $columns['Allocated'] = function (array $item) {
            return formatQuantity($item['item_allocated_count']);
        };
        $columns['Free'] = 'stockCell';
    } else {
        // One column for both, since "available" means different things.
        $columns['Availability'] = function (array $item) {
            return isTool($item) ? toolBorrowerCell($item) : stockCell($item);
        };
    }

    $columns['Edit'] = function (array $item) {
        return '<a href="index.php?page=edit-item&item_id=' . $item['item_id'] . '">Edit</a>'
            . ' / <a href="index.php?page=add-item&duplicate=' . $item['item_id'] . '">Duplicate</a>';
    };

    return $columns;
}

/** Classes go by heading, not position, as only the mixed listing has a Type column. */
function renderItemTable(array $items, array $columns): void
{
    $classes = [];

    foreach (array_keys($columns) as $index => $heading) {
        if ($heading === ITEM_SELECT_HEADING) {
            $classes[$index] = 'col-select';
        } elseif ($heading === '') {
            $classes[$index] = 'col-thumb';
        } elseif ($heading === 'Location') {
            $classes[$index] = 'col-location';
        }
    }

    renderTable(
        array_keys($columns),
        $items,
        static function (array $item) use ($columns) {
            $cells = [];

            foreach ($columns as $cell) {
                $cells[] = $cell($item);
            }

            return $cells;
        },
        $classes
    );
}

/**
 * Item id => why it cannot become $newType, blocked items only: 'assembly'
 * (reserved stock, which a tool cannot hold) or 'loans' (a sign-out history,
 * which a part cannot have). A reason, not a sentence: callers word it differently.
 */
function itemsBlockingKindChange(array $itemIds, string $newType): array
{
    $itemIds = array_filter(array_map('intval', $itemIds));

    if (!$itemIds) {
        return [];
    }

    // Cast to integers above, so safe to inline.
    $list = implode(',', $itemIds);

    if ($newType === 'tool') {
        $reason = 'assembly';
        $sql = 'SELECT DISTINCT item_id FROM inv_assembly_items WHERE item_id IN (' . $list . ')';
    } else {
        $reason = 'loans';
        $sql = 'SELECT DISTINCT loan_item_id AS item_id FROM inv_tool_loans
                WHERE loan_item_id IN (' . $list . ')';
    }

    return array_fill_keys(array_column(dbAll($sql), 'item_id'), $reason);
}

function itemKindChangeError(array $item, string $newType): ?string
{
    if (itemTypeOf($item) === $newType) {
        return null;
    }

    $blocked = itemsBlockingKindChange([$item['item_id']], $newType);

    switch ($blocked[(int)$item['item_id']] ?? '') {
        case 'assembly':
            return 'This part is on a project assembly, so it cannot become a tool.'
                . ' Take it off the assembly first.';
        case 'loans':
            return 'This tool has been signed out before, so it cannot become a part.'
                . ' Its history would have nothing to belong to.';
    }

    return null;
}

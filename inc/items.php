<?php

/** What an item is, its validation, its columns, its form and its listing. */

/**
 * A part or a tool, decided by the categories it is filed under rather than by
 * a column of its own. Parts are stock; tools are single objects, see
 * inc/tools.php.
 */
const ITEM_TYPES = ['part' => 'Part', 'tool' => 'Tool'];

const ITEM_TYPE_PLURALS = ['part' => 'Parts', 'tool' => 'Tools'];

/** One of the kinds above, falling back to parts for anything unrecognised. */
function itemType($value, string $default = 'part'): string
{
    $value = strtolower(trim((string)$value));

    return isset(ITEM_TYPES[$value]) ? $value : $default;
}

/** The kind an already stored item is, read from the item_is_tool column. */
function itemTypeOf(array $item): string
{
    return empty($item['item_is_tool']) ? 'part' : 'tool';
}

function isTool(array $item): bool
{
    return itemTypeOf($item) === 'tool';
}

/** The listing page showing one kind of thing, or the mixed one for null. */
function itemTypePage(?string $type): string
{
    return $type === null ? 'items' : strtolower(ITEM_TYPE_PLURALS[$type]);
}

/** Field => message when missing. The dropdowns count anything below 1 as unset. */
const ITEM_REQUIRED_FIELDS = [
    'item_name'     => 'Item name cannot be empty',
    'item_brand'    => 'You must select a manufacturer',
    'item_location' => 'You must select a location',
    'item_status'   => 'You must select a status',
];

/** What inv_items.item_quantity can hold, so an addition cannot overflow it. */
const MAX_ITEM_QUANTITY = 2147483647;

/** Asked for on top of those, but only of a part: a tool has no stock. */
const PART_REQUIRED_FIELDS = [
    'item_measurement_unit' => 'You must select a measurement unit',
];

/**
 * Everything wrong with submitted item data, keyed by field. Every field is
 * checked rather than stopping at the first, so a half filled form says all of
 * what it needs at once.
 */
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
        // The form only offers one kind, so this catches a stale form or a
        // category that changed under it.
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

/** Submitted categories, always as a list of ids. */
function itemCategoryIds(array $post): array
{
    $categories = $post['item_category'] ?? [];

    return array_map('intval', is_array($categories) ? $categories : [$categories]);
}

/**
 * The columns to write; categories live in their own table. A tool has no
 * stock, but the columns are not nullable, so it is stored as a single piece.
 */
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

/** A stored item as form values, for the edit form and for duplicating. */
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

/**
 * $type decides which fields appear and which categories are offered, so an
 * item's categories always match the kind it was filled in as.
 */
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

/** Only categories differ between a part and a tool, so only they are labelled. */
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

/**
 * Why an amount typed into the stock form cannot be used, keyed by field.
 * $delta is negative when Remove was pressed. Stops at the first problem,
 * since each check needs the one before it to have passed.
 */
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

/** $item is the item as it stands once the stock has moved. */
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

/** The notes section at the foot of an item page, or a dash when there are none. */
function renderItemNotes(array $item): void
{
    sectionHeader('Notes');
    notesBox(trim((string)$item['item_notes']) !== '' ? $item['item_notes'] : '-');
}

/**
 * $remove keeps the box ticked when a rejected save is drawn again, so asking
 * for the photo to go is not undone by a mistake in another field.
 */
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
 * One form doing two jobs: taking new files and saving what the existing ones
 * are described as, so a description typed while a file is being chosen is not
 * lost on the way. Returns the message to show afterwards.
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

    // What did get through is said too, so the list is explained rather than
    // looking like part of what went wrong.
    return errorMessage($done ? array_merge([$summary], $errors) : $errors);
}

/**
 * Returns [kept, problems]. Each file is judged on its own, so a batch of ten
 * does not come to nothing over the one that was wrong.
 */
function storeItemFiles($itemId, array $upload): array
{
    $stored = 0;
    $errors = [];

    foreach (uploadedFileList($upload) as $file) {
        $result = storeItemFile($file);

        if (isset($result['error'])) {
            // The name came from the browser and a message is drawn as HTML.
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
 * Returns how many changed. Walks the item's own files, so an id belonging to
 * something else cannot be reached by putting it in the form.
 */
function describeItemFiles($itemId, array $descriptions): int
{
    $changed = 0;

    foreach (fetchItemFiles($itemId) as $file) {
        $id = (int)$file['file_id'];

        if (!array_key_exists($id, $descriptions)) {
            continue;
        }

        // mb_substr, so a long line is not cut through a character.
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

/**
 * A duplicate gets its own copies, as it does of the photo. One that cannot be
 * copied is left out rather than recorded as a row pointing at nothing.
 */
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

/** $type pins the page to one kind; null is the mixed listing, which holds both. */
function itemsIndexPage(?string $type): void
{
    // $kind is what the listing ended up narrowed to, filters included.
    [$where, $params, $applied, $kind] = itemFilters($type);

    $slice = paginate(countItems($where, $params));
    $noun = ($type === null) ? 'Items' : ITEM_TYPE_PLURALS[$type];
    [$badges, $query] = itemFilterSummary($applied, $params, $type, $kind);

    $links = [];

    // Export and labels cover every match, not just this page.
    if ($slice['total'] > 0) {
        $links['Export'] = 'index.php?page=export-items' . $query;
        $links['Labels'] = 'index.php?page=labels&amp;type=item' . $query;
    }

    $links['Import'] = 'index.php?page=import-items';
    $links['Add New ' . ($kind === null ? 'Item' : ITEM_TYPES[$kind])] =
        'index.php?page=add-item' . ($kind === null ? '' : '&amp;kind=' . $kind);

    template('page/items-index', [
        'type'    => $type,
        'items'   => fetchItems($where, $params, $slice),
        'slice'   => $slice,
        'applied' => $applied,
        'noun'    => $noun,
        'badges'  => $badges,
        'links'   => $links,
        'columns' => itemListingColumns($type),
    ]);
}

/**
 * The badges beside the heading and the query string carrying them to export
 * and labels. On a page pinned to one kind that badge would only repeat the
 * heading, so it is left to the mixed listing.
 */
function itemFilterSummary(array $applied, array $params, ?string $pinnedKind, ?string $kind): array
{
    $badges = [];
    $query = '';

    foreach ($applied as $key) {
        $tax = taxonomy($key);
        $id = (string)$params[$tax['param']];

        // The badge names the row, but the link has to carry its id.
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

/** Heading => the function giving that heading's cell, so the two cannot drift. */
function itemListingColumns(?string $type): array
{
    $columns = [
        '' => function (array $item) {
            return itemThumb($item['item_image'], $item['item_name']);
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

/**
 * What stands in the way of each of these items becoming $newType, as item id
 * => reason, leaving out the ones with nothing against them:
 *
 *   'assembly'  stock a project has reserved, which a tool cannot hold
 *   'loans'     a sign-out history, which a part cannot have
 *
 * The whole set is asked at once, since switching a category converts
 * everything filed under it. A reason rather than a sentence, because the item
 * pages and the category pages say it differently.
 */
function itemsBlockingKindChange(array $itemIds, string $newType): array
{
    $itemIds = array_filter(array_map('intval', $itemIds));

    if (!$itemIds) {
        return [];
    }

    // The ids are cast to integers above, so they are safe to inline; a bound
    // parameter cannot stand in for a list.
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

/** Why an item cannot change kind, or null when it can. */
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

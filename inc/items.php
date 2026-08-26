<?php

/**
 * Everything the item pages share: what kind of thing an item is, validation,
 * the values written to the database, the form and the listing.
 */

/**
 * An item is either a part or a tool, and which one it is comes from the
 * categories it is filed under rather than a column of its own.
 *
 * Parts are stock: a quantity that projects reserve and installs consume.
 * Tools are single objects: no quantity, no projects, just signed in and out
 * again, see inc/tools.php.
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

/**
 * Form field => message shown when it has not been filled in. The name must be
 * non-empty; the rest are dropdowns, where anything below 1 means "Select".
 */
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
 * Everything wrong with submitted item data, keyed by the field it belongs to,
 * or an empty array when it is fine. $type is the kind the form was filled in
 * as.
 *
 * Every field is checked rather than stopping at the first, so a half filled
 * form comes back saying all of what it needs at once.
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
        // Categories are what make an item a part or a tool, so they all have
        // to agree. The form only offers one kind, so this catches a stale
        // form or a category that changed under it.
        $errors['item_category'] = 'Every category must be a '
            . strtolower(ITEM_TYPES[$type]) . ' category.';
    }

    if ($type === 'part' && (int)($post['item_min_quantity'] ?? 0) < 0) {
        $errors['item_min_quantity'] = 'The reorder level cannot be negative';
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
 * Submitted form data as the columns to write. Categories live in their own
 * join table, so they are not included here.
 *
 * A tool has no stock, but the columns are not nullable, so it is stored as a
 * single piece of one thing and the quantity is never shown.
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
        'item_name'     => trim($post['item_name']),
        'item_part_no'  => textOrNull($post, 'item_part_no'),
        'item_brand'    => $post['item_brand'],
        'item_supplier' => ($post['item_supplier'] >= 1) ? $post['item_supplier'] : null,
        'item_location' => $post['item_location'],
        'item_status'   => $post['item_status'],
        'item_notes'    => textOrNull($post, 'item_notes'),
        'item_image'    => $image,
    ];
}

/**
 * A stored item as form values, used to populate the edit form and to
 * pre-fill the add form when duplicating.
 */
function itemFormValues(array $item): array
{
    return [
        'item_type'             => itemTypeOf($item),
        'item_name'             => $item['item_name'],
        'item_part_no'          => $item['item_part_no'],
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
 * The add/edit item form.
 *
 * $type decides both which fields appear and which categories are offered, so
 * the categories an item ends up with always match the kind it was filled in
 * as. Taxonomy dropdowns get a "+" button that opens the add-new modal handled
 * by assets/js/app.js.
 */
function renderItemForm(array $values, string $submitName, string $type, $formMessage = false): void
{
    $options = fetchItemFormOptions($type);

    echo '<form method="post" enctype="multipart/form-data">' . "\n";
    formMessage($formMessage);

    echo '    <input type="hidden" name="item_type" value="' . $type . '">' . "\n";

    textField('item_name', ITEM_TYPES[$type] . ' Name', $values['item_name'] ?? '');
    textField('item_part_no', 'Manufacturers Part Number (optional)', $values['item_part_no'] ?? '');

    if ($type === 'part') {
        textField('item_quantity', 'Item Quantity', $values['item_quantity'] ?? 1, 'number', ' min="0"');
        textField(
            'item_min_quantity',
            'Reorder Level (0 for no warning)',
            $values['item_min_quantity'] ?? 0,
            'number',
            ' min="0"'
        );

        selectField(
            'item_measurement_unit',
            'Measurement Unit',
            $options['item_measurement_unit'],
            $values['item_measurement_unit'] ?? null,
            '<option value="0">Select</option>'
        );
    }

    foreach (taxonomies() as $key => $tax) {
        itemTaxonomyField($key, $tax, $type, $options['item_' . $key], $values['item_' . $key] ?? null);
    }

    if (!empty($values['duplicate_of'])) {
        echo '    <input type="hidden" name="duplicate_of" value="' . (int)$values['duplicate_of'] . '">' . "\n";
    }

    renderItemPhotoField($values['item_image'] ?? null);

    textareaField('item_notes', 'Notes (optional)', trim($values['item_notes'] ?? ''));

    submitButton($submitName);
    echo '</form>' . "\n";
}

/**
 * One taxonomy dropdown on the item form, with the "+" button that opens the
 * add-new modal.
 *
 * The kind of item is put on both controls, because a category added from here
 * has to file that kind and the refreshed options have to be narrowed to it;
 * assets/js/app.js reads it back off them and inc/ajax.php acts on it.
 */
function itemTaxonomyField(string $key, array $tax, string $type, array $options, $selected): void
{
    $name = 'item_' . $key;
    $multiple = !empty($tax['multiple']);

    // Only categories differ between a part and a tool, so only they are
    // labelled with which one is being edited.
    $label = ($key === 'category') ? ITEM_TYPES[$type] . ' ' . $tax['label'] : $tax['label'];

    formRow($name, $label . ($multiple ? ' (choose one or more)' : ''),
        '<div class="searchable-select-row' . invalidClass($name) . '">'
        . '<div class="searchable-select">'
        . '<select name="' . $name . ($multiple ? '[]' : '') . '" id="' . $name . '"'
        . ' data-item-type="' . $type . '"' . invalidAttributes($name)
        . ($multiple ? ' multiple data-placeholder="Select..."' : '') . '>'
        . ($multiple ? '' : '<option value="0">Select</option>')
        . selectOptions($options, $selected)
        . '</select>'
        . '</div>'
        . '<button type="button" class="add-new-attribute-value" id="add_new_' . $key . '"'
        . ' data-item-type="' . $type . '"'
        . ' title="Add new ' . $tax['label'] . '">+</button>'
        . '</div>');
}

/**
 * Why an amount typed into the add/remove stock form cannot be used, keyed by
 * the field, or an empty array when it can.
 *
 * There is only one field to be wrong about, and each check here needs the one
 * before it to have passed, so this stops at the first thing it finds.
 *
 * $delta is the amount as a change: what was typed, made negative when the
 * remove button was the one pressed.
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

/**
 * What the change did, for the message shown afterwards. $item is the item as
 * it stands once the stock has moved.
 *
 * Removing stock can take it back off the assemblies holding it, so what is
 * reserved now is worth saying rather than leaving to be discovered.
 */
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

/** Photo picker, showing what is already stored with the option to remove it. */
function renderItemPhotoField(?string $image): void
{
    $control = '<input type="file" name="item_photo" id="item_photo"'
        . ' accept="image/jpeg,image/png,image/gif,image/webp"' . invalidAttributes('item_photo') . '>';

    if ($image) {
        $control = '<span class="photo-field">'
            . itemThumb($image, 'Current photo', 'item-thumb item-thumb-form')
            . $control
            . '<label class="checkbox"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label>'
            . '</span>';
    }

    formRow('item_photo', 'Photo (optional)', $control);
}

/**
 * Work out the photo filename to store, cleaning up any file it replaces.
 *
 * Returns ['name' => filename|null] or ['error' => message].
 */
function resolveItemPhoto(?string $current, bool $remove, bool $copy = false): array
{
    $upload = storeItemImage($_FILES['item_photo'] ?? []);

    if (isset($upload['error'])) {
        return $upload;
    }

    // When duplicating, $current belongs to the original item and must be
    // left alone; the new item gets its own copy of the file.
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
 * The listing behind the Parts, Tools and Items pages.
 *
 * $type pins the page to one kind of thing and picks the columns that suit it;
 * null is the mixed listing reached by drilling into a location or a
 * manufacturer, which holds both.
 */
function itemsIndexPage(?string $type): void
{
    // $kind is what the listing actually ended up narrowed to: $type on the
    // Parts and Tools pages, and whatever the mixed one was filtered by.
    [$where, $params, $applied, $kind] = itemFilters($type);

    $slice = paginate(countItems($where, $params));
    $items = fetchItems($where, $params, $slice);
    $noun = ($type === null) ? 'Items' : ITEM_TYPE_PLURALS[$type];
    [$badges, $query] = itemFilterSummary($applied, $params, $type, $kind);

    $links = [];

    // Export and labels cover everything the filters match, not just this
    // page, so they are offered whenever anything matched at all.
    if ($slice['total'] > 0) {
        $links['Export'] = 'index.php?page=export-items' . $query;
        $links['Labels'] = 'index.php?page=labels&amp;type=item' . $query;
    }

    $links['Import'] = 'index.php?page=import-items';
    $links['Add New ' . ($kind === null ? 'Item' : ITEM_TYPES[$kind])] =
        'index.php?page=add-item' . ($kind === null ? '' : '&amp;kind=' . $kind);

    pageHeader($noun . countBadge($slice['total'], $badges), $links);

    formMessage(takeFlash());
    renderItemFilters($applied, $type, itemTypePage($type));

    if (!$items) {
        echo '<p>No ' . strtolower($noun) . ' match.</p>';
        return;
    }

    $columns = itemListingColumns($type);

    renderTable(array_keys($columns), $items, function (array $item) use ($columns) {
        $cells = [];

        foreach ($columns as $cell) {
            $cells[] = $cell($item);
        }

        return $cells;
    }, [0 => 'col-thumb']);

    renderPagination($slice, strtolower($noun));
}

/**
 * The filters in force, as the badges shown beside the heading and the query
 * string that carries them on to export and labels.
 *
 * $pinnedKind is the kind the page is fixed to. On Parts or Tools the kind
 * badge would only repeat the heading, so it is left to the mixed listing.
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

        // The labels page has a ?type= of its own, which is why the kind of
        // item is never called that in a query string.
        $query .= '&amp;kind=' . $kind;
    }

    return [$badges ? ' ' . implode(' ', $badges) : '', $query];
}

/**
 * The listing columns for one kind of thing, as heading => a function giving
 * that heading's cell for a row.
 *
 * Headings and cells are one definition so they cannot drift apart, and
 * renderTable() takes the keys and the values separately.
 */
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

    $columns['Location'] = function (array $item) {
        return nameOrDeleted($item['loc_name'] ?? null);
    };

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
        // The mixed listing has one column for both, since what "available"
        // means depends on which kind the row is.
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
 * Why an item cannot be turned from a part into a tool or back, or null when
 * it can.
 *
 * The two keep different records, and neither makes sense against the other:
 * a tool has no stock for an assembly to reserve, and a part is a quantity
 * rather than the one object a sign-out history is about.
 */
function itemKindChangeError(array $item, string $newType): ?string
{
    $current = itemTypeOf($item);

    if ($current === $newType) {
        return null;
    }

    if ($current === 'part' && itemIsOnAnAssembly($item['item_id'])) {
        return 'This part is on a project assembly, so it cannot become a tool.'
            . ' Take it off the assembly first.';
    }

    if ($current === 'tool' && toolHasBeenSignedOut($item['item_id'])) {
        return 'This tool has been signed out before, so it cannot become a part.'
            . ' Its history would have nothing to belong to.';
    }

    return null;
}

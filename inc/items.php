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

/**
 * Form field => message shown when it has not been filled in. The name must be
 * non-empty; the rest are dropdowns, where anything below 1 means "Select".
 */
const ITEM_REQUIRED_FIELDS = [
    'item_name'             => 'Item name cannot be empty',
    'item_measurement_unit' => 'You must select a measurement unit',
    'item_brand'            => 'You must select a manufacturer',
    'item_location'         => 'You must select a location',
    'item_status'           => 'You must select a status',
];

/** The fields a tool has no use for, so they are neither shown nor checked. */
const PART_ONLY_FIELDS = ['item_quantity', 'item_min_quantity', 'item_measurement_unit'];

/**
 * The first validation failure for submitted item data, or null when it is
 * fine. $type is the kind the form was filled in as.
 */
function validateItem(array $post, string $type): ?string
{
    foreach (ITEM_REQUIRED_FIELDS as $field => $message) {
        if ($type === 'tool' && in_array($field, PART_ONLY_FIELDS, true)) {
            continue;
        }

        $value = $post[$field] ?? '';
        $missing = ($field === 'item_name') ? (trim((string)$value) === '') : ($value < 1);

        if ($missing) {
            return $message;
        }
    }

    $categoryIds = array_filter(itemCategoryIds($post));

    if (!$categoryIds) {
        return 'You must select at least one ' . strtolower(ITEM_TYPES[$type]) . ' category';
    }

    // Categories are what make an item a part or a tool, so they all have to
    // agree. The form only offers one kind, so this catches a stale form or a
    // category that changed under it.
    $types = categoryTypesFor($categoryIds);

    if ($types !== [$type]) {
        return 'Every category must be a ' . strtolower(ITEM_TYPES[$type]) . ' category.';
    }

    if ($type === 'part' && (int)($post['item_min_quantity'] ?? 0) < 0) {
        return 'The reorder level cannot be negative';
    }

    return null;
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
        $name = 'item_' . $key;
        $multiple = !empty($tax['multiple']);
        $label = ($key === 'category' ? ITEM_TYPES[$type] . ' ' . $tax['label'] : $tax['label']);

        formRow($name, $label . ($multiple ? ' (choose one or more)' : ''),
            '<div class="searchable-select-row">'
            . '<div class="searchable-select">'
            . '<select name="' . $name . ($multiple ? '[]' : '') . '" id="' . $name . '"'
            . ' data-item-type="' . $type . '"'
            . ($multiple ? ' multiple data-placeholder="Select..."' : '') . '>'
            . ($multiple ? '' : '<option value="0">Select</option>')
            . selectOptions($options[$name], $values[$name] ?? null)
            . '</select>'
            . '</div>'
            . '<button type="button" class="add-new-attribute-value" id="add_new_' . $key . '"'
            . ' data-item-type="' . $type . '"'
            . ' title="Add new ' . $tax['label'] . '">+</button>'
            . '</div>');
    }

    if (!empty($values['duplicate_of'])) {
        echo '    <input type="hidden" name="duplicate_of" value="' . (int)$values['duplicate_of'] . '">' . "\n";
    }

    renderItemPhotoField($values['item_image'] ?? null);

    textareaField('item_notes', 'Notes (optional)', trim($values['item_notes'] ?? ''));

    submitButton($submitName);
    echo '</form>' . "\n";
}

/** Photo picker, showing what is already stored with the option to remove it. */
function renderItemPhotoField(?string $image): void
{
    $control = '<input type="file" name="item_photo" id="item_photo" accept="image/jpeg,image/png,image/gif,image/webp">';

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
    [$where, $params, $appliedFilters, $kind] = itemFilters($type);

    $items = fetchItems($where, $params);
    $itemCount = count($items);
    $noun = $type === null ? 'Items' : ITEM_TYPE_PLURALS[$type];
    $page = $type === null ? 'items' : strtolower(ITEM_TYPE_PLURALS[$type]);

    $badges = [];
    $query = '';

    foreach ($appliedFilters as $key) {
        $tax = taxonomy($key);
        $name = taxonomyName($key, $params[$tax['param']]);

        $badges[] = '<span>' . $tax['label'] . ': ' . escapeHtml($name ?? 'unknown') . '</span>';
        $query .= '&amp;' . $tax['param'] . '=' . escapeHtml($params[$tax['param']]);
    }

    if (trim((string)queryParam('q')) !== '') {
        $badges[] = '<span>Search: ' . escapeHtml(queryParam('q')) . '</span>';
        $query .= '&amp;q=' . urlencode((string)queryParam('q'));
    }

    // On a page already called Parts or Tools the badge would only repeat the
    // heading, so it is for the mixed listing.
    if ($type === null && $kind !== null) {
        $badges[] = '<span>Type: ' . ITEM_TYPES[$kind] . '</span>';
    }

    // Export and labels follow the filters, so they need the kind of thing
    // being listed too. The labels page has a ?type= of its own, which is why
    // the kind of item is never called that in a query string.
    if ($kind !== null) {
        $query .= '&amp;kind=' . $kind;
    }

    $links = [];

    if ($itemCount > 0) {
        $links['Export'] = 'index.php?page=export-items' . $query;
        $links['Labels'] = 'index.php?page=labels&amp;type=item' . $query;
    }

    $links['Import'] = 'index.php?page=import-items';
    $links['Add New ' . ($kind === null ? 'Item' : ITEM_TYPES[$kind])] =
        'index.php?page=add-item' . ($kind === null ? '' : '&amp;kind=' . $kind);

    pageHeader($noun . countBadge($itemCount, $badges ? ' ' . implode(' ', $badges) : ''), $links);

    formMessage(takeFlash());
    renderItemFilters($appliedFilters, $type, $page);

    if ($itemCount === 0) {
        echo '<p>No ' . strtolower($noun) . ' match.</p>';
        return;
    }

    renderTable(itemColumnHeadings($type), $items, function ($item) use ($type) {
        return itemRowCells($item, $type);
    }, [0 => 'col-thumb']);
}

/** Listing headings for one kind of thing. */
function itemColumnHeadings(?string $type): array
{
    if ($type === 'tool') {
        return ['', 'Name', 'Location', 'Status', 'Signed Out To', 'Due Back', 'Edit'];
    }

    if ($type === 'part') {
        return ['', 'Name', 'Location', 'Status', 'Allocated', 'Free', 'Edit'];
    }

    return ['', 'Name', 'Type', 'Location', 'Status', 'Availability', 'Edit'];
}

/** The cells under those headings. */
function itemRowCells(array $item, ?string $type): array
{
    $cells = [
        itemThumb($item['item_image'], $item['item_name']),
        '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
            . escapeHtml($item['item_name']) . '</a>'
            . ($item['cat_names'] ? '<small class="row-note">' . escapeHtml($item['cat_names']) . '</small>' : ''),
    ];

    if ($type === null) {
        $cells[] = ITEM_TYPES[itemTypeOf($item)];
    }

    $cells[] = isset($item['loc_name']) ? escapeHtml($item['loc_name']) : '<i>Deleted</i>';
    $cells[] = isset($item['status_name']) ? escapeHtml($item['status_name']) : '<i>Deleted</i>';

    if ($type === 'tool') {
        $cells[] = toolBorrowerCell($item);
        $cells[] = toolDueCell($item);
    } elseif ($type === 'part') {
        $cells[] = formatQuantity($item['item_allocated_count']);
        $cells[] = stockCell($item);
    } else {
        $cells[] = isTool($item) ? toolBorrowerCell($item) : stockCell($item);
    }

    $cells[] = '<a href="index.php?page=edit-item&item_id=' . $item['item_id'] . '">Edit</a>'
        . ' / <a href="index.php?page=add-item&duplicate=' . $item['item_id'] . '">Duplicate</a>';

    return $cells;
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

    if ($current === 'part' && fetchItemAssemblyUsage($item['item_id'])) {
        return 'This part is on a project assembly, so it cannot become a tool.'
            . ' Take it off the assembly first.';
    }

    if ($current === 'tool' && fetchToolLoans($item['item_id'])) {
        return 'This tool has been signed out before, so it cannot become a part.'
            . ' Its history would have nothing to belong to.';
    }

    return null;
}

<?php

/**
 * Everything the add and edit item pages share: validation, the values written
 * to the database and the form itself.
 */

/**
 * Form field => message shown when it has not been filled in. The name must be
 * non-empty; the rest are dropdowns, where anything below 1 means "Select".
 */
const ITEM_REQUIRED_FIELDS = [
    'item_name'             => 'Item name cannot be empty',
    'item_measurement_unit' => 'You must select a measurement unit',
    'item_brand'            => 'You must select a brand',
    'item_location'         => 'You must select a location',
    'item_status'           => 'You must select a status',
];

/**
 * The first validation failure for submitted item data, or null when it is fine.
 */
function validateItem(array $post): ?string
{
    foreach (ITEM_REQUIRED_FIELDS as $field => $message) {
        $value = $post[$field] ?? '';
        $missing = ($field === 'item_name') ? (trim((string)$value) === '') : ($value < 1);

        if ($missing) {
            return $message;
        }
    }

    if (!array_filter(itemCategoryIds($post))) {
        return 'You must select at least one category';
    }

    if ((int)($post['item_min_quantity'] ?? 0) < 0) {
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
 */
function itemColumns(array $post, ?string $image): array
{
    return [
        'item_name'             => trim($post['item_name']),
        'item_part_no'          => textOrNull($post, 'item_part_no'),
        'item_quantity'         => trim($post['item_quantity']),
        'item_min_quantity'     => (int)($post['item_min_quantity'] ?? 0),
        'item_measurement_unit' => trim($post['item_measurement_unit']),
        'item_brand'            => $post['item_brand'],
        'item_supplier'         => ($post['item_supplier'] >= 1) ? $post['item_supplier'] : null,
        'item_location'         => $post['item_location'],
        'item_status'           => $post['item_status'],
        'item_notes'            => textOrNull($post, 'item_notes'),
        'item_image'            => $image,
    ];
}

/**
 * A stored item as form values, used to populate the edit form and to
 * pre-fill the add form when duplicating.
 */
function itemFormValues(array $item): array
{
    return [
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
 * The add/edit item form. Taxonomy dropdowns get a "+" button that opens the
 * add-new modal handled by assets/js/app.js.
 */
function renderItemForm(array $values, string $submitName, $formMessage = false): void
{
    $options = fetchItemFormOptions();

    echo '<form method="post" enctype="multipart/form-data">' . "\n";
    formMessage($formMessage);

    textField('item_name', 'Item Name', $values['item_name'] ?? '');
    textField('item_part_no', 'Manufacturers Part Number (optional)', $values['item_part_no'] ?? '');
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

    foreach (taxonomies() as $key => $tax) {
        $name = 'item_' . $key;
        $multiple = !empty($tax['multiple']);

        formRow($name, $tax['label'] . ($multiple ? ' (choose one or more)' : ''),
            '<div class="searchable-select-row">'
            . '<div class="searchable-select">'
            . '<select name="' . $name . ($multiple ? '[]' : '') . '" id="' . $name . '"'
            . ($multiple ? ' multiple data-placeholder="Select..."' : '') . '>'
            . ($multiple ? '' : '<option value="0">Select</option>')
            . selectOptions($options[$name], $values[$name] ?? null)
            . '</select>'
            . '</div>'
            . '<button type="button" class="add-new-attribute-value" id="add_new_' . $key . '"'
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
 * Validation shared by the add and edit deployment pages.
 */
function validateDeployment(array $post): ?string
{
    if (trim($post['dep_description'] ?? '') === '') {
        return 'Deployment description cannot be empty';
    }

    if ($post['dep_quantity'] < 0 || $post['dep_quantity'] > 99999999999) {
        return 'Deployment quantity cannot be empty, a negative number or greater than 99999999999';
    }

    return null;
}

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
    'item_category'         => 'You must select a category',
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
        $missing = ($field === 'item_name') ? ($value === '') : ($value < 1);

        if ($missing) {
            return $message;
        }
    }

    return null;
}

/**
 * Submitted form data as the columns to write. The category is stored in its
 * own join table, so it is not included here.
 */
function itemColumns(array $post): array
{
    return [
        'item_name'             => trim($post['item_name']),
        'item_part_no'          => textOrNull($post, 'item_part_no'),
        'item_quantity'         => trim($post['item_quantity']),
        'item_measurement_unit' => trim($post['item_measurement_unit']),
        'item_brand'            => $post['item_brand'],
        'item_supplier'         => ($post['item_supplier'] >= 1) ? $post['item_supplier'] : null,
        'item_location'         => $post['item_location'],
        'item_status'           => $post['item_status'],
        'item_notes'            => textOrNull($post, 'item_notes'),
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
        'item_measurement_unit' => $item['item_measurement_unit'],
        'item_brand'            => $item['item_brand_id'],
        'item_supplier'         => $item['item_sup_id'],
        'item_category'         => $item['cat_id'],
        'item_location'         => $item['item_loc_id'],
        'item_status'           => $item['item_status'],
        'item_notes'            => $item['item_notes'],
    ];
}

/**
 * The add/edit item form. Taxonomy dropdowns get a "+" button that opens the
 * add-new modal handled by assets/js/app.js.
 */
function renderItemForm(array $values, string $submitName, $formMessage = false): void
{
    $options = fetchItemFormOptions();

    echo '<form method="post">' . "\n";
    formMessage($formMessage);

    textField('item_name', 'Item Name', $values['item_name'] ?? '');
    textField('item_part_no', 'Manufacturers Part Number (optional)', $values['item_part_no'] ?? '');
    textField('item_quantity', 'Item Quantity', $values['item_quantity'] ?? 1, 'number');

    selectField(
        'item_measurement_unit',
        'Measurement Unit',
        $options['item_measurement_unit'],
        $values['item_measurement_unit'] ?? null,
        '<option value="0">Select</option>'
    );

    foreach (taxonomies() as $key => $tax) {
        $name = 'item_' . $key;

        formRow($name, $tax['label'],
            '<div class="searchable-select-row">'
            . '<div class="searchable-select">'
            . '<select name="' . $name . '" id="' . $name . '"><option value="0">Select</option>'
            . selectOptions($options[$name], $values[$name] ?? null)
            . '</select>'
            . '</div>'
            . '<button type="button" class="add-new-attribute-value" id="add_new_' . $key . '"'
            . ' title="Add new ' . $tax['label'] . '">+</button>'
            . '</div>');
    }

    textareaField('item_notes', 'Notes (optional)', trim($values['item_notes'] ?? ''));

    submitButton($submitName);
    echo '</form>' . "\n";
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

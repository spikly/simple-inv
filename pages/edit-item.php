<?php

$editId = queryParam('item_id');
$item = fetchSingleItem($editId);
$formMessage = takeFlash();
$values = $item ? itemFormValues($item) : [];
$type = $item ? itemTypeOf($item) : 'part';

// Switching an item between a part and a tool means switching its categories
// too, so the form is redrawn for the other kind with nothing chosen.
if ($item && queryParam('kind') !== false && !isset($_POST['edit_item_submit'])) {
    $wanted = itemType(queryParam('kind'), $type);
    $blocked = itemKindChangeError($item, $wanted);

    if ($blocked !== null) {
        $formMessage = errorMessage($blocked);
    } elseif ($wanted !== $type) {
        $type = $wanted;
        $values['item_category'] = [];
    }
}

if ($item && isset($_POST['edit_item_submit'])) {
    $type = itemType($_POST['item_type'] ?? null, itemTypeOf($item));
    $values = $_POST;
    $values['item_type'] = $type;

    $error = itemKindChangeError($item, $type) ?? validateItem($_POST, $type);
    $photo = ['name' => $item['item_image']];

    if (!$error) {
        $photo = resolveItemPhoto($item['item_image'], !empty($_POST['remove_photo']));
        $error = $photo['error'] ?? null;
    }

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbTransaction(function () use ($editId, $photo, $type) {
            dbRun(
                'UPDATE inv_items SET
                    item_name = :item_name,
                    item_part_no = :item_part_no,
                    item_quantity = :item_quantity,
                    item_min_quantity = :item_min_quantity,
                    item_measurement_unit = :item_measurement_unit,
                    item_brand_id = :item_brand,
                    item_sup_id = :item_supplier,
                    item_loc_id = :item_location,
                    item_status = :item_status,
                    item_notes = :item_notes,
                    item_image = :item_image
                 WHERE item_id = :edit_id',
                itemColumns($_POST, $photo['name'], $type) + ['edit_id' => $editId]
            );

            saveItemCategories($editId, itemCategoryIds($_POST));

            // A change in quantity is shared out again, so stock added here
            // reaches the assemblies it was short for. A tool has none.
            if ($type === 'part') {
                reallocateItem($editId);
            }
        });

        redirectWith(
            'index.php?page=edit-item&item_id=' . urlencode((string)$editId),
            successMessage(ITEM_TYPES[$type] . ' updated!')
        );
    }
} elseif ($item && isset($_POST['delete_item_submit'])) {
    deleteItemImage($item['item_image']);
    dbRun('DELETE FROM inv_items WHERE item_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

    redirectWith(
        'index.php?page=' . strtolower(ITEM_TYPE_PLURALS[itemTypeOf($item)]),
        successMessage(ITEM_TYPES[itemTypeOf($item)] . ' deleted!')
    );
}

$plural = ITEM_TYPE_PLURALS[$type];
$other = ($type === 'part') ? 'tool' : 'part';

pageHeader('Edit ' . ITEM_TYPES[$type], $item ? [
    'View ' . ITEM_TYPES[$type] => 'index.php?page=view-item&item_id=' . $item['item_id'],
    'Back to ' . $plural        => 'index.php?page=' . strtolower($plural),
    'Make it a ' . ITEM_TYPES[$other] => 'index.php?page=edit-item&item_id=' . $item['item_id']
        . '&amp;kind=' . $other,
] : []);

if (!$item) {
    formMessage($formMessage);
    echo '<p>No item found</p>';
    return;
}

if ($type !== itemTypeOf($item)) {
    echo '<p class="form-message form-success">Filing this as a '
        . strtolower(ITEM_TYPES[$type]) . '. Pick its new categories and save to make the change.</p>' . "\n";
}

renderItemForm($values, 'edit_item_submit', $type, $formMessage);

deleteSection(ITEM_TYPES[$type], 'delete_item_submit', 'Delete',
    'Delete "' . $item['item_name'] . '"? Its '
    . ($type === 'tool' ? 'sign-out history goes' : 'assembly entries go') . ' too.');

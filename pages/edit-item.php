<?php

$editId = queryParam('item_id');
$item = fetchSingleItem($editId);
$formMessage = takeFlash();
$values = $item ? itemFormValues($item) : [];
$type = $item ? itemTypeOf($item) : 'part';

// Switching kind means switching categories, so the form is redrawn empty.
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

    // A file input submits nothing when nothing was chosen.
    $values['item_image'] = $item['item_image'];

    $blocked = itemKindChangeError($item, $type);

    // About the item rather than any one field, so it goes in without one.
    $errors = ($blocked !== null ? [$blocked] : []) + validateItem($_POST, $type);
    $photo = ['name' => $item['item_image']];

    // Storing the upload moves the file, so it waits until the rest is sound.
    if (!$errors) {
        $photo = resolveItemPhoto($item['item_image'], !empty($_POST['remove_photo']));

        if (isset($photo['error'])) {
            $errors['item_photo'] = $photo['error'];
        }
    }

    if ($errors) {
        $formMessage = errorMessage($errors);
    } else {
        $heldBefore = (float)$item['item_quantity'];

        dbTransaction(function () use ($editId, $photo, $type, $heldBefore) {
            dbRun(
                'UPDATE inv_items SET
                    item_name = :item_name,
                    item_part_no = :item_part_no,
                    item_colour = :item_colour,
                    item_product_url = :item_product_url,
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

            // Shared out again, so stock added reaches the assemblies short of it.
            if ($type === 'part') {
                // This form sets the quantity, so the history wants the difference.
                recordStockMovement($editId, (float)$_POST['item_quantity'] - $heldBefore, 'edited');
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
    deleteItemFiles($editId);
    dbRun('DELETE FROM inv_items WHERE item_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

    redirectWith(
        'index.php?page=' . itemTypePage(itemTypeOf($item)),
        successMessage(ITEM_TYPES[itemTypeOf($item)] . ' deleted!')
    );
}

template('page/edit-item', [
    'item'        => $item,
    'values'      => $values,
    'type'        => $type,
    'other'       => ($type === 'part') ? 'tool' : 'part',
    'formMessage' => $formMessage,
]);

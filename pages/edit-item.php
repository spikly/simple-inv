<?php

$editId = queryParam('item_id');
$item = fetchSingleItem($editId);
$formMessage = takeFlash();
$values = $item ? itemFormValues($item) + ['item_quantity_loaded' => $item['item_quantity']] : [];
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

    // A file input submits nothing when unchanged.
    $values['item_image'] = $item['item_image'];

    $blocked = itemKindChangeError($item, $type);

    // Not tied to a field, so no key.
    $errors = ($blocked !== null ? [$blocked] : []) + validateItem($_POST, $type);

    // Stock can move in another tab while this form is open. An untouched
    // quantity keeps what is held now; a changed one is checked before it
    // overwrites the move.
    $loaded = $_POST['item_quantity_loaded'] ?? null;
    $held = (string)(int)$item['item_quantity'];

    if ($type === 'part' && $loaded !== null && (string)$loaded !== $held) {
        if (trim((string)($_POST['item_quantity'] ?? '')) === (string)$loaded) {
            $_POST['item_quantity'] = $held;
            $values['item_quantity'] = $held;
        } elseif (!isset($errors['item_quantity'])) {
            $errors['item_quantity'] = 'The stock changed to ' . $held . ' while this form was open. '
                . 'Check the quantity and save again.';
        }

        // Saving again accepts the quantity entered.
        $values['item_quantity_loaded'] = $held;
    }

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

        try {
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

                if ($type === 'part') {
                    // This form sets the quantity, so log the difference.
                    recordStockMovement($editId, (float)$_POST['item_quantity'] - $heldBefore, 'edited');
                    reallocateItem($editId);
                } else {
                    // A tool is stored as one piece, so a part becoming one is
                    // logged too, or the history disagrees if it changes back.
                    recordStockMovement($editId, 1 - $heldBefore, 'edited');
                }
            });
        } catch (\Throwable $e) {
            // The row still names the old photo, so only the new upload goes.
            if ($photo['name'] !== $item['item_image']) {
                deleteItemImage($photo['name']);
            }

            throw $e;
        }

        if ($photo['name'] !== $item['item_image']) {
            deleteItemImage($item['item_image']);
        }

        redirectWith(
            'index.php?page=edit-item&item_id=' . urlencode((string)$editId),
            successMessage(ITEM_TYPES[$type] . ' updated!')
        );
    }
} elseif ($item && isset($_POST['delete_item_submit'])) {
    // The row goes before its files, as for a bulk delete.
    deleteItems([$editId]);

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

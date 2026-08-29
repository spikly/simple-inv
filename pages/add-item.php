<?php

/**
 * A new part or a new tool. The kind is settled before the form is drawn,
 * either by the listing it was reached from (?type=tool) or by the item being
 * duplicated, because it decides which fields and categories are on offer.
 */

$values = [];
$formMessage = takeFlash();
$type = itemType(queryParam('kind'));

if (isset($_POST['add_item_submit'])) {
    $type = itemType($_POST['item_type'] ?? null);
    $values = $_POST;
    $values['item_type'] = $type;
    $duplicateOf = (int)($_POST['duplicate_of'] ?? 0);
    $values['duplicate_of'] = $duplicateOf ?: null;

    $errors = validateItem($_POST, $type);
    $source = $duplicateOf ? fetchSingleItem($duplicateOf) : false;

    // The photo a duplicate starts with belongs to its original, and a file
    // input submits nothing when nothing was chosen, so it is put back here
    // for the form to draw again if this save is rejected.
    $values['item_image'] = $source ? $source['item_image'] : null;
    $photo = ['name' => null];

    // Storing the upload moves the file, so it waits until the rest is sound.
    if (!$errors) {
        $photo = resolveItemPhoto(
            $source ? $source['item_image'] : null,
            !empty($_POST['remove_photo']),
            (bool)$source
        );

        if (isset($photo['error'])) {
            $errors['item_photo'] = $photo['error'];
        }
    }

    if ($errors) {
        $formMessage = errorMessage($errors);
    } else {
        $itemId = dbTransaction(function () use ($photo, $type) {
            $id = dbInsert(
                'INSERT INTO inv_items
                    (item_name, item_part_no, item_quantity, item_min_quantity, item_measurement_unit,
                     item_brand_id, item_sup_id, item_loc_id, item_status, item_notes, item_image)
                 VALUES
                    (:item_name, :item_part_no, :item_quantity, :item_min_quantity, :item_measurement_unit,
                     :item_brand, :item_supplier, :item_location, :item_status, :item_notes, :item_image)',
                itemColumns($_POST, $photo['name'], $type)
            );

            saveItemCategories($id, itemCategoryIds($_POST));

            if ($type === 'part') {
                recordStockMovement($id, (float)$_POST['item_quantity'], 'created');
            }

            return $id;
        });

        redirectWith(
            'index.php?page=view-item&item_id=' . $itemId,
            successMessage(ITEM_TYPES[$type] . ' added!')
        );
    }
} elseif (queryParam('duplicate')) {
    $item = fetchSingleItem(queryParam('duplicate'));

    if ($item) {
        $values = itemFormValues($item);
        $values['duplicate_of'] = $item['item_id'];
        $type = itemTypeOf($item);
    }
}

template('page/add-item', [
    'values'        => $values,
    'type'          => $type,
    'other'         => ($type === 'part') ? 'tool' : 'part',
    'hasCategories' => (bool)categoryOptions($type),
    'formMessage'   => $formMessage,
]);

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

    $error = validateItem($_POST, $type);
    $photo = ['name' => null];

    if (!$error) {
        $source = $duplicateOf ? fetchSingleItem($duplicateOf) : false;
        $photo = resolveItemPhoto($source ? $source['item_image'] : null, false, (bool)$source);
        $error = $photo['error'] ?? null;
    }

    if ($error) {
        $formMessage = errorMessage($error);
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

$other = ($type === 'part') ? 'tool' : 'part';

pageHeader('Add ' . ITEM_TYPES[$type], [
    'Back to ' . ITEM_TYPE_PLURALS[$type] => 'index.php?page=' . itemTypePage($type),
    'Add a ' . ITEM_TYPES[$other] . ' Instead' => 'index.php?page=add-item&kind=' . $other,
]);

if (!categoryOptions($type)) {
    formMessage($formMessage);
    echo '<p>There are no ' . strtolower(ITEM_TYPES[$type]) . ' categories yet, and everything has to be'
        . ' filed under one. <a href="index.php?page=add-cat">Add one first.</a></p>' . "\n";
    return;
}

renderItemForm($values, 'add_item_submit', $type, $formMessage);

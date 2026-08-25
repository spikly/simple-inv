<?php

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_item_submit'])) {
    $values = $_POST;
    $duplicateOf = (int)($_POST['duplicate_of'] ?? 0);
    $values['duplicate_of'] = $duplicateOf ?: null;

    $error = validateItem($_POST);
    $photo = ['name' => null];

    if (!$error) {
        $source = $duplicateOf ? fetchSingleItem($duplicateOf) : false;
        $photo = resolveItemPhoto($source ? $source['item_image'] : null, false, (bool)$source);
        $error = $photo['error'] ?? null;
    }

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        $itemId = dbTransaction(function () use ($photo) {
            $id = dbInsert(
                'INSERT INTO inv_items
                    (item_name, item_part_no, item_quantity, item_min_quantity, item_measurement_unit,
                     item_brand_id, item_sup_id, item_loc_id, item_status, item_notes, item_image)
                 VALUES
                    (:item_name, :item_part_no, :item_quantity, :item_min_quantity, :item_measurement_unit,
                     :item_brand, :item_supplier, :item_location, :item_status, :item_notes, :item_image)',
                itemColumns($_POST, $photo['name'])
            );

            saveItemCategories($id, itemCategoryIds($_POST));

            return $id;
        });

        redirectWith('index.php?page=view-item&item_id=' . $itemId, successMessage('Item added!'));
    }
} elseif (queryParam('duplicate')) {
    $item = fetchSingleItem(queryParam('duplicate'));

    if ($item) {
        $values = itemFormValues($item);
        $values['duplicate_of'] = $item['item_id'];
    }
}

pageHeader('Add Item', ['Back to Items' => 'index.php?page=items']);
renderItemForm($values, 'add_item_submit', $formMessage);

<?php

$values = [];
$formMessage = false;

if (isset($_POST['add_item_submit'])) {
    $values = $_POST;
    $error = validateItem($_POST);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        $itemId = dbInsert(
            'INSERT INTO inv_items
                (item_name, item_part_no, item_quantity, item_measurement_unit, item_brand_id,
                 item_sup_id, item_loc_id, item_status, item_notes)
             VALUES
                (:item_name, :item_part_no, :item_quantity, :item_measurement_unit, :item_brand,
                 :item_supplier, :item_location, :item_status, :item_notes)',
            itemColumns($_POST)
        );

        dbRun('INSERT INTO categories_items (cat_id, item_id) VALUES (:cat_id, :item_id)', [
            'cat_id'  => $_POST['item_category'],
            'item_id' => $itemId,
        ]);

        $formMessage = successMessage(
            'Item added! <a href="index.php?page=view-item&item_id=' . $itemId . '">View Item</a>'
        );
    }
} elseif (queryParam('duplicate')) {
    $item = fetchSingleItem(queryParam('duplicate'));
    $values = $item ? itemFormValues($item) : [];
}

pageHeader('Add Item');
renderItemForm($values, 'add_item_submit', $formMessage);

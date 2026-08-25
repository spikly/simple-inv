<?php

$editId = queryParam('item_id');
$formMessage = false;
$deleted = false;

if (isset($_POST['edit_item_submit'])) {
    $error = validateItem($_POST);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbRun(
            'UPDATE inv_items SET
                item_name = :item_name,
                item_part_no = :item_part_no,
                item_quantity = :item_quantity,
                item_measurement_unit = :item_measurement_unit,
                item_brand_id = :item_brand,
                item_sup_id = :item_supplier,
                item_loc_id = :item_location,
                item_status = :item_status,
                item_notes = :item_notes
             WHERE item_id = :edit_id',
            itemColumns($_POST) + ['edit_id' => $editId]
        );

        // This query will need to change if an item can ever belong to multiple categories
        // but for the simple 1 category per item system we have right now this is fine
        dbRun('UPDATE categories_items SET cat_id = :cat_id WHERE item_id = :edit_id', [
            'edit_id' => $editId,
            'cat_id'  => $_POST['item_category'],
        ]);

        $formMessage = successMessage('Item updated!');
    }
} elseif (isset($_POST['delete_item_submit'])) {
    dbRun('DELETE FROM inv_items WHERE item_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

    $formMessage = successMessage('Item deleted!');
    $deleted = true;
}

$item = fetchSingleItem($editId);

pageHeader('Edit Item', $item ? ['View Item' => 'index.php?page=view-item&item_id=' . $item['item_id']] : []);

if ($item) {
    renderItemForm(itemFormValues($item), 'edit_item_submit', $formMessage);
    deleteSection('Item', 'delete_item_submit');
} elseif ($deleted) {
    formMessage($formMessage);
} else {
    echo '<p>No item found</p>';
}

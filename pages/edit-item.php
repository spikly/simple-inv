<?php

$editId = queryParam('item_id');
$item = fetchSingleItem($editId);
$formMessage = takeFlash();
$values = $item ? itemFormValues($item) : [];

if ($item && isset($_POST['edit_item_submit'])) {
    $values = $_POST;
    $error = validateItem($_POST);
    $photo = ['name' => $item['item_image']];

    if (!$error) {
        $photo = resolveItemPhoto($item['item_image'], !empty($_POST['remove_photo']));
        $error = $photo['error'] ?? null;
    }

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbTransaction(function () use ($editId, $photo) {
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
                itemColumns($_POST, $photo['name']) + ['edit_id' => $editId]
            );

            saveItemCategories($editId, itemCategoryIds($_POST));
        });

        redirectWith('index.php?page=edit-item&item_id=' . urlencode((string)$editId), successMessage('Item updated!'));
    }
} elseif ($item && isset($_POST['delete_item_submit'])) {
    deleteItemImage($item['item_image']);
    dbRun('DELETE FROM inv_items WHERE item_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

    redirectWith('index.php?page=items', successMessage('Item deleted!'));
}

pageHeader('Edit Item', $item ? [
    'View Item'   => 'index.php?page=view-item&item_id=' . $item['item_id'],
    'Back to Items' => 'index.php?page=items',
] : []);

if (!$item) {
    formMessage($formMessage);
    echo '<p>No item found</p>';
    return;
}

renderItemForm($values, 'edit_item_submit', $formMessage);
deleteSection('Item', 'delete_item_submit', 'Delete',
    'Delete "' . $item['item_name'] . '"? Its deployments and assembly entries go too.');

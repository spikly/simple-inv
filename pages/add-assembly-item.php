<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_assembly_item_submit'])) {
    $columns = assemblyItemColumns($_POST);
    $error = empty($_POST['item_id']) ? 'Please select an item.' : validateAssemblyItem($columns);

    if ($error) {
        $values = $columns;
        $formMessage = errorMessage($error);
    } else {
        dbRun(
            'INSERT INTO inv_assembly_items
                (assembly_id, item_id, quantity_required, quantity_allocated, quantity_installed,
                 assembly_item_notes)
             VALUES
                (:assembly_id, :item_id, :quantity_required, :quantity_allocated, :quantity_installed,
                 :assembly_item_notes)',
            $columns + ['assembly_id' => $assemblyId, 'item_id' => (int)$_POST['item_id']]
        );

        redirectWith(
            'index.php?page=add-assembly-item&assembly_id=' . $assemblyId,
            successMessage('Part added to assembly!')
        );
    }
}

$items = fetchAvailableItemsForAssembly($assemblyId);

pageHeader('Add Part', [
    'Back to Assembly' => 'index.php?page=view-assembly&assembly_id=' . (int)$assembly['assembly_id'],
]);

echo '<p>Assembly: <strong>' . escapeHtml($assembly['assembly_name']) . '</strong></p>' . "\n";

if (!$items) {
    formMessage($formMessage);
    echo '<p>Every item is already part of this assembly.</p>' . "\n";
    return;
}

echo '<form method="post">' . "\n";
formMessage($formMessage);

selectField(
    'item_id',
    'Item',
    array_column($items, 'item_name', 'item_id'),
    null,
    '<option value="">Select an item</option>',
    ' required'
);

renderAssemblyItemFields($values);
submitButton('add_assembly_item_submit');
echo '</form>' . "\n";

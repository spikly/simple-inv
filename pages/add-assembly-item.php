<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}

$values = [];
$formMessage = false;

if (isset($_POST['add_assembly_item_submit'])) {
    if (empty($_POST['item_id'])) {
        $formMessage = errorMessage('Please select an item.');
    } else {
        $columns = assemblyItemColumns($_POST);
        $error = validateAssemblyItem($columns);

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

            $formMessage = successMessage('Part added to assembly!');
        }
    }
}

pageHeader('Add Part', [
    'Back to Assembly' => 'index.php?page=view-assembly&assembly_id=' . (int)$assembly['assembly_id'],
]);

echo '<p>Assembly: <strong>' . escapeHtml($assembly['assembly_name']) . '</strong></p>' . "\n";

echo '<form method="post">' . "\n";
formMessage($formMessage);

selectField(
    'item_id',
    'Item',
    array_column(fetchAvailableItemsForAssembly($assemblyId), 'item_name', 'item_id'),
    null,
    '<option value="">Select an item</option>',
    ' required'
);

renderAssemblyItemFields($values);
submitButton('add_assembly_item_submit');
echo '</form>' . "\n";

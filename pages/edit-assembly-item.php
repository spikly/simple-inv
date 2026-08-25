<?php

$assemblyItemId = queryId('assembly_item_id');
$assemblyItem = fetchAssemblyItem($assemblyItemId);

if (!$assemblyItem) {
    echo '<p>Assembly item not found.</p>';
    return;
}

$values = $assemblyItem;
$formMessage = takeFlash();

if (isset($_POST['edit_assembly_item_submit'])) {
    $values = assemblyItemColumns($_POST);
    $error = validateAssemblyItem($values);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbRun(
            'UPDATE inv_assembly_items SET
                quantity_required = :quantity_required,
                quantity_allocated = :quantity_allocated,
                quantity_installed = :quantity_installed,
                assembly_item_notes = :assembly_item_notes
             WHERE assembly_item_id = :assembly_item_id',
            $values + ['assembly_item_id' => $assemblyItemId]
        );

        redirectWith(
            'index.php?page=edit-assembly-item&assembly_item_id=' . $assemblyItemId,
            successMessage('Assembly part updated!')
        );
    }
} elseif (isset($_POST['delete_assembly_item_submit'])) {
    dbRun('DELETE FROM inv_assembly_items WHERE assembly_item_id = :assembly_item_id', [
        'assembly_item_id' => $assemblyItemId,
    ]);

    redirectWith(
        'index.php?page=view-assembly&assembly_id=' . (int)$assemblyItem['assembly_id'],
        successMessage('Part removed from assembly!')
    );
}

pageHeader('Edit Assembly Part', [
    'Back to Assembly' => 'index.php?page=view-assembly&assembly_id=' . (int)$assemblyItem['assembly_id'],
]);

echo '<p>Item: <strong>' . escapeHtml($assemblyItem['item_name']) . '</strong></p>' . "\n";
echo '<p>Assembly: <strong>' . escapeHtml($assemblyItem['assembly_name']) . '</strong></p>' . "\n";

echo '<form method="post">' . "\n";
formMessage($formMessage);
renderAssemblyItemFields($values);
submitButton('edit_assembly_item_submit');
echo '</form>' . "\n";

confirmDeleteForm('delete_assembly_item_submit', 'Remove Part', 'Remove this part from the assembly?');

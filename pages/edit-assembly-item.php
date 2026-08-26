<?php

$assemblyItemId = queryId('assembly_item_id');
$assemblyItem = fetchAssemblyItem($assemblyItemId);

if (!$assemblyItem) {
    echo '<p>Assembly item not found.</p>';
    return;
}

$values = $assemblyItem;
$formMessage = takeFlash();

$installedBefore = (float)$assemblyItem['quantity_installed'];

if (isset($_POST['edit_assembly_item_submit'])) {
    $values = assemblyItemColumns($_POST);
    $errors = validateAssemblyItem($values);

    // What is free to install is only worth working out once the figure asking
    // for it makes sense on its own.
    if (!isset($errors['quantity_installed'])) {
        $errors += validateAssemblyInstall(
            $assemblyItem['item_id'],
            $assemblyItemId,
            $values['quantity_installed'],
            $installedBefore
        );
    }

    if ($errors) {
        $formMessage = errorMessage($errors);
    } else {
        $stock = dbTransaction(function () use ($values, $assemblyItemId, $installedBefore) {
            dbRun(
                'UPDATE inv_assembly_items SET
                    quantity_required = :quantity_required,
                    quantity_installed = :quantity_installed,
                    assembly_item_notes = :assembly_item_notes
                 WHERE assembly_item_id = :assembly_item_id',
                $values + ['assembly_item_id' => $assemblyItemId]
            );

            return settleAssemblyItemStock(
                $assemblyItemId,
                $values['quantity_installed'] - $installedBefore
            );
        });

        redirectWith(
            'index.php?page=edit-assembly-item&assembly_item_id=' . $assemblyItemId,
            successMessage('Assembly part updated!' . assemblyStockMessage($stock))
        );
    }
} elseif (isset($_POST['delete_assembly_item_submit'])) {
    // Removing the part gives up its reservation, which then goes to whatever
    // else is waiting on the item. Installed units stay spent.
    dbTransaction(function () use ($assemblyItemId, $assemblyItem) {
        dbRun('DELETE FROM inv_assembly_items WHERE assembly_item_id = :assembly_item_id', [
            'assembly_item_id' => $assemblyItemId,
        ]);

        reallocateItem($assemblyItem['item_id']);
    });

    redirectWith(
        'index.php?page=view-assembly&assembly_id=' . (int)$assemblyItem['assembly_id'],
        successMessage('Part removed from assembly! Its reserved stock is free again.')
    );
}

pageHeader('Edit Assembly Part', [
    'Back to Assembly' => 'index.php?page=view-assembly&assembly_id=' . (int)$assemblyItem['assembly_id'],
]);

// What this part could still take: its own reservation plus anything free.
$available = itemStockAvailable($assemblyItem['item_id'], $assemblyItemId);
$unit = escapeHtml($assemblyItem['unit_symbol'] ?? '');

echo '<p>Item: <strong>' . escapeHtml($assemblyItem['item_name']) . '</strong></p>' . "\n";
echo '<p>Assembly: <strong>' . escapeHtml($assemblyItem['assembly_name']) . '</strong></p>' . "\n";

echo '<div class="item-property-container">' . "\n";
itemProperty('Reserved from Stock', '<p>' . formatQuantity($assemblyItem['quantity_allocated']) . $unit . '</p>');
itemProperty('Available to Take', '<p>' . formatQuantity($available) . $unit . '</p>');
echo '</div>' . "\n";

echo '<form method="post">' . "\n";
formMessage($formMessage);
renderAssemblyItemFields($values, formatQuantity($available) . $unit . ' available');
submitButton('edit_assembly_item_submit');
echo '</form>' . "\n";

confirmDeleteForm(
    'delete_assembly_item_submit',
    'Remove Part',
    'Remove this part from the assembly? Its reserved stock goes back, installed units stay spent.'
);

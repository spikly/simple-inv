<?php

$assemblyItemId = queryId('assembly_item_id');
$assemblyItem = fetchAssemblyItem($assemblyItemId);

if (!$assemblyItem) {
    template('page/edit-assembly-item', ['assemblyItem' => false]);
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

template('page/edit-assembly-item', [
    'assemblyItem' => $assemblyItem,
    'values'       => $values,
    // What this part could still take: its own reservation plus anything free.
    'available'    => itemStockAvailable($assemblyItem['item_id'], $assemblyItemId),
    'formMessage'  => $formMessage,
]);

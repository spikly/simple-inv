<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    template('page/add-assembly-item', ['assembly' => false]);
    return;
}

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_assembly_item_submit'])) {
    $columns = assemblyItemColumns($_POST);
    $itemId = (int)($_POST['item_id'] ?? 0);
    $errors = validateAssemblyItem($columns);

    if (empty($itemId)) {
        $errors = ['item_id' => 'Please select an item.'] + $errors;
    } elseif (!isset($errors['quantity_installed'])) {
        // Only worth working out once the figure asking for it makes sense.
        $errors += validateAssemblyInstall($itemId, 0, $columns['quantity_installed'], 0);
    }

    if ($errors) {
        $values = $columns + ['item_id' => $itemId];
        $formMessage = errorMessage($errors);
    } else {
        // Stored holding nothing; settling reserves what can be spared.
        $stock = dbTransaction(function () use ($columns, $assemblyId, $itemId) {
            $assemblyItemId = dbInsert(
                'INSERT INTO inv_assembly_items
                    (assembly_id, item_id, quantity_required, quantity_allocated, quantity_installed,
                     assembly_item_notes)
                 VALUES
                    (:assembly_id, :item_id, :quantity_required, 0, :quantity_installed,
                     :assembly_item_notes)',
                $columns + ['assembly_id' => $assemblyId, 'item_id' => $itemId]
            );

            return settleAssemblyItemStock($assemblyItemId, $columns['quantity_installed']);
        });

        redirectWith(
            'index.php?page=add-assembly-item&assembly_id=' . $assemblyId,
            successMessage('Part added to assembly!' . assemblyStockMessage($stock))
        );
    }
}

$options = [];

foreach (fetchAvailableItemsForAssembly($assemblyId) as $item) {
    $options[$item['item_id']] = $item['item_name']
        . ' (' . formatQuantity($item['item_free_count']) . $item['unit_symbol'] . ' free)';
}

template('page/add-assembly-item', compact('assembly', 'options', 'values', 'formMessage'));

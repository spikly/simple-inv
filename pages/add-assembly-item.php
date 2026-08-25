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
    $itemId = (int)($_POST['item_id'] ?? 0);
    $error = empty($itemId) ? 'Please select an item.' : validateAssemblyItem($columns);

    if (!$error) {
        $error = validateAssemblyInstall($itemId, 0, $columns['quantity_installed'], 0);
    }

    if ($error) {
        $values = $columns + ['item_id' => $itemId];
        $formMessage = errorMessage($error);
    } else {
        // The part is stored holding nothing, then settling the stock reserves
        // what it can spare for it and takes anything installed out of stock.
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

$options = [];

foreach ($items as $item) {
    $options[$item['item_id']] = $item['item_name']
        . ' (' . formatQuantity($item['item_free_count']) . $item['unit_symbol'] . ' free)';
}

selectField(
    'item_id',
    'Item',
    $options,
    $values['item_id'] ?? null,
    '<option value="">Select an item</option>',
    ' required'
);

renderAssemblyItemFields($values, 'stock is reserved automatically, as far as it goes');
submitButton('add_assembly_item_submit');
echo '</form>' . "\n";

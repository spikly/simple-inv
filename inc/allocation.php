<?php

/**
 * Stock moving between the store and project assemblies. Parts only.
 *
 * A part on an assembly reserves what it still needs out of its item's free
 * stock; installing takes those units out of stock for good. Reservations are
 * worked out here rather than typed in, which is what keeps the item pages,
 * the shopping list and the assemblies telling one story.
 */

/**
 * What is held, less what every other part has reserved.
 * $exceptAssemblyItemId keeps a part from competing against itself.
 */
function itemStockAvailable($item_id, $exceptAssemblyItemId = 0): float
{
    return (float)dbValue(
        'SELECT i.item_quantity
            - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items
                        WHERE item_id = i.item_id AND assembly_item_id <> :except), 0)
         FROM inv_items i
         WHERE i.item_id = :item_id',
        ['item_id' => (int)$item_id, 'except' => (int)$exceptAssemblyItemId],
        0
    );
}

/** Claims what is outstanding, or as much as the item can spare. */
function allocateAssemblyItem($assembly_item_id): float
{
    $part = dbRow(
        'SELECT item_id, quantity_required, quantity_installed
         FROM inv_assembly_items WHERE assembly_item_id = :id',
        ['id' => (int)$assembly_item_id]
    );

    if (!$part) {
        return 0.0;
    }

    $outstanding = max(0, (float)$part['quantity_required'] - (float)$part['quantity_installed']);
    $available = itemStockAvailable($part['item_id'], $assembly_item_id);
    $allocated = max(0, min($outstanding, $available));

    dbRun(
        'UPDATE inv_assembly_items SET quantity_allocated = :allocated WHERE assembly_item_id = :id',
        ['allocated' => $allocated, 'id' => (int)$assembly_item_id]
    );

    return $allocated;
}

/**
 * Share free stock over every part that wants it, oldest first. Run whenever
 * stock or demand changes, so a delivery reaches the assemblies left short.
 */
function reallocateItem($item_id): void
{
    $parts = dbAll(
        'SELECT assembly_item_id FROM inv_assembly_items
         WHERE item_id = :item_id ORDER BY assembly_item_id',
        ['item_id' => (int)$item_id]
    );

    if (!$parts) {
        return;
    }

    // Cleared first, so the order above decides who gets the stock.
    dbRun('UPDATE inv_assembly_items SET quantity_allocated = 0 WHERE item_id = :item_id', [
        'item_id' => (int)$item_id,
    ]);

    foreach ($parts as $part) {
        allocateAssemblyItem($part['assembly_item_id']);
    }
}

/** reallocateItem() over a list of items, for a delete that frees several. */
function reallocateItems(array $item_ids): void
{
    foreach ($item_ids as $item_id) {
        reallocateItem($item_id);
    }
}

/** Negative $quantity puts units back, for an installed figure corrected down. */
function consumeItemStock($item_id, float $quantity): void
{
    if ($quantity == 0.0) {
        return;
    }

    dbRun('UPDATE inv_items SET item_quantity = item_quantity - :quantity WHERE item_id = :item_id', [
        'quantity' => $quantity,
        'item_id'  => (int)$item_id,
    ]);
}

/**
 * Install what has been installed since the last save, then reserve what is
 * outstanding across every part using that item. Returns the quantity
 * reserved and how far short of the outstanding amount that leaves it.
 */
function settleAssemblyItemStock($assembly_item_id, float $installedDelta): array
{
    // The assembly's name so the history can say where the units went.
    $part = dbRow(
        'SELECT ai.item_id, ai.quantity_required, ai.quantity_installed, a.assembly_name
         FROM inv_assembly_items ai
         INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
         WHERE ai.assembly_item_id = :id',
        ['id' => (int)$assembly_item_id]
    );

    if (!$part) {
        return ['allocated' => 0.0, 'short' => 0.0];
    }

    consumeItemStock($part['item_id'], $installedDelta);

    // consumeItemStock takes stock away, so a positive delta is stock leaving.
    recordStockMovement(
        $part['item_id'],
        -$installedDelta,
        $installedDelta > 0 ? 'installed' : 'uninstalled',
        $part['assembly_name']
    );

    reallocateItem($part['item_id']);

    $allocated = (float)dbValue(
        'SELECT quantity_allocated FROM inv_assembly_items WHERE assembly_item_id = :id',
        ['id' => (int)$assembly_item_id],
        0
    );

    $outstanding = max(0, (float)$part['quantity_required'] - (float)$part['quantity_installed']);

    return ['allocated' => $allocated, 'short' => max(0, $outstanding - $allocated)];
}

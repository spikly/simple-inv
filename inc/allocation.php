<?php

/**
 * Stock moving between the store and project assemblies.
 *
 * A part on an assembly reserves what it still needs out of its item's free
 * stock, so anything set aside for a project stops counting as available
 * anywhere else. Marking some of it installed takes those units out of stock
 * for good and releases the reservation that was holding them.
 *
 * Reservations are worked out here rather than typed in, which is what keeps
 * the item pages, the shopping list and the assemblies telling the same story.
 */

/**
 * Stock a part could still claim: what is held, less what deployments have
 * taken and what every other part has already reserved.
 *
 * $exceptAssemblyItemId leaves one part's own reservation in the figure, so a
 * part can be recalculated without competing against itself.
 */
function itemStockAvailable($item_id, $exceptAssemblyItemId = 0): float
{
    return (float)dbValue(
        'SELECT i.item_quantity
            - COALESCE((SELECT SUM(dep_quantity) FROM inv_deployments
                        WHERE dep_item_id = i.item_id), 0)
            - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items
                        WHERE item_id = i.item_id AND assembly_item_id <> :except), 0)
         FROM inv_items i
         WHERE i.item_id = :item_id',
        ['item_id' => (int)$item_id, 'except' => (int)$exceptAssemblyItemId],
        0
    );
}

/**
 * Reserve stock for one part. It claims what is still outstanding, which is
 * the required quantity less whatever has already been installed, or as much
 * of that as the item can spare. Returns the quantity now reserved.
 */
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
 * Share an item's free stock over every part that wants it, oldest part first.
 *
 * Run whenever the stock or the demand on it changes, so a delivery reaches
 * the assemblies that were left short and a new deployment takes its units
 * back off them.
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

    // Clear the reservations first so the order above decides who gets the
    // stock, rather than whichever part happened to claim it earliest.
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

/**
 * Take installed units out of an item's stock, or put them back when the
 * installed figure is corrected downwards.
 */
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
 * Settle the stock behind one part after its quantities have been written:
 * install whatever has been installed since the last save, then reserve what
 * is still outstanding across every part using that item.
 *
 * Returns the quantity reserved and how far short of the outstanding amount
 * that leaves the part.
 */
function settleAssemblyItemStock($assembly_item_id, float $installedDelta): array
{
    $part = dbRow(
        'SELECT item_id, quantity_required, quantity_installed
         FROM inv_assembly_items WHERE assembly_item_id = :id',
        ['id' => (int)$assembly_item_id]
    );

    if (!$part) {
        return ['allocated' => 0.0, 'short' => 0.0];
    }

    consumeItemStock($part['item_id'], $installedDelta);
    reallocateItem($part['item_id']);

    $allocated = (float)dbValue(
        'SELECT quantity_allocated FROM inv_assembly_items WHERE assembly_item_id = :id',
        ['id' => (int)$assembly_item_id],
        0
    );

    $outstanding = max(0, (float)$part['quantity_required'] - (float)$part['quantity_installed']);

    return ['allocated' => $allocated, 'short' => max(0, $outstanding - $allocated)];
}

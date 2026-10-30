<?php

/**
 * Parts only. A part on an assembly reserves what it still needs from its item's
 * free stock; installing consumes it. Reservations are only ever computed here,
 * never entered, so item pages, shopping list and assemblies agree.
 */

/** Held less other parts' reservations; $exceptAssemblyItemId excludes the part's own. */
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
 * Shares free stock over the item's parts on open projects, oldest first. Run
 * on any stock or demand change, and when a project opens or closes.
 */
function reallocateItem($item_id): void
{
    // Cleared first, so the order below decides who gets the stock. Parts on
    // closed projects stay cleared.
    dbRun('UPDATE inv_assembly_items SET quantity_allocated = 0 WHERE item_id = :item_id', [
        'item_id' => (int)$item_id,
    ]);

    $parts = dbAll(
        'SELECT ai.assembly_item_id
         FROM inv_assembly_items ai
         INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
         INNER JOIN inv_projects p ON p.project_id = a.assembly_project_id
         INNER JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
         WHERE ai.item_id = :item_id
           AND ps.project_status_name IN (' . openProjectStatusSql() . ')
         ORDER BY ai.assembly_item_id',
        ['item_id' => (int)$item_id]
    );

    foreach ($parts as $part) {
        allocateAssemblyItem($part['assembly_item_id']);
    }
}

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
 * Consumes the newly installed quantity, then reallocates the item.
 * Returns ['allocated' => reserved, 'short' => outstanding left uncovered].
 */
function settleAssemblyItemStock($assembly_item_id, float $installedDelta): array
{
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

    // Negated: a positive delta is stock leaving.
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

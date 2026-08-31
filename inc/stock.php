<?php

/**
 * How a part's quantity got to be what it is. Every path that changes
 * item_quantity writes a row here, so a figure that looks wrong can be traced
 * back. Nothing reads a quantity out of it; the item still holds the number.
 */

/** The key is stored, so leave keys alone once rows exist; the label is display. */
const STOCK_MOVEMENT_REASONS = [
    'opening'     => 'Held when the log began',
    'created'     => 'Added to the inventory',
    'imported'    => 'Imported from a file',
    'added'       => 'Stock added',
    'removed'     => 'Stock removed',
    'edited'      => 'Quantity edited',
    'installed'   => 'Installed on an assembly',
    'uninstalled' => 'Returned from an assembly',
];

/**
 * Records a change already made; a change of nothing is not recorded. The
 * quantity is read back rather than worked out, so it matches the column.
 */
function recordStockMovement($item_id, float $change, string $reason, ?string $note = null): void
{
    if ($change == 0.0) {
        return;
    }

    dbRun(
        'INSERT INTO inv_stock_movements
            (move_item_id, move_change, move_quantity_after, move_reason, move_note)
         VALUES (:item_id, :change, :after, :reason, :note)',
        [
            'item_id' => (int)$item_id,
            'change'  => $change,
            'after'   => (float)dbValue(
                'SELECT item_quantity FROM inv_items WHERE item_id = :item_id',
                ['item_id' => (int)$item_id],
                0
            ),
            'reason'  => isset(STOCK_MOVEMENT_REASONS[$reason]) ? $reason : 'edited',
            'note'    => $note,
        ]
    );
}

/**
 * Change what is held rather than set it: buying five more is a change of
 * five, which the edit page cannot say. Assembly claims are worked out again
 * around the new figure.
 */
function adjustItemStock($item_id, int $delta, ?string $note = null): void
{
    dbTransaction(function () use ($item_id, $delta, $note) {
        dbRun('UPDATE inv_items SET item_quantity = item_quantity + :delta WHERE item_id = :item_id', [
            'delta'   => $delta,
            'item_id' => (int)$item_id,
        ]);

        recordStockMovement($item_id, (float)$delta, $delta > 0 ? 'added' : 'removed', $note);
        reallocateItem($item_id);
    });
}

function countStockMovements($item_id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_stock_movements WHERE move_item_id = :item_id',
        ['item_id' => (int)$item_id],
        0
    );
}

/** An item's movements, newest first. */
function fetchStockMovements($item_id, ?array $slice = null): array
{
    return dbAll(
        'SELECT * FROM inv_stock_movements WHERE move_item_id = :item_id
         ORDER BY move_at DESC, move_id DESC'
        . ($slice ? paginationLimit($slice) : ''),
        ['item_id' => (int)$item_id]
    );
}

/** Signed and coloured, so stock in reads differently from stock out. */
function stockChangeCell($change, string $unit): string
{
    $change = (float)$change;
    $class = ($change < 0) ? 'stock-over' : 'stock-ok';

    return '<span class="stock ' . $class . '">'
        . ($change > 0 ? '+' : '&minus;') . formatQuantity(abs($change)) . $unit . '</span>';
}

function renderStockMovements(array $movements, array $item): void
{
    template('stock/movements', compact('movements', 'item'));
}

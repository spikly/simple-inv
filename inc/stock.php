<?php

/**
 * The record of how a part's quantity got to be what it is.
 *
 * Every path that changes inv_items.item_quantity writes a row here saying
 * what changed, what it became and why, so a figure that looks wrong can be
 * traced back rather than argued about. Nothing reads a quantity out of this
 * table; the item still holds the number, and this is the story behind it.
 *
 * Tools have no stock, so nothing here applies to them.
 */

/**
 * Why a movement happened. The key is what is stored, so leave the keys alone
 * once rows exist; the label is only what the history table shows.
 */
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
 * Write down a change that has already been made.
 *
 * The quantity is read back rather than worked out, so what is recorded is
 * what the item actually holds now even where the column rounded it.
 *
 * A change of nothing is not a movement, so it is not recorded.
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
 * Take a delivery in, or write stock off, by changing what is held rather than
 * setting it. Buying five more of something is a change of five, which is what
 * the edit page cannot say.
 *
 * What is held decides what the assemblies can reserve, so their claims are
 * worked out again around the new figure: stock arriving reaches the parts
 * that were waiting on it, oldest first, and stock leaving is given back up.
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

/**
 * A change, signed and coloured, so stock coming in reads differently from
 * stock going out at a glance.
 */
function stockChangeCell($change, string $unit): string
{
    $change = (float)$change;
    $class = ($change < 0) ? 'stock-over' : 'stock-ok';

    return '<span class="stock ' . $class . '">'
        . ($change > 0 ? '+' : '&minus;') . formatQuantity(abs($change)) . $unit . '</span>';
}

/** The history table shown on a part's page. */
function renderStockMovements(array $movements, array $item): void
{
    if (!$movements) {
        echo '<p>Nothing has moved yet.</p>' . "\n";
        return;
    }

    $unit = escapeHtml($item['unit_symbol'] ?? '');

    renderTable(
        ['When', 'Change', 'Held After', 'Why', 'Note'],
        $movements,
        function ($move) use ($unit) {
            return [
                escapeHtml(formatDate($move['move_at'], 'j M Y H:i')),
                stockChangeCell($move['move_change'], $unit),
                formatQuantity($move['move_quantity_after']) . $unit,
                escapeHtml(STOCK_MOVEMENT_REASONS[$move['move_reason']] ?? $move['move_reason']),
                escapeHtml((string)$move['move_note']),
            ];
        }
    );
}

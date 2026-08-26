<?php

/**
 * Taking a delivery in, or writing stock off.
 *
 * The edit page sets what is held to a figure. This changes it by one, because
 * buying five more of something is a change of five and not a sum to work out
 * in your head first.
 */

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;
$formMessage = takeFlash();
$amount = '';
$note = '';

$itemUrl = 'index.php?page=view-item&item_id=' . urlencode((string)$itemId);
$adding = !isset($_POST['remove_stock_submit']);

if ($item && !isTool($item) && (isset($_POST['add_stock_submit']) || isset($_POST['remove_stock_submit']))) {
    $amount = trim((string)($_POST['stock_amount'] ?? ''));
    $note = trim((string)($_POST['stock_note'] ?? ''));

    // The buttons say which way it goes, so the amount is always typed in as a
    // plain number and never needs a minus sign.
    $delta = ctype_digit($amount) ? ((int)$amount * ($adding ? 1 : -1)) : 0;
    $errors = validateStockChange($item, $amount, $delta);

    if ($errors) {
        $formMessage = errorMessage($errors);
    } else {
        adjustItemStock($itemId, $delta, $note !== '' ? $note : null);

        redirectWith($itemUrl, successMessage(stockChangeMessage(fetchSingleItem($itemId), $delta)));
    }
}

pageHeader('Add and Remove Stock', $item ? [
    'View Part'  => $itemUrl,
    'Edit Part'  => 'index.php?page=edit-item&item_id=' . $item['item_id'],
    'All Parts'  => 'index.php?page=parts',
] : []);

if (!$item) {
    formMessage($formMessage);
    echo '<p>Invalid item ID</p>';
    return;
}

if (isTool($item)) {
    formMessage($formMessage);
    echo '<p><strong>' . escapeHtml($item['item_name']) . '</strong> is a tool rather than stock, so it is'
        . ' signed in and out rather than counted.'
        . ' <a href="index.php?page=loan-tool&item_id=' . $item['item_id'] . '">Sign it in or out.</a></p>' . "\n";
    return;
}

$unit = escapeHtml($item['unit_symbol']);

echo '<p><strong>Part:</strong> ' . escapeHtml($item['item_name'])
    . ' - kept in ' . escapeHtml($item['loc_name'] ?? 'no location') . '</p>' . "\n";

echo '<div class="item-property-container">' . "\n";
itemProperty('Held', '<p>' . (int)$item['item_quantity'] . $unit . '</p>');
itemProperty('Reserved for Projects', '<p>' . formatQuantity($item['item_allocated_count']) . $unit . '</p>');
itemProperty('Free', '<p>' . stockCell($item) . '</p>');
echo '</div>' . "\n";

echo '<form method="post">' . "\n";
formMessage($formMessage);

textField('stock_amount', 'Amount', $amount, 'number', ' min="1" step="1" required autofocus');
textField('stock_note', 'Note (optional)', $note, 'text', ' maxlength="255"');

echo '    <p>' . "\n";
echo '        <input type="submit" name="add_stock_submit" value="Add Stock">' . "\n";
echo '        <input type="submit" name="remove_stock_submit" value="Remove Stock" class="delete">' . "\n";
echo '    </p>' . "\n";
echo '</form>' . "\n";

if ((float)$item['item_allocated_count'] > 0) {
    echo '<p>Stock arriving goes first to the assemblies that are short of it. Removing stock takes it'
        . ' back off them, so what is reserved can fall as well as rise.</p>' . "\n";
}

sectionHeader('Stock History');

$moveSlice = paginate(countStockMovements($itemId), 'sp');
renderStockMovements(fetchStockMovements($itemId, $moveSlice), $item);
renderPagination($moveSlice, 'movements');

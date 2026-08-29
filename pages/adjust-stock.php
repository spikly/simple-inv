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

$view = [
    'item'        => $item,
    'itemUrl'     => $itemUrl,
    'amount'      => $amount,
    'note'        => $note,
    'formMessage' => $formMessage,
    'movements'   => [],
    'moveSlice'   => [],
];

if ($item && !isTool($item)) {
    $view['moveSlice'] = paginate(countStockMovements($itemId), 'sp');
    $view['movements'] = fetchStockMovements($itemId, $view['moveSlice']);
}

template('page/adjust-stock', $view);

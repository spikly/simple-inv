<?php

/**
 * Taking a delivery in, or writing stock off. The edit page sets what is held;
 * this changes it by an amount, so buying five more is a change of five.
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

    // The buttons say which way it goes, so the amount is always positive.
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

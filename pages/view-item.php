<?php

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;
$type = $item ? itemTypeOf($item) : 'part';

// The item row already carries whichever sign-out is still open, if any.
$isOut = $item && $type === 'tool' && !empty($item['loan_to']);
$signLink = $isOut ? 'Sign In' : 'Sign Out';

$links = [];

$view = [
    'item'        => $item,
    'type'        => $type,
    'isOut'       => $isOut,
    'signLink'    => $signLink,
    'formMessage' => takeFlash(),
];

if ($item) {
    if ($type === 'tool') {
        $links[$signLink] = 'index.php?page=loan-tool&item_id=' . $item['item_id'];
    } else {
        $links['Add Stock'] = 'index.php?page=adjust-stock&item_id=' . $item['item_id'];
    }

    $links['Edit ' . ITEM_TYPES[$type]] = 'index.php?page=edit-item&item_id=' . $item['item_id'];
    $links['Duplicate'] = 'index.php?page=add-item&duplicate=' . $item['item_id'];
    $links['Label'] = 'index.php?page=labels&type=item&item_id=' . $item['item_id'];

    $view['categories'] = fetchItemCategoryIds($itemId);

    if ($type === 'tool') {
        $view['loanSlice'] = paginate(countToolLoans($itemId), 'lp');
        $view['loans'] = fetchToolLoans($itemId, $view['loanSlice']);
    } else {
        $view['assemblyUsage'] = fetchItemAssemblyUsage($itemId);
        $view['moveSlice'] = paginate(countStockMovements($itemId), 'sp');
        $view['movements'] = fetchStockMovements($itemId, $view['moveSlice']);
    }
}

$view['links'] = $links;

template('page/view-item', $view);

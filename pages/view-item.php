<?php

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;
$type = $item ? itemTypeOf($item) : 'part';

if ($item && isset($_POST['save_files_submit'])) {
    $result = saveItemFiles(
        $itemId,
        is_array($_POST['file_description'] ?? null) ? $_POST['file_description'] : [],
        $_FILES['item_files'] ?? []
    );

    redirectWith('index.php?page=view-item&item_id=' . urlencode((string)$itemId), $result);
} elseif ($item && isset($_POST['delete_file_submit'])) {
    $file = fetchItemFile((int)($_POST['file_id'] ?? 0));

    if ($file && (int)$file['file_item_id'] === (int)$itemId) {
        deleteItemFile($file['file_stored_name']);
        deleteItemFileRow($file['file_id']);
        $message = successMessage('"' . escapeHtml($file['file_name']) . '" deleted.');
    } else {
        $message = errorMessage('That file is not attached to this item.');
    }

    redirectWith('index.php?page=view-item&item_id=' . urlencode((string)$itemId), $message);
}

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
    $view['files'] = fetchItemFiles($itemId);

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

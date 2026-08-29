<?php

/**
 * Signing a tool out and back in again.
 *
 * The same page does both, because which one it offers is not a choice: a
 * tool that is here can go out, and one that is out can come back.
 */

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;
$formMessage = takeFlash();
$loan = $item ? fetchOpenToolLoan($itemId) : null;
$values = [];

$itemUrl = 'index.php?page=view-item&item_id=' . urlencode((string)$itemId);
$selfUrl = 'index.php?page=loan-tool&item_id=' . urlencode((string)$itemId);

if ($item && isset($_POST['sign_out_submit'])) {
    $result = toolLoanValues($_POST);
    $errors = $result['errors'] ?? [];

    if (!$errors) {
        // Already being out is about the tool, not about a field on the form.
        $alreadyOut = signToolOut($itemId, $result['values']);
        $errors = ($alreadyOut !== null) ? [$alreadyOut] : [];
    }

    if ($errors) {
        $values = $_POST;
        $formMessage = errorMessage($errors);

        // One reason to be here is that it went out from somewhere else while
        // this form was open, so what is on screen is read again.
        $loan = fetchOpenToolLoan($itemId);
    } else {
        redirectWith($itemUrl, successMessage('Signed out to '
            . escapeHtml($result['values']['loan_to']) . '.'));
    }
} elseif ($item && $loan && isset($_POST['sign_in_submit'])) {
    signToolIn($loan['loan_id']);

    redirectWith($itemUrl, successMessage(escapeHtml($item['item_name']) . ' is back in.'));
}

$view = [
    'item'        => $item,
    'loan'        => $loan,
    'itemUrl'     => $itemUrl,
    'values'      => $values,
    'formMessage' => $formMessage,
    'loans'       => [],
    'loanSlice'   => [],
];

if ($item && isTool($item)) {
    $view['loanSlice'] = paginate(countToolLoans($itemId), 'lp');
    $view['loans'] = fetchToolLoans($itemId, $view['loanSlice']);
}

template('page/loan-tool', $view);

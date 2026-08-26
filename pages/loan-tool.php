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
    $error = $result['error'] ?? signToolOut($itemId, $result['values'] ?? []);

    if ($error) {
        $values = $_POST;
        $formMessage = errorMessage($error);

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

pageHeader('Sign Tool In and Out', $item ? [
    'View Tool' => $itemUrl,
    'All Tools' => 'index.php?page=tools',
] : []);

if (!$item) {
    formMessage($formMessage);
    echo '<p>Invalid item ID</p>';
    return;
}

if (!isTool($item)) {
    formMessage($formMessage);
    echo '<p><strong>' . escapeHtml($item['item_name']) . '</strong> is a part, not a tool, so it is'
        . ' reserved through projects rather than signed out.'
        . ' <a href="' . $itemUrl . '">Back to it.</a></p>' . "\n";
    return;
}

echo '<p><strong>Tool:</strong> ' . escapeHtml($item['item_name'])
    . ' - kept in ' . escapeHtml($item['loc_name'] ?? 'no location') . '</p>' . "\n";

// A save redirects, so anything reaching here left the loan as it was found.
if ($loan) {
    $overdue = loanIsOverdue($loan['loan_due_at']);

    echo '<p class="form-message form-' . ($overdue ? 'error' : 'success') . '">'
        . 'Out with <strong>' . escapeHtml($loan['loan_to']) . '</strong> since '
        . escapeHtml(formatDate($loan['loan_out_at']))
        . ($loan['loan_due_at']
            ? ', due back ' . escapeHtml(formatDate($loan['loan_due_at'])) . ($overdue ? ' (overdue)' : '')
            : ', with no date set')
        . '.</p>' . "\n";

    formMessage($formMessage);

    echo '<form method="post">' . "\n";
    echo '    <p>Signing it back in keeps the record, so this tool remembers where it has been.</p>' . "\n";
    echo '    <p><input type="submit" name="sign_in_submit" value="Sign Back In"></p>' . "\n";
    echo '</form>' . "\n";
} else {
    echo '<form method="post">' . "\n";
    formMessage($formMessage);
    textField('loan_to', 'Signed Out To', $values['loan_to'] ?? '', 'text', ' required');
    textField('loan_due_at', 'Due Back (optional)', $values['loan_due_at'] ?? '', 'date');
    textareaField('loan_notes', 'Notes (optional)', $values['loan_notes'] ?? '');
    submitButton('sign_out_submit', 'Sign Out');
    echo '</form>' . "\n";
}

sectionHeader('Sign-Out History');
renderToolLoans(fetchToolLoans($itemId));

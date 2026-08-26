<?php

/**
 * Correcting one sign-out record: the wrong name, a date that moved, or an
 * entry made by mistake.
 */

$loanId = queryParam('loan_id');
$itemId = queryParam('item_id');
$formMessage = takeFlash();

$loan = dbRow('SELECT * FROM inv_tool_loans WHERE loan_id = :loan_id', ['loan_id' => $loanId]);

if ($loan && isset($_POST['edit_loan_submit'])) {
    $result = toolLoanValues($_POST);
    $returned = toolReturnValue($loan, $_POST);

    $errors = ($result['errors'] ?? []) + ($returned['errors'] ?? []);

    if ($errors) {
        $formMessage = errorMessage($errors);
    } else {
        dbRun(
            'UPDATE inv_tool_loans
                SET loan_to = :loan_to, loan_due_at = :loan_due_at,
                    loan_notes = :loan_notes, loan_in_at = :loan_in_at
              WHERE loan_id = :loan_id',
            $result['values'] + [
                'loan_in_at' => $returned['value'],
                'loan_id'    => $loanId,
            ]
        );

        redirectWith(
            'index.php?page=edit-loan&loan_id=' . urlencode((string)$loanId)
                . '&item_id=' . urlencode((string)$loan['loan_item_id']),
            successMessage('Sign-out record updated!')
        );
    }
} elseif ($loan && isset($_POST['delete_loan_submit'])) {
    dbRun('DELETE FROM inv_tool_loans WHERE loan_id = :loan_id LIMIT 1', ['loan_id' => $loanId]);

    redirectWith(
        'index.php?page=view-item&item_id=' . urlencode((string)$loan['loan_item_id']),
        successMessage('Sign-out record deleted!')
    );
}

pageHeader('Edit Sign-Out Record', $itemId ? [
    'View Tool' => 'index.php?page=view-item&item_id=' . urlencode((string)$itemId),
] : []);

if (!$loan) {
    formMessage($formMessage);
    echo '<p>No sign-out record found</p>';
    return;
}

echo '<form method="post">' . "\n";
formMessage($formMessage);
textField('loan_to', 'Signed Out To', $loan['loan_to'], 'text', ' required');
textField('loan_due_at', 'Due Back (optional)', $loan['loan_due_at'], 'date');
// Clearing this puts the tool back out with whoever had it.
textField(
    'loan_in_at',
    'Returned (clear this to put it back out)',
    $loan['loan_in_at'] ? date('Y-m-d\TH:i', strtotime($loan['loan_in_at'])) : '',
    'datetime-local'
);
textareaField('loan_notes', 'Notes (optional)', (string)$loan['loan_notes']);
submitButton('edit_loan_submit');
echo '</form>' . "\n";

deleteSection('Sign-Out Record', 'delete_loan_submit', 'Delete',
    'Delete this sign-out record? The tool\'s history loses it for good.');

<?php

/** Correcting one sign-out record. */

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

template('page/edit-loan', compact('loan', 'itemId', 'formMessage'));

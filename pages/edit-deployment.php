<?php

$editId = queryParam('deployment_id');
$itemId = queryParam('item_id');
$formMessage = takeFlash();

$deployment = dbRow('SELECT * FROM inv_deployments WHERE dep_id = :edit_id', ['edit_id' => $editId]);

if ($deployment && isset($_POST['edit_deployment_submit'])) {
    $error = validateDeployment($_POST);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        // The quantity deployed competes with what the assemblies hold, so
        // their reservations are worked out again around the new figure.
        dbTransaction(function () use ($editId, $deployment) {
            dbRun(
                'UPDATE inv_deployments SET dep_description = :dep_description, dep_quantity = :dep_quantity
                 WHERE dep_id = :edit_id',
                [
                    'edit_id'         => $editId,
                    'dep_description' => trim($_POST['dep_description']),
                    'dep_quantity'    => trim($_POST['dep_quantity']),
                ]
            );

            reallocateItem($deployment['dep_item_id']);
        });

        redirectWith(
            'index.php?page=edit-deployment&deployment_id=' . urlencode((string)$editId)
                . '&item_id=' . urlencode((string)$deployment['dep_item_id']),
            successMessage('Deployment updated!')
        );
    }
} elseif ($deployment && isset($_POST['delete_deployment_submit'])) {
    dbTransaction(function () use ($editId, $deployment) {
        dbRun('DELETE FROM inv_deployments WHERE dep_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

        // The stock it was holding can go back to the assemblies.
        reallocateItem($deployment['dep_item_id']);
    });

    redirectWith(
        'index.php?page=view-item&item_id=' . urlencode((string)$deployment['dep_item_id']),
        successMessage('Deployment deleted!')
    );
}

pageHeader('Edit Deployment', $itemId ? ['View Item' => 'index.php?page=view-item&item_id=' . $itemId] : []);

if (!$deployment) {
    formMessage($formMessage);
    echo '<p>No deployment found</p>';
    return;
}

echo '<form method="post">' . "\n";
formMessage($formMessage);
textField('dep_description', 'Deployment Description', $deployment['dep_description']);
textField('dep_quantity', 'Deployment Quantity', $deployment['dep_quantity'], 'number', ' min="0"');
submitButton('edit_deployment_submit');
echo '</form>' . "\n";

deleteSection('Deployment', 'delete_deployment_submit');

<?php

$editId = queryParam('deployment_id');
$itemId = queryParam('item_id');
$formMessage = false;
$deleted = false;

if (isset($_POST['edit_deployment_submit'])) {
    $error = validateDeployment($_POST);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbRun(
            'UPDATE inv_deployments SET dep_description = :dep_description, dep_quantity = :dep_quantity
             WHERE dep_id = :edit_id',
            [
                'edit_id'         => $editId,
                'dep_description' => trim($_POST['dep_description']),
                'dep_quantity'    => trim($_POST['dep_quantity']),
            ]
        );

        $formMessage = successMessage('Deployment updated!');
    }
} elseif (isset($_POST['delete_deployment_submit'])) {
    dbRun('DELETE FROM inv_deployments WHERE dep_id = :edit_id LIMIT 1', ['edit_id' => $editId]);

    $formMessage = successMessage('Deployment deleted!');
    $deleted = true;
}

$deployment = dbRow('SELECT * FROM inv_deployments WHERE dep_id = :edit_id', ['edit_id' => $editId]);

pageHeader('Edit Deployment', $itemId ? ['View Item' => 'index.php?page=view-item&item_id=' . $itemId] : []);

if ($deployment) {
    echo '<form method="post">' . "\n";
    formMessage($formMessage);
    formRow('dep_description', 'Deployment Description',
        '<input type="text" name="dep_description" value="' . escapeHtml($deployment['dep_description']) . '" />');
    formRow('dep_quantity', 'Deployment Quantity',
        '<input type="text" name="dep_quantity" value="' . escapeHtml($deployment['dep_quantity']) . '" />');
    echo '    <p><input type="submit" name="edit_deployment_submit" value="Save"></p>' . "\n";
    echo '</form>' . "\n";

    deleteSection('Deployment', 'delete_deployment_submit');
} elseif ($deleted) {
    formMessage($formMessage);
} else {
    echo '<p>No deployment found</p>';
}

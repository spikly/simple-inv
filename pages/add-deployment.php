<?php

$itemId = queryParam('item_id');
$item = fetchSingleItem($itemId);
$formMessage = takeFlash();

if ($item && isset($_POST['add_deployment_submit'])) {
    $error = validateDeployment($_POST);

    if ($error) {
        $formMessage = errorMessage($error);
    } else {
        dbRun(
            'INSERT INTO inv_deployments (dep_item_id, dep_description, dep_quantity)
             VALUES (:dep_item_id, :dep_description, :dep_quantity)',
            [
                'dep_item_id'     => $itemId,
                'dep_description' => trim($_POST['dep_description']),
                'dep_quantity'    => trim($_POST['dep_quantity']),
            ]
        );

        redirectWith(
            'index.php?page=add-deployment&item_id=' . urlencode((string)$itemId),
            successMessage('Deployment added!')
        );
    }
}

pageHeader('Deploy Item', $item ? ['View Item' => 'index.php?page=view-item&item_id=' . $item['item_id']] : []);

if (!$item) {
    formMessage($formMessage);
    echo '<p>Invalid item ID</p>';
    return;
}

?>
<form method="post">
    <p>
        <strong>Item:</strong> <?php echo escapeHtml($item['item_name']); ?>
        - <?php echo stockCell($item); ?> free of
        <?php echo escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']); ?>
    </p>
    <?php formMessage($formMessage); ?>
    <p>
        <label for="dep_description">Deployment Description</label>
        <input type="text" name="dep_description" id="dep_description" value="" />
    </p>
    <p>
        <label for="dep_quantity">Deployment Quantity</label>
        <input type="number" name="dep_quantity" id="dep_quantity" value="1" min="0" />
    </p>
    <p>
        <input type="submit" name="add_deployment_submit" value="Save">
    </p>
</form>
<?php

sectionHeader('Current Deployments');
renderDeployments(fetchItemDeployments($itemId), $item);

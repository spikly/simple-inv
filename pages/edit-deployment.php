<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['deployment_id'])) ? $_GET['deployment_id'] : false;
$item_id = (isset($_GET['item_id'])) ? $_GET['item_id'] : false;

if(isset($_POST['edit_deployment_submit'])) {
    if(empty($_POST['dep_description'])) {
    	$formMessage = [
            'status' => 'error',
            'message' => 'Deployment name cannot be empty',
        ];
    }elseif($_POST['dep_quantity'] < 0 || $_POST['dep_quantity'] > 99999999999) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Deployment quantity cannot be empty, a negative number or greater than 99999999999',
        ];
    }else{
        $formData = [
            'edit_id' => $edit_id,
            'dep_description' => trim($_POST['dep_description']),
            'dep_quantity' => trim($_POST['dep_quantity']),
        ];

        try {
            $sql = 'UPDATE inv_deployments SET dep_description = :dep_description, dep_quantity = :dep_quantity WHERE dep_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Deployment updated!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}elseif(isset($_POST['delete_deployment_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_deployments WHERE dep_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Deployment deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_deployments WHERE dep_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $deployment = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Deployment
    </h2>
    <?php if($item_id): ?>
        <nav class="onpage-nav">
            <a href="index.php?page=view-item&item_id=<?php echo $item_id; ?>">Back to Item</a>
        </nav>
    <?php endif; ?>
</div>

<?php if($deployment): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="dep_description">Deployment Description</label>
        <input type="text" name="dep_description" value="<?php echo escapeHtml($deployment['dep_description']); ?>" />
    </p>
    <p>
        <label for="dep_quantity">Deployment Quantity</label>
        <input type="text" name="dep_quantity" value="<?php echo escapeHtml($deployment['dep_quantity']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_deployment_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Deployment
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_deployment_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No deployment found
    </p>
<?php endif; ?>
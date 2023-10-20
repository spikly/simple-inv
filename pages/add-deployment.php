<?php

$formData = [];
$formMessage = false;

$item_id = (isset($_GET['item_id'])) ? $_GET['item_id'] : false;
$item = fetchSingleItem($item_id);

if(isset($_POST['add_deployment_submit'])) {
    if(empty($_POST['dep_description'])) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Deployment description cannot be empty',
        ];
    }elseif($_POST['dep_quantity'] < 0 || $_POST['dep_quantity'] > 99999999999) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Deployment quantity cannot be empty, a negative number or greater than 99999999999',
        ];
    }else{
        $formData = [
            'dep_item_id' => $item_id,
            'dep_description' => trim($_POST['dep_description']),
            'dep_quantity' => trim($_POST['dep_quantity']),
        ];

        try {
            $sql = 'INSERT INTO inv_deployments (dep_item_id, dep_description, dep_quantity) VALUES (:dep_item_id, :dep_description, :dep_quantity)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Deployment added!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }
}

?>

<div class="flex-nav">
    <h2>
        Deploy Item
    </h2>
    <?php if($item): ?>
        <nav class="onpage-nav">
            <a href="index.php?page=view-item&item_id=<?php echo $item['item_id']; ?>">Back to Item</a>
        </nav>
    <?php endif; ?>
</div>

<?php if($item): ?>
<form method="post">
    <p>
        <strong>Item:</strong> <?php echo escapeHtml($item['item_name']); ?>
    </p>
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="dep_description">Deployment Description</label>
        <input type="text" name="dep_description" />
    </p>
    <p>
        <label for="dep_quantity">Deployment Quantity</label>
        <input type="number" name="dep_quantity" value="1" />
    </p>
    <p>
        <input type="submit" name="add_deployment_submit" value="Save">
    </p>
</form>
<?php else: ?>
    <p>
        Invalid item ID
    </p>
<?php endif; ?>
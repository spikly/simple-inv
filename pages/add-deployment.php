<?php

$formData = [];
$formMessage = false;
$deployments = [];

$item_id = (isset($_GET['item_id'])) ? $_GET['item_id'] : false;
$item = fetchSingleItem($item_id);

if($item) {
    $deployments = fetchItemDeployments($item_id);
}

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
            <a href="index.php?page=view-item&item_id=<?php echo $item['item_id']; ?>">View Item</a>
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
<div class="flex-nav extra-padding">
    <h2>
        Current Deployments
    </h2>
</div>
<?php if(count($deployments) > 0): ?>
<div class="table-container">
    <table>
        <tr>
            <th>Description</th>
            <th>Quantity</th>
            <th>Utilisation</th>
            <th>Date</th>
            <th>Edit</th>
        </tr>
        <?php foreach($deployments as $deployment): ?>
        <tr>
            <td><?php echo escapeHtml($deployment['dep_description']); ?></td>
            <td><?php echo escapeHtml($deployment['dep_quantity']); ?></td>
            <td><?php echo calculatePercentage($item['item_quantity'], $deployment['dep_quantity']); ?>&percnt;</td>
            <td><?php echo escapeHtml($deployment['dep_timestamp']); ?></td>
            <td><a href="index.php?page=edit-deployment&deployment_id=<?php echo $deployment['dep_id']; ?>&item_id=<?php echo $deployment['dep_item_id']; ?>">Edit</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php else: ?>
<p>
    No current deployments
</p>
<?php endif; ?>
<?php else: ?>
    <p>
        Invalid item ID
    </p>
<?php endif; ?>
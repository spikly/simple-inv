<?php

$item_id = (isset($_GET['item_id'])) ? $_GET['item_id'] : false;
$deployments = [];

try {
    $sql = 'SELECT i.item_id, i.item_name, i.item_quantity, i.item_notes, b.brand_id, b.brand_name, c.cat_id, c.cat_name, l.loc_id, l.loc_name, s.status_id, s.status_name
            FROM inv_items i
            LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
            LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
            LEFT JOIN inv_statuses s ON s.status_id  = i.item_status
            LEFT JOIN categories_items ci ON i.item_id = ci.item_id
            LEFT JOIN inv_categories c ON ci.cat_id = c.cat_id
            WHERE i.item_id = :item_id';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'item_id' => $item_id
    ]);

    $item = $stmt->fetch();

    if($item) {
        $deployments = fetchItemDeployments($item_id);
    }
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$deploymentCount = countItemDeployments($item_id);
$utilisationPercentage = calculatePercentage($item['item_quantity'], $deploymentCount)

?>

<div class="flex-nav">
    <h2>
        Item Info
    </h2>
    <?php if($item_id && $item): ?>
        <nav class="onpage-nav">
            <a href="index.php?page=add-deployment&item_id=<?php echo $item['item_id']; ?>">Deploy Item</a>
            <a href="index.php?page=edit-item&item_id=<?php echo $item['item_id']; ?>">Edit Item</a>
        </nav>
    <?php endif; ?>
</div>

<?php if($item_id && $item): ?>
    <h3>Name</h3>
    <p class="item-name">
        <?php echo escapeHtml($item['item_name']); ?>
    </p>
    <div class="item-property-container">
        <div class="item-property">
            <h3>Quantity</h3>
            <p>
                <?php echo escapeHtml($item['item_quantity']); ?>
            </p>
        </div>
        <div class="item-property">
            <h3>Deployed</h3>
            <p>
                <?php echo $deploymentCount; ?>
            </p>
        </div>
        <div class="item-property <?php echo utilisationBg($utilisationPercentage); ?>">
            <h3>Utilisation</h3>
            <?php echo $utilisationPercentage; ?>%
        </div>
        <div class="item-property">
            <h3>Brand</h3>
            <p>
                <a href="index.php?page=items&brand_id=<?php echo $item['brand_id']; ?>">
                    <?php echo escapeHtml($item['brand_name']); ?>
                </a>
            </p>
        </div>
        <div class="item-property">
            <h3>Category</h3>
            <p>
                <a href="index.php?page=items&category_id=<?php echo $item['cat_id']; ?>">
                    <?php echo escapeHtml($item['cat_name']); ?>
                </a>
            </p>
        </div>
        <div class="item-property">
            <h3>Storage Location</h3>
            <p>
                <a href="index.php?page=items&location_id=<?php echo $item['loc_id']; ?>">
                    <?php echo escapeHtml($item['loc_name']); ?>
                </a>
            </p>
        </div>
        <div class="item-property">
            <h3>Status</h3>
            <p>
                <a href="index.php?page=items&status_id=<?php echo $item['status_id']; ?>">
                    <?php echo escapeHtml($item['status_name']); ?>
                </a>
            </p>
        </div>
    </div>
    <h3>Current Deployments</h3>
    <?php if(count($deployments) > 0): ?>
    <div class="table-container">
        <table>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Date</th>
                <th>Edit</th>
            </tr>
            <?php foreach($deployments as $deployment): ?>
            <tr>
                <td><?php echo escapeHtml($deployment['dep_description']); ?></td>
                <td><?php echo escapeHtml($deployment['dep_quantity']); ?></td>
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
    <h3>Other Notes</h3>
    <div class="notes-box">
        <?php echo nl2p(text2link((isset($item['item_notes']) && strlen($item['item_notes']) > 0) ? escapeHtml($item['item_notes']) : '-')); ?>
    </div>
<?php else: ?>
<p>
    No item found
</p>
<?php endif; ?>
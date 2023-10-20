<?php

$item_id = (isset($_GET['item_id'])) ? $_GET['item_id'] : false;

try {
    $sql = 'SELECT i.item_id, i.item_name, i.item_quantity, i.item_deployed_loc, i.item_notes, b.brand_name, c.cat_name, l.loc_name, s.status_name
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
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Item Info
    </h2>
    <?php if($item_id && $item): ?>
        <nav class="onpage-nav">
            <a href="index.php?page=edit-item&item_id=<?php echo $item['item_id']; ?>">Edit Item</a>
        </nav>
    <?php endif; ?>
</div>

<?php if($item_id && $item): ?>

<h3>Name</h3>
<p>
    <?php echo escapeHtml($item['item_name']); ?>
</p>
<h3>Quantity</h3>
<p>
    <?php echo escapeHtml($item['item_quantity']); ?>
</p>
<h3>Brand</h3>
<p>
    <?php echo escapeHtml($item['brand_name']); ?>
</p>
<h3>Category</h3>
<p>
    <?php echo escapeHtml($item['cat_name']); ?>
</p>
<h3>Storage Location</h3>
<p>
    <?php echo escapeHtml($item['loc_name']); ?>
</p>
<h3>Status</h3>
<p>
    <?php echo escapeHtml($item['status_name']); ?>
</p>
<h3>Deployed Location</h3>
<p>
    <?php echo (isset($item['item_deployed_loc']) && strlen($item['item_deployed_loc']) > 0) ? escapeHtml($item['item_deployed_loc']) : '-'; ?>
</p>
<h3>Other Notes</h3>
<div class="notes-box">
    <?php echo nl2p(text2link((isset($item['item_notes']) && strlen($item['item_notes']) > 0) ? escapeHtml($item['item_notes']) : '-')); ?>
</div>

<?php else: ?>
<p>
    No item found
</p>
<?php endif; ?>
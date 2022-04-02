<?php

$allItems = [];
$params = [];
$clauses = [];

$sql = 'SELECT i.item_id, i.item_name, i.item_deployed_loc, b.brand_name, c.cat_name, l.loc_name, s.status_name
FROM inv_items i
INNER JOIN inv_brands b ON b.brand_id = i.item_brand_id
INNER JOIN inv_locations l ON l.loc_id = i.item_loc_id
INNER JOIN inv_statuses s ON s.status_id  = i.item_status
INNER JOIN categories_items ci ON i.item_id = ci.item_id
INNER JOIN inv_categories c ON ci.cat_id = c.cat_id ';

if(isset($_GET['brand_id'])) {
    $clauses[] = 'i.item_brand_id = :brand_id';
    $params['brand_id'] = $_GET['brand_id'];
}

if(isset($_GET['category_id'])) {
    $clauses[] = 'ci.cat_id = :category_id';
    $params['category_id'] = $_GET['category_id'];
}

if(isset($_GET['location_id'])) {
    $clauses[] = 'i.item_loc_id = :location_id';
    $params['location_id'] = $_GET['location_id'];
}

if(isset($_GET['status_id'])) {
    $clauses[] = 'i.item_status = :status_id';
    $params['status_id'] = $_GET['status_id'];
}

if(count($clauses) > 0) {
    $sql .= 'WHERE ' . implode(' AND ', $clauses);
}

$sql .= ' ORDER BY item_name asc';
$stmt = $db->prepare($sql);
$stmt->execute($params);

$allItems = $stmt->fetchAll();
?>

<div class="flex-nav">
    <h2>
        Items
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-item">Add New Item</a>
    </nav>
</div>

<?php if(count($allItems) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Brand</th>
        <th>Category</th>
        <th>Location</th>
        <th>Status</th>
        <th>Deployed Location</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allItems as $item): ?>
        <tr>
            <td><?php echo escapeHtml($item['item_name']); ?></td>
            <td><?php echo escapeHtml($item['brand_name']); ?></td>
            <td><?php echo escapeHtml($item['cat_name']); ?></td>
            <td><?php echo escapeHtml($item['loc_name']); ?></td>
            <td><?php echo escapeHtml($item['status_name']); ?></td>
            <td><?php echo (isset($item['item_deployed_loc']) && strlen($item['item_deployed_loc']) > 0) ? escapeHtml($item['item_deployed_loc']) : '-'; ?></td>
            <td><a href="index.php?page=edit-item&item_id=<?php echo $item['item_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
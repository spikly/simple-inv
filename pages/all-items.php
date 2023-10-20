<?php

$allItems = [];
$params = [];
$clauses = [];

$sql = 'SELECT i.item_id, i.item_name, i.item_quantity, b.brand_name, c.cat_name, l.loc_name, s.status_name, d.item_deployed_count
FROM inv_items i
LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
LEFT JOIN inv_statuses s ON s.status_id  = i.item_status
LEFT JOIN categories_items ci ON i.item_id = ci.item_id
LEFT JOIN inv_categories c ON ci.cat_id = c.cat_id
LEFT JOIN (select dep_item_id, sum(dep_quantity) as item_deployed_count from inv_deployments group by dep_item_id) d on i.item_id = d.dep_item_id';

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
$itemCount = count($allItems);

$filtersApplied = [];
$exportUrl = '';
if($itemCount > 0) {
    if(count($clauses) > 0) {
        foreach($params as $index => $value) {
            switch ($index) {
                case 'brand_id':
                    $filtersApplied[] = '<span>Brand: ' . $allItems[0]['brand_name'] . '</span>';
                    $exportUrl = '&amp;' . $index . '=' . $value;
                    break;
                case 'category_id':
                    $filtersApplied[] = '<span>Category: ' . $allItems[0]['cat_name'] . '</span>';
                    $exportUrl = '&amp;' . $index . '=' . $value;
                    break;
                case 'location_id':
                    $filtersApplied[] = '<span>Location: ' . $allItems[0]['loc_name'] . '</span>';
                    $exportUrl = '&amp;' . $index . '=' . $value;
                    break;
                case 'status_id':
                    $filtersApplied[] = '<span>Status: ' . $allItems[0]['status_name'] . '</span>';
                    $exportUrl = '&amp;' . $index . '=' . $value;
                    break;                
                default:
                    break;
            }
        }
    }
}
?>

<div class="flex-nav">
    <h2>
        Items <span> <span><?php echo $itemCount; ?></span> <?php echo ($filtersApplied > 0) ? implode(' ', $filtersApplied) : ' none'; ?></span>
    </h2>
    <nav class="onpage-nav">
        <?php if($itemCount > 0): ?>
            <a href="index.php?page=export-items<?php echo $exportUrl; ?>">Export</a>
        <?php endif; ?>
        <a href="index.php?page=add-item">Add New Item</a>
    </nav>
</div>

<?php if($itemCount > 0): ?>
<div class="search-box">
    <input type="search" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..." >
</div>

<div class="table-container">
    <table id="searchableTable">
        <tr>
            <th>Name</th>
            <th>Brand</th>
            <th>Category</th>
            <th>Location</th>
            <th>Status</th>
            <th>Quantity</th>
            <th>Deployed</th>
            <th>Edit</th>
        </tr>
        <?php foreach($allItems as $item): ?>
            <tr>
                <td><a href="index.php?page=view-item&item_id=<?php echo $item['item_id']; ?>"><?php echo escapeHtml($item['item_name']); ?></a></td>
                <td><?php echo (isset($item['brand_name'])) ? escapeHtml($item['brand_name']) : '<i>Deleted</i>'; ?></td>
                <td><?php echo (isset($item['cat_name'])) ? escapeHtml($item['cat_name']) : '<i>Deleted</i>'; ?></td>
                <td><?php echo (isset($item['loc_name'])) ? escapeHtml($item['loc_name']) : '<i>Deleted</i>'; ?></td>
                <td><?php echo (isset($item['status_name'])) ? escapeHtml($item['status_name']) : '<i>Deleted</i>'; ?></td>
                <td><?php echo escapeHtml($item['item_quantity']); ?></td>
                <td><?php echo (isset($item['item_deployed_count'])) ? escapeHtml($item['item_deployed_count']) : '0'; ?></td>
                <td><a href="index.php?page=edit-item&item_id=<?php echo $item['item_id']; ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php else: ?>
No items
<?php endif; ?>
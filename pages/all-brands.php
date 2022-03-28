<?php

$allBrands = [];

$sql = 'SELECT brand_id, brand_name FROM inv_brands ORDER BY brand_name asc';

$stmt = $db->prepare($sql);
$stmt->execute();

$allBrands = $stmt->fetchAll();
?>

<div class="flex-nav">
    <h2>
        Brands
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-brand">Add New Brand</a>
    </nav>
</div>

<?php if(count($allBrands) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allBrands as $brand): ?>
        <tr>
            <td><a href="index.php?page=items&brand_id=<?php echo $brand['brand_id']; ?>"><?php echo escapeHtml($brand['brand_name']); ?></a></td>
            <td><a href="index.php?page=edit-brand&brand_id=<?php echo $brand['brand_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
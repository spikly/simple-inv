<?php

$allCategories = [];

$sql = 'SELECT cat_id, cat_name, cat_slug FROM inv_categories ORDER BY cat_name asc';

$stmt = $db->prepare($sql);
$stmt->execute();

$allCategories = $stmt->fetchAll();
?>

<div class="flex-nav">
    <h2>
        Categories
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-cat">Add New Category</a>
    </nav>
</div>

<?php if(count($allCategories) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allCategories as $category): ?>
        <tr>
            <td><a href="index.php?page=items&category_id=<?php echo $category['cat_id']; ?>"><?php echo escapeHtml($category['cat_name']); ?></a></td>
            <td><a href="index.php?page=edit-cat&category_id=<?php echo $category['cat_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
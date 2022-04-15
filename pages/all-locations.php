<?php

$allLocations = [];

$sql = 'SELECT loc_id, loc_name FROM inv_locations ORDER BY loc_name asc';

$stmt = $db->prepare($sql);
$stmt->execute();

$allLocations = $stmt->fetchAll();
$locationCount = count($allLocations);
?>

<div class="flex-nav">
    <h2>
        Locations <span>(<?php echo $locationCount; ?> total)</span>
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-loc">Add New Location</a>
    </nav>
</div>

<?php if(count($allLocations) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allLocations as $location): ?>
        <tr>
            <td><a href="index.php?page=items&location_id=<?php echo $location['loc_id']; ?>"><?php echo escapeHtml($location['loc_name']); ?></a></td>
            <td><a href="index.php?page=edit-loc&location_id=<?php echo $location['loc_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
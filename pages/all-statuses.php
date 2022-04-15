<?php

$allStatuses = [];

$sql = 'SELECT status_id, status_name FROM inv_statuses ORDER BY status_name asc';

$stmt = $db->prepare($sql);
$stmt->execute();

$allStatuses = $stmt->fetchAll();
$statusCount = count($allStatuses);
?>

<div class="flex-nav">
    <h2>
        Statuses <span>(<?php echo $statusCount; ?> total)</span>
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-status">Add New Status</a>
    </nav>
</div>

<?php if(count($allStatuses) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allStatuses as $status): ?>
        <tr>
            <td><a href="index.php?page=items&status_id=<?php echo $status['status_id']; ?>"><?php echo escapeHtml($status['status_name']); ?></a></td>
            <td><a href="index.php?page=edit-status&status_id=<?php echo $status['status_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
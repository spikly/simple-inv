<?php

$allItems = [];

$sql = 'select i.item_id, i.item_name, c.cat_name, l.loc_name, s.status_name
from inv_items i
inner join inv_locations l on l.loc_id = i.item_loc_id
inner join inv_statuses s on s.status_id  = i.item_status
inner join categories_items ci on i.item_id = ci.item_id
inner join inv_categories c on ci.cat_id = c.cat_id';

$stmt = $pdo->prepare($sql);
$stmt->execute();

$allItems = $stmt->fetchAll();
?>
<nav class="onpage-nav">
    <a href="index.php?page=addnewitem">Add New Item</a>
</nav>

<?php if(count($allItems) > 0): ?>
<div class="search-box">
    <input type="text" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for items..">
</div>

<table id="searchableTable">
    <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Location</th>
        <th>Status</th>
        <th>Edit</th>
    </tr>
    <?php foreach($allItems as $item): ?>
        <tr>
            <td><?php echo $item['item_name']; ?></td>
            <td><?php echo $item['cat_name']; ?></td>
            <td><?php echo $item['loc_name']; ?></td>
            <td><?php echo $item['status_name']; ?></td>
            <td><a href="index.php?page=edititem&item_id=<?php echo $item['item_id']; ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
No items
<?php endif; ?>
<script>
    function searchTable() {
        // Declare variables
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("tableSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("searchableTable");
        tr = table.getElementsByTagName("tr");

        // Loop through all table rows, and hide those who don't match the search query
        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[0];
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>
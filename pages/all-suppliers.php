<?php

$allSuppliers = [];

$sql = 'SELECT sup_id, sup_name, sup_website FROM inv_suppliers ORDER BY sup_name asc';

$stmt = $db->prepare($sql);
$stmt->execute();

$allSuppliers = $stmt->fetchAll();
$supplierCount = count($allSuppliers);
?>

<div class="flex-nav">
    <h2>
        Suppliers <span><span><?php echo $supplierCount; ?></span></span>
    </h2>
    <nav class="onpage-nav">
        <a href="index.php?page=add-supplier">Add New Supplier</a>
    </nav>
</div>

<?php if(count($allSuppliers) > 0): ?>
<div class="search-box">
    <input type="search" id="tableSearchInput" onkeyup="searchTable()" placeholder="Search for suppliers...">
</div>

<div class="table-container">
    <table id="searchableTable">
        <tr>
            <th>Name</th>
            <th>Website</th>
            <th>Edit</th>
        </tr>
        <?php foreach($allSuppliers as $supplier): ?>
            <tr>
                <td><a href="index.php?page=items&supplier_id=<?php echo $supplier['sup_id']; ?>"><?php echo escapeHtml($supplier['sup_name']); ?></a></td>
                <td>
                    <?php if(!empty($supplier['sup_website'])): ?>
                        <a href="<?php echo escapeHtml($supplier['sup_website']); ?>" target="_blank">Visit Website</a>
                    <?php endif; ?>
                </td>
                <td><a href="index.php?page=edit-supplier&supplier_id=<?php echo $supplier['sup_id']; ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php else: ?>
No items
<?php endif; ?>
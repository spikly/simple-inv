<?php

$assembly_id = (int)($_GET['assembly_id'] ?? 0);

$assembly = fetchAssembly($assembly_id);

if(!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}

$items = fetchAssemblyItems($assembly_id);

?>

<div class="flex-nav">

<h2>
<?php echo escapeHtml($assembly['assembly_name']); ?>
</h2>

<nav class="onpage-nav">

<a href="index.php?page=add-assembly-item&assembly_id=<?php
    echo $assembly_id;
?>">
Add Part
</a>

<a href="index.php?page=edit-assembly&assembly_id=<?php
    echo $assembly_id;
?>">
Edit Assembly
</a>

<a href="index.php?page=view-project&project_id=<?php
    echo (int)$assembly['project_id'];
?>">
Back to Project
</a>

</nav>

</div>


<?php if($assembly['assembly_description']): ?>

<div class="notes-box">

<?php
echo nl2p(
    text2link(
        escapeHtml(
            $assembly['assembly_description']
        )
    )
);
?>

</div>

<?php endif; ?>


<div class="flex-nav extra-padding">

<h2>Parts</h2>

</div>


<?php if(count($items)): ?>

<div class="table-container">

<table>

<tr>

<th>Item</th>

<th>Required</th>

<th>Allocated</th>

<th>Installed</th>

<th>Remaining</th>

<th></th>

</tr>


<?php foreach($items as $item): ?>

<?php

$remaining =
    max(
        0,
        (float)$item['quantity_required'] -
        (float)$item['quantity_installed']
    );

?>

<tr>

<td>

<a href="index.php?page=view-item&item_id=<?php
    echo (int)$item['item_id'];
?>">

<?php
echo escapeHtml(
    $item['item_name']
);
?>

</a>

</td>


<td>
<?php echo $item['quantity_required']; ?>
</td>


<td>
<?php echo $item['quantity_allocated']; ?>
</td>


<td>
<?php echo $item['quantity_installed']; ?>
</td>


<td>
<?php echo $remaining; ?>
</td>


<td>

<a href="index.php?page=edit-assembly-item&assembly_item_id=<?php
    echo (int)$item['assembly_item_id'];
?>">
Edit
</a>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<?php else: ?>

<p>
No parts assigned to this assembly.
</p>

<?php endif; ?>


<?php if($assembly['assembly_notes']): ?>

<div class="flex-nav extra-padding">

<h2>Notes</h2>

</div>

<div class="notes-box">

<?php

echo nl2p(
    text2link(
        escapeHtml(
            $assembly['assembly_notes']
        )
    )
);

?>

</div>

<?php endif; ?>
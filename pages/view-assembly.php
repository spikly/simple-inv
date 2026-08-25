<?php

$assembly_id = (int)($_GET['assembly_id'] ?? 0);

$assembly = fetchAssembly($assembly_id);

if(!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}

$formMessage = false;


if(isset($_POST['duplicate_assembly_submit'])) {

    try {

        $db->beginTransaction();


        /*
         * Duplicate assembly
         */
        $sql = "
            INSERT INTO inv_project_assemblies
            (
                assembly_project_id,
                assembly_name,
                assembly_description,
                assembly_notes,
                assembly_sort_order
            )
            SELECT
                assembly_project_id,
                CONCAT(assembly_name, ' (Copy)'),
                assembly_description,
                assembly_notes,
                assembly_sort_order
            FROM inv_project_assemblies
            WHERE assembly_id = :assembly_id
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'assembly_id' => $assembly_id,
        ]);


        $new_assembly_id =
            (int)$db->lastInsertId();


        /*
         * Duplicate parts
         */
        $sql = "
            INSERT INTO inv_assembly_items
            (
                assembly_id,
                item_id,
                quantity_required,
                quantity_allocated,
                quantity_installed,
                assembly_item_notes
            )
            SELECT
                :new_assembly_id,
                item_id,
                quantity_required,
                0,
                0,
                assembly_item_notes
            FROM inv_assembly_items
            WHERE assembly_id = :old_assembly_id
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'new_assembly_id' => $new_assembly_id,
            'old_assembly_id' => $assembly_id,
        ]);


        $db->commit();


        $formMessage = [
            'status' => 'success',
            'message' =>
                'Assembly duplicated successfully. ' .
                '<a href="index.php?page=view-assembly&assembly_id=' .
                (int)$new_assembly_id .
                '">View the new assembly</a>.',
        ];


    } catch(\PDOException $e) {

        if($db->inTransaction()) {
            $db->rollBack();
        }

        throw new \PDOException(
            $e->getMessage(),
            (int)$e->getCode()
        );
    }
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

<form
    method="post"
    style="display:inline;"
    onsubmit="return confirm(
        'Duplicate this assembly and all of its parts?'
    );"
>

    <input
        type="submit"
        name="duplicate_assembly_submit"
        value="Duplicate Assembly"
    >

</form>

</nav>

</div>

<?php

echo ($formMessage)
    ? '<p class="form-message form-' .
        $formMessage['status'] .
        '">' .
        $formMessage['message'] .
        '</p>'
    : '';

?>

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
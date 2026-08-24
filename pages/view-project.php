<?php

$project_id = (int)($_GET['project_id'] ?? 0);

$project = fetchProject($project_id);

if(!$project) {

    echo '<p>Project not found.</p>';
    return;

}

$assemblies = fetchProjectAssemblies($project_id);

$summary = getProjectSummary($project_id);

?>

<div class="flex-nav">

    <h2>
        <?php echo escapeHtml($project['project_name']); ?>
    </h2>

    <nav class="onpage-nav">

        <a href="index.php?page=add-assembly&project_id=<?php
            echo $project_id;
        ?>">
            Add Assembly
        </a>

        <a href="index.php?page=edit-project&project_id=<?php
            echo $project_id;
        ?>">
            Edit
        </a>

    </nav>

</div>


<?php if($project['project_reference']): ?>

<p>
    <strong>Reference:</strong>
    <?php echo escapeHtml($project['project_reference']); ?>
</p>

<?php endif; ?>


<p>
    <strong>Status:</strong>
    <?php echo escapeHtml($project['project_status_name']); ?>
</p>


<?php if($project['project_description']): ?>

<div class="notes-box">

    <?php
    echo nl2p(
        text2link(
            escapeHtml(
                $project['project_description']
            )
        )
    );
    ?>

</div>

<?php endif; ?>


<div class="item-property-container">

    <div class="item-property">

        <h3>Required</h3>

        <p>
            <?php
            echo (float)$summary['required_quantity'];
            ?>
        </p>

    </div>


    <div class="item-property">

        <h3>Allocated</h3>

        <p>
            <?php
            echo (float)$summary['allocated_quantity'];
            ?>
        </p>

    </div>


    <div class="item-property">

        <h3>Installed</h3>

        <p>
            <?php
            echo (float)$summary['installed_quantity'];
            ?>
        </p>

    </div>

</div>


<div class="flex-nav extra-padding">

    <h2>Assemblies</h2>

    <nav class="onpage-nav">

        <a href="index.php?page=add-assembly&project_id=<?php
            echo $project_id;
        ?>">
            Add Assembly
        </a>

    </nav>

</div>


<?php if(count($assemblies)): ?>

<div class="table-container">

<table>

<tr>
    <th>Assembly</th>
    <th>Parts</th>
    <th>Required</th>
    <th>Installed</th>
    <th>Progress</th>
    <th></th>
</tr>


<?php foreach($assemblies as $assembly): ?>

<?php

$required =
    (float)$assembly['quantity_required'];

$installed =
    (float)$assembly['quantity_installed'];

$progress =
    $required > 0
        ? min(
            100,
            round(
                ($installed / $required) * 100
            )
        )
        : 0;

?>


<tr>

<td>

<a href="index.php?page=view-assembly&assembly_id=<?php
    echo (int)$assembly['assembly_id'];
?>">

<?php
echo escapeHtml(
    $assembly['assembly_name']
);
?>

</a>

</td>


<td>
<?php echo (int)$assembly['item_count']; ?>
</td>


<td>
<?php echo $required; ?>
</td>


<td>
<?php echo $installed; ?>
</td>


<td>
<?php echo $progress; ?>%
</td>


<td>

<a href="index.php?page=edit-assembly&assembly_id=<?php
    echo (int)$assembly['assembly_id'];
?>">
Edit
</a>

</td>

</tr>


<?php endforeach; ?>

</table>

</div>

<?php else: ?>

<p>No assemblies yet.</p>

<?php endif; ?>


<?php if($project['project_notes']): ?>

<div class="flex-nav extra-padding">

    <h2>Notes</h2>

</div>

<div class="notes-box">

<?php

echo nl2p(
    text2link(
        escapeHtml(
            $project['project_notes']
        )
    )
);

?>

</div>

<?php endif; ?>
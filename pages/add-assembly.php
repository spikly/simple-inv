<?php

$formData = [];
$formMessage = false;

$project_id = (int)($_GET['project_id'] ?? 0);

$project = fetchProject($project_id);

if(!$project) {
    echo '<p>Project not found.</p>';
    return;
}


if(isset($_POST['add_assembly_submit'])) {

    if(!empty($_POST['assembly_name'])) {

        $formData = [
            'assembly_project_id' => $project_id,
            'assembly_name' => trim($_POST['assembly_name']),
            'assembly_description' => trim($_POST['assembly_description']),
            'assembly_notes' => trim($_POST['assembly_notes']),
            'assembly_sort_order' => (int)$_POST['assembly_sort_order'],
        ];

        try {

            $sql = "
                INSERT INTO inv_project_assemblies
                (
                    assembly_project_id,
                    assembly_name,
                    assembly_description,
                    assembly_notes,
                    assembly_sort_order
                )
                VALUES
                (
                    :assembly_project_id,
                    :assembly_name,
                    :assembly_description,
                    :assembly_notes,
                    :assembly_sort_order
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Assembly added!',
            ];

            $formData = [];

        } catch(\PDOException $e) {

            throw new \PDOException(
                $e->getMessage(),
                (int)$e->getCode()
            );
        }

    } else {

        $formMessage = [
            'status' => 'error',
            'message' => 'Assembly name cannot be empty',
        ];
    }
}

?>

<div class="flex-nav">

    <h2>
        Add Assembly
    </h2>

    <nav class="onpage-nav">

    <a href="index.php?page=view-project&project_id=<?php
        echo $project_id;
    ?>">
    Back to Project
    </a>

    </nav>
</div>


<p>
    Project:
    <strong>
        <?php echo escapeHtml($project['project_name']); ?>
    </strong>
</p>


<form method="post">

    <?php

    echo ($formMessage)
        ? '<p class="form-message form-' .
            $formMessage['status'] .
            '">' .
            $formMessage['message'] .
            '</p>'
        : '';

    ?>


    <p>

        <label for="assembly_name">
            Assembly Name
        </label>

        <input
            type="text"
            name="assembly_name"
            id="assembly_name"
            value="<?php
                echo escapeHtml(
                    $formData['assembly_name'] ?? ''
                );
            ?>"
        />

    </p>


    <p>

        <label for="assembly_sort_order">
            Sort Order
        </label>

        <input
            type="number"
            name="assembly_sort_order"
            id="assembly_sort_order"
            value="<?php
                echo $formData['assembly_sort_order'] ?? 0;
            ?>"
        />

    </p>


    <p>

        <label for="assembly_description">
            Description
        </label>

        <textarea
            name="assembly_description"
            id="assembly_description"
        ><?php
            echo escapeHtml(
                $formData['assembly_description'] ?? ''
            );
        ?></textarea>

    </p>


    <p>

        <label for="assembly_notes">
            Notes
        </label>

        <textarea
            name="assembly_notes"
            id="assembly_notes"
        ><?php
            echo escapeHtml(
                $formData['assembly_notes'] ?? ''
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="add_assembly_submit"
            value="Save"
        >

    </p>

</form>
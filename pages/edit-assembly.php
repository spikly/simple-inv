<?php

$assembly_id = (int)($_GET['assembly_id'] ?? 0);

$assembly = fetchAssembly($assembly_id);

if(!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}


$formData = [
    'assembly_name' =>
        $assembly['assembly_name'],

    'assembly_description' =>
        $assembly['assembly_description'],

    'assembly_notes' =>
        $assembly['assembly_notes'],

    'assembly_sort_order' =>
        $assembly['assembly_sort_order'],
];

$formMessage = false;


if(isset($_POST['edit_assembly_submit'])) {

    if(!empty($_POST['assembly_name'])) {

        $formData = [
            'assembly_name' =>
                trim($_POST['assembly_name']),

            'assembly_description' =>
                trim($_POST['assembly_description']),

            'assembly_notes' =>
                trim($_POST['assembly_notes']),

            'assembly_sort_order' =>
                (int)$_POST['assembly_sort_order'],
        ];


        try {

            $sql = "
                UPDATE inv_project_assemblies

                SET
                    assembly_name = :assembly_name,
                    assembly_description = :assembly_description,
                    assembly_notes = :assembly_notes,
                    assembly_sort_order = :assembly_sort_order

                WHERE assembly_id = :assembly_id
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([

                'assembly_name' =>
                    $formData['assembly_name'],

                'assembly_description' =>
                    $formData['assembly_description']
                        ?: null,

                'assembly_notes' =>
                    $formData['assembly_notes']
                        ?: null,

                'assembly_sort_order' =>
                    $formData['assembly_sort_order'],

                'assembly_id' =>
                    $assembly_id,
            ]);


            $formMessage = [
                'status' => 'success',
                'message' => 'Assembly updated!',
            ];


            // Refresh the displayed data.
            $assembly = fetchAssembly($assembly_id);

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


if(isset($_POST['delete_assembly_submit'])) {

    try {

        $sql = "
            DELETE FROM inv_project_assemblies

            WHERE assembly_id = :assembly_id
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'assembly_id' => $assembly_id,
        ]);


        $formMessage = [
            'status' => 'success',
            'message' => 'Assembly deleted!',
        ];

    } catch(\PDOException $e) {

        throw new \PDOException(
            $e->getMessage(),
            (int)$e->getCode()
        );
    }
}

?>

<div class="flex-nav">

    <h2>
        Edit Assembly
    </h2>

    <nav class="onpage-nav">

    <a href="index.php?page=view-assembly&assembly_id=<?php
        echo $assembly_id;
    ?>">
    Back to Assembly
    </a>

    </nav>

</div>


<p>

    Project:

    <strong>
        <?php
        echo escapeHtml(
            $assembly['project_name']
        );
        ?>
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
                    $formData['assembly_name']
                );
            ?>"
            required
        >

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
                echo (int)$formData['assembly_sort_order'];
            ?>"
        >

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
                $formData['assembly_description']
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
                $formData['assembly_notes']
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="edit_assembly_submit"
            value="Save"
        >

    </p>

</form>


<hr>


<form
    method="post"
    onsubmit="return confirm(
        'Are you sure you want to delete this assembly? All parts assigned to it will also be removed.'
    );"
>

    <p>

        <input
            type="submit"
            name="delete_assembly_submit"
            value="Delete Assembly"
            class="delete"
        >

    </p>

</form>
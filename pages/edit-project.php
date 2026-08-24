<?php

$project_id = (int)($_GET['project_id'] ?? 0);

$project = fetchProject($project_id);

if(!$project) {
    echo '<p>Project not found.</p>';
    return;
}

$statuses = fetchProjectStatuses();

$formData = [
    'project_name' => $project['project_name'],
    'project_reference' => $project['project_reference'],
    'project_description' => $project['project_description'],
    'project_status_id' => $project['project_status_id'],
    'project_notes' => $project['project_notes'],
];

$formMessage = false;


if(isset($_POST['edit_project_submit'])) {

    if(!empty($_POST['project_name'])) {

        $formData = [
            'project_name' => trim($_POST['project_name']),
            'project_reference' => trim($_POST['project_reference']),
            'project_description' => trim($_POST['project_description']),
            'project_status_id' => (int)$_POST['project_status_id'],
            'project_notes' => trim($_POST['project_notes']),
        ];

        try {

            $sql = "
                UPDATE inv_projects

                SET
                    project_name = :project_name,
                    project_reference = :project_reference,
                    project_description = :project_description,
                    project_status_id = :project_status_id,
                    project_notes = :project_notes

                WHERE project_id = :project_id
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                'project_name' =>
                    $formData['project_name'],

                'project_reference' =>
                    $formData['project_reference'] ?: null,

                'project_description' =>
                    $formData['project_description'] ?: null,

                'project_status_id' =>
                    $formData['project_status_id'],

                'project_notes' =>
                    $formData['project_notes'] ?: null,

                'project_id' =>
                    $project_id,
            ]);

            $formMessage = [
                'status' => 'success',
                'message' => 'Project updated!',
            ];

        } catch(\PDOException $e) {

            throw new \PDOException(
                $e->getMessage(),
                (int)$e->getCode()
            );
        }

    } else {

        $formMessage = [
            'status' => 'error',
            'message' => 'Project name cannot be empty',
        ];
    }
}


if(isset($_POST['delete_project_submit'])) {

    try {

        $sql = "
            DELETE FROM inv_projects
            WHERE project_id = :project_id
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'project_id' => $project_id,
        ]);

        $formMessage = [
            'status' => 'success',
            'message' => 'Project deleted!',
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
        Edit Project
    </h2>

    <nav class="onpage-nav">

    <a href="index.php?page=view-project&project_id=<?php
        echo $project_id;
    ?>">
    Back to Project
    </a>

    </nav>

</div>


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

        <label for="project_name">
            Project Name
        </label>

        <input
            type="text"
            name="project_name"
            id="project_name"
            value="<?php
                echo escapeHtml(
                    $formData['project_name']
                );
            ?>"
            required
        >

    </p>


    <p>

        <label for="project_reference">
            Reference
        </label>

        <input
            type="text"
            name="project_reference"
            id="project_reference"
            value="<?php
                echo escapeHtml(
                    $formData['project_reference']
                );
            ?>"
        >

    </p>


    <p>

        <label for="project_status_id">
            Status
        </label>

        <select
            name="project_status_id"
            id="project_status_id"
        >

            <?php foreach($statuses as $status): ?>

                <option
                    value="<?php
                        echo (int)$status['project_status_id'];
                    ?>"
                    <?php
                    echo (
                        (int)$formData['project_status_id'] ===
                        (int)$status['project_status_id']
                    )
                        ? 'selected'
                        : '';
                    ?>
                >
                    <?php
                    echo escapeHtml(
                        $status['project_status_name']
                    );
                    ?>
                </option>

            <?php endforeach; ?>

        </select>

    </p>


    <p>

        <label for="project_description">
            Description
        </label>

        <textarea
            name="project_description"
            id="project_description"
        ><?php
            echo escapeHtml(
                $formData['project_description']
            );
        ?></textarea>

    </p>


    <p>

        <label for="project_notes">
            Notes
        </label>

        <textarea
            name="project_notes"
            id="project_notes"
        ><?php
            echo escapeHtml(
                $formData['project_notes']
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="edit_project_submit"
            value="Save"
        >

    </p>

</form>


<hr>


<form
    method="post"
    onsubmit="return confirm(
        'Are you sure you want to delete this project? This will also delete all assemblies and their parts.'
    );"
>

    <p>

        <input
            type="submit"
            name="delete_project_submit"
            value="Delete Project"
            class="delete"
        >

    </p>

</form>
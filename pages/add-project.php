<?php

$formData = [];
$formMessage = false;

$statuses = fetchProjectStatuses();

if(isset($_POST['add_project'])) {

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
                INSERT INTO inv_projects
                (
                    project_name,
                    project_reference,
                    project_description,
                    project_status_id,
                    project_notes
                )
                VALUES
                (
                    :project_name,
                    :project_reference,
                    :project_description,
                    :project_status_id,
                    :project_notes
                )
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Project added!',
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
            'message' => 'Project name cannot be empty',
        ];
    }
}

?>

<div class="flex-nav">

    <h2>
        Add Project
    </h2>

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
                    $formData['project_name'] ?? ''
                );
            ?>"
        />

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
                    $formData['project_reference'] ?? ''
                );
            ?>"
        />

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
                $formData['project_description'] ?? ''
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
                $formData['project_notes'] ?? ''
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="add_project"
            value="Save"
        >

    </p>

</form>
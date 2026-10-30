<?php

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_project'])) {
    $errors = validateProject($_POST);

    if ($errors) {
        $values = $_POST;
        $formMessage = errorMessage($errors);
    } else {
        $projectId = dbInsert(
            'INSERT INTO inv_projects
                (project_name, project_reference, project_description, project_status_id, project_notes)
             VALUES
                (:project_name, :project_reference, :project_description, :project_status_id, :project_notes)',
            projectColumns($_POST)
        );

        redirectWith('index.php?page=view-project&project_id=' . $projectId, successMessage('Project added!'));
    }
}

template('page/add-project', compact('values', 'formMessage'));

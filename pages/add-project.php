<?php

$values = [];
$formMessage = false;

if (isset($_POST['add_project'])) {
    if (trim($_POST['project_name']) === '') {
        $values = $_POST;
        $formMessage = errorMessage('Project name cannot be empty');
    } else {
        $columns = projectColumns($_POST);

        dbRun(
            'INSERT INTO inv_projects
                (project_name, project_reference, project_description, project_status_id, project_notes)
             VALUES
                (:project_name, :project_reference, :project_description, :project_status_id, :project_notes)',
            $columns
        );

        $formMessage = successMessage('Project added!');
    }
}

pageHeader('Add Project');
renderProjectForm($values, 'add_project', $formMessage);

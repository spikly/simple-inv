<?php

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_project'])) {
    if (trim($_POST['project_name']) === '') {
        $values = $_POST;
        $formMessage = errorMessage('Project name cannot be empty');
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

pageHeader('Add Project', ['Back to Projects' => 'index.php?page=projects']);
renderProjectForm($values, 'add_project', $formMessage);

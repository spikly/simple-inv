<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

if (!$project) {
    echo '<p>Project not found.</p>';
    return;
}

$values = $project;
$formMessage = false;

if (isset($_POST['edit_project_submit'])) {
    $values = $_POST;

    if (trim($_POST['project_name']) === '') {
        $formMessage = errorMessage('Project name cannot be empty');
    } else {
        dbRun(
            'UPDATE inv_projects SET
                project_name = :project_name,
                project_reference = :project_reference,
                project_description = :project_description,
                project_status_id = :project_status_id,
                project_notes = :project_notes
             WHERE project_id = :project_id',
            projectColumns($_POST) + ['project_id' => $projectId]
        );

        $formMessage = successMessage('Project updated!');
    }
}

if (isset($_POST['delete_project_submit'])) {
    dbRun('DELETE FROM inv_projects WHERE project_id = :project_id', ['project_id' => $projectId]);

    $formMessage = successMessage('Project deleted!');
}

pageHeader('Edit Project', ['Back to Project' => 'index.php?page=view-project&project_id=' . $projectId]);

renderProjectForm($values, 'edit_project_submit', $formMessage);

confirmDeleteForm(
    'delete_project_submit',
    'Delete Project',
    'Are you sure you want to delete this project? This will also delete all assemblies and their parts.'
);

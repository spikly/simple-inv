<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

if (!$project) {
    template('page/edit-project', ['project' => false]);
    return;
}

$values = $project;
$formMessage = takeFlash();

if (isset($_POST['edit_project_submit'])) {
    $values = $_POST;

    if (trim($_POST['project_name']) === '') {
        $formMessage = errorMessage(['project_name' => 'Project name cannot be empty']);
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

        redirectWith('index.php?page=edit-project&project_id=' . $projectId, successMessage('Project updated!'));
    }
} elseif (isset($_POST['delete_project_submit'])) {
    // Its assemblies and their parts go with it, freeing the stock they held.
    dbTransaction(function () use ($projectId) {
        $itemIds = fetchProjectItemIds($projectId);

        dbRun('DELETE FROM inv_projects WHERE project_id = :project_id', ['project_id' => $projectId]);

        reallocateItems($itemIds);
    });

    redirectWith('index.php?page=projects', successMessage('Project deleted!'));
}

template('page/edit-project', compact('project', 'values', 'projectId', 'formMessage'));

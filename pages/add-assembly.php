<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

if (!$project) {
    template('page/add-assembly', ['project' => false]);
    return;
}

$values = [];
$formMessage = takeFlash();

if (isset($_POST['add_assembly_submit'])) {
    if (trim($_POST['assembly_name']) === '') {
        $values = $_POST;
        $formMessage = errorMessage(['assembly_name' => 'Assembly name cannot be empty']);
    } else {
        $assemblyId = dbInsert(
            'INSERT INTO inv_project_assemblies
                (assembly_project_id, assembly_name, assembly_description, assembly_notes, assembly_sort_order)
             VALUES
                (:assembly_project_id, :assembly_name, :assembly_description, :assembly_notes, :assembly_sort_order)',
            assemblyColumns($_POST) + ['assembly_project_id' => $projectId]
        );

        redirectWith('index.php?page=view-assembly&assembly_id=' . $assemblyId, successMessage('Assembly added!'));
    }
}

template('page/add-assembly', compact('project', 'values', 'projectId', 'formMessage'));

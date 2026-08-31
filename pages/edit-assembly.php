<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    template('page/edit-assembly', ['assembly' => false]);
    return;
}

$formMessage = takeFlash();

if (isset($_POST['edit_assembly_submit'])) {
    if (trim($_POST['assembly_name']) === '') {
        $assembly = $_POST + $assembly;
        $formMessage = errorMessage(['assembly_name' => 'Assembly name cannot be empty']);
    } else {
        dbRun(
            'UPDATE inv_project_assemblies SET
                assembly_name = :assembly_name,
                assembly_description = :assembly_description,
                assembly_notes = :assembly_notes,
                assembly_sort_order = :assembly_sort_order
             WHERE assembly_id = :assembly_id',
            assemblyColumns($_POST) + ['assembly_id' => $assemblyId]
        );

        redirectWith('index.php?page=edit-assembly&assembly_id=' . $assemblyId, successMessage('Assembly updated!'));
    }
} elseif (isset($_POST['delete_assembly_submit'])) {
    // Its parts go too, so the stock they held is shared out again.
    dbTransaction(function () use ($assemblyId) {
        $itemIds = fetchAssemblyItemIds($assemblyId);

        dbRun('DELETE FROM inv_project_assemblies WHERE assembly_id = :assembly_id', [
            'assembly_id' => $assemblyId,
        ]);

        reallocateItems($itemIds);
    });

    redirectWith(
        'index.php?page=view-project&project_id=' . (int)$assembly['project_id'],
        successMessage('Assembly deleted!')
    );
}

template('page/edit-assembly', compact('assembly', 'assemblyId', 'formMessage'));

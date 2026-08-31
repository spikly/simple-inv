<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    template('page/view-assembly', ['assembly' => false]);
    return;
}

$formMessage = takeFlash();

if (isset($_POST['duplicate_assembly_submit'])) {
    try {
        db()->beginTransaction();

        $newAssemblyId = dbInsert("
            INSERT INTO inv_project_assemblies
                (assembly_project_id, assembly_name, assembly_description, assembly_notes, assembly_sort_order)
            SELECT
                assembly_project_id, CONCAT(assembly_name, ' (Copy)'), assembly_description,
                assembly_notes, assembly_sort_order
            FROM inv_project_assemblies
            WHERE assembly_id = :assembly_id
        ", ['assembly_id' => $assemblyId]);

        dbRun('
            INSERT INTO inv_assembly_items
                (assembly_id, item_id, quantity_required, quantity_allocated, quantity_installed, assembly_item_notes)
            SELECT :new_assembly_id, item_id, quantity_required, 0, 0, assembly_item_notes
            FROM inv_assembly_items
            WHERE assembly_id = :old_assembly_id
        ', ['new_assembly_id' => $newAssemblyId, 'old_assembly_id' => $assemblyId]);

        // The copy starts holding nothing, so free stock is shared out again.
        reallocateItems(fetchAssemblyItemIds($newAssemblyId));

        db()->commit();

        redirectWith(
            'index.php?page=view-assembly&assembly_id=' . (int)$newAssemblyId,
            successMessage('Assembly duplicated. You are looking at the copy.')
        );
    } catch (\PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        throw $e;
    }
}

$slice = paginate(countAssemblyItems($assemblyId));

template('page/view-assembly', [
    'assembly'    => $assembly,
    'items'       => fetchAssemblyItems($assemblyId, $slice),
    'slice'       => $slice,
    'assemblyId'  => $assemblyId,
    'formMessage' => $formMessage,
]);

<?php

$assemblyId = queryId('assembly_id');
$assembly = fetchAssembly($assemblyId);

if (!$assembly) {
    echo '<p>Assembly not found.</p>';
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

        // The copy starts holding nothing, so each item shares its free stock
        // out again across the original and the copy.
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
$items = fetchAssemblyItems($assemblyId, $slice);

$duplicateForm = '<form method="post" style="display:inline;"'
    . ' onsubmit="return confirm(' . jsString('Duplicate this assembly and all of its parts?') . ');">'
    . '<input type="submit" name="duplicate_assembly_submit" value="Duplicate Assembly">'
    . '</form>';

pageHeader(escapeHtml($assembly['assembly_name']), [
    'Add Part'        => 'index.php?page=add-assembly-item&assembly_id=' . $assemblyId,
    'Edit Assembly'   => 'index.php?page=edit-assembly&assembly_id=' . $assemblyId,
    'Back to Project' => 'index.php?page=view-project&project_id=' . (int)$assembly['project_id'],
], $duplicateForm);

formMessage($formMessage);

if ($assembly['assembly_description']) {
    notesBox($assembly['assembly_description']);
}

sectionHeader('Parts' . countBadge($slice['total']));

if ($items) {
    renderTable(
        ['Item', 'Required', 'Allocated', 'Installed', 'Remaining', ''],
        $items,
        function ($item) {
            // Anything the item could not spare, so a part that is waiting on
            // stock says so where it is being looked at.
            $outstanding = max(0, (float)$item['quantity_required'] - (float)$item['quantity_installed']);
            $short = $outstanding - (float)$item['quantity_allocated'];

            return [
                '<a href="index.php?page=view-item&item_id=' . (int)$item['item_id'] . '">'
                    . escapeHtml($item['item_name']) . '</a>',
                formatQuantity($item['quantity_required']),
                formatQuantity($item['quantity_allocated'])
                    . ($short > 0 ? '<small class="row-note">' . formatQuantity($short)
                        . ' short of stock</small>' : ''),
                formatQuantity($item['quantity_installed']),
                formatQuantity($outstanding),
                '<a href="index.php?page=edit-assembly-item&assembly_item_id='
                    . (int)$item['assembly_item_id'] . '">Edit</a>',
            ];
        }
    );

    renderPagination($slice, 'parts');
} else {
    echo '<p>No parts assigned to this assembly.</p>' . "\n";
}

if ($assembly['assembly_notes']) {
    sectionHeader('Notes');
    notesBox($assembly['assembly_notes']);
}

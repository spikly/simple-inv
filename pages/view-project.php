<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

if (!$project) {
    echo '<p>Project not found.</p>';
    return;
}

$assemblies = fetchProjectAssemblies($projectId);
$summary = getProjectSummary($projectId);

$addAssemblyLink = 'index.php?page=add-assembly&project_id=' . $projectId;

pageHeader(escapeHtml($project['project_name']), [
    'Add Assembly'  => $addAssemblyLink,
    'Shopping List' => 'index.php?page=shopping-list&project_id=' . $projectId,
    'Edit'          => 'index.php?page=edit-project&project_id=' . $projectId,
]);

formMessage(takeFlash());

if ($project['project_reference']) {
    echo '<p><strong>Reference:</strong> ' . escapeHtml($project['project_reference']) . '</p>' . "\n";
}

echo '<p><strong>Status:</strong> ' . escapeHtml($project['project_status_name']) . '</p>' . "\n";

if ($project['project_description']) {
    notesBox($project['project_description']);
}

echo '<div class="item-property-container">' . "\n";

foreach (['Required' => 'required_quantity', 'Allocated' => 'allocated_quantity', 'Installed' => 'installed_quantity'] as $heading => $column) {
    itemProperty($heading, '<p>' . formatQuantity($summary[$column]) . '</p>');
}

echo '</div>' . "\n";

sectionHeader('Assemblies', ['Add Assembly' => $addAssemblyLink]);

if ($assemblies) {
    renderTable(
        ['Assembly', 'Parts', 'Required', 'Installed', 'Progress', ''],
        $assemblies,
        function ($assembly) {
            $id = (int)$assembly['assembly_id'];
            $required = (float)$assembly['quantity_required'];
            $installed = (float)$assembly['quantity_installed'];
            $progress = $required > 0 ? min(100, round(($installed / $required) * 100)) : 0;

            return [
                '<a href="index.php?page=view-assembly&assembly_id=' . $id . '">'
                    . escapeHtml($assembly['assembly_name']) . '</a>',
                (int)$assembly['item_count'],
                formatQuantity($required),
                formatQuantity($installed),
                $progress . '%',
                '<a href="index.php?page=edit-assembly&assembly_id=' . $id . '">Edit</a>',
            ];
        }
    );
} else {
    echo '<p>No assemblies yet.</p>' . "\n";
}

if ($project['project_notes']) {
    sectionHeader('Notes');
    notesBox($project['project_notes']);
}

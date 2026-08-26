<?php

$slice = paginate(countProjects());
$projects = fetchProjects($slice);

pageHeader('Projects' . countBadge($slice['total']), ['Add Project' => 'index.php?page=add-project']);

formMessage(takeFlash());

if (!$projects) {
    echo '<p>No projects have been created.</p>';
    return;
}

renderTable(
    ['Project', 'Reference', 'Status', 'Assemblies', 'Required', 'Installed', ''],
    $projects,
    function ($project) {
        $id = (int)$project['project_id'];

        return [
            '<a href="index.php?page=view-project&project_id=' . $id . '">'
                . escapeHtml($project['project_name']) . '</a>',
            escapeHtml($project['project_reference']),
            escapeHtml($project['project_status_name']),
            (int)$project['assembly_count'],
            formatQuantity($project['required_quantity']),
            formatQuantity($project['installed_quantity']),
            '<a href="index.php?page=edit-project&project_id=' . $id . '">Edit</a>',
        ];
    }
);

renderPagination($slice, 'projects');

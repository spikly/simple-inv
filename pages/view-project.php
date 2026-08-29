<?php

$projectId = queryId('project_id');
$project = fetchProject($projectId);

$view = [
    'project'        => $project,
    'projectId'      => $projectId,
    'addAssemblyUrl' => 'index.php?page=add-assembly&project_id=' . $projectId,
    'assemblies'     => [],
    'assemblySlice'  => [],
    'summary'        => [],
];

if ($project) {
    $view['assemblySlice'] = paginate(countProjectAssemblies($projectId));
    $view['assemblies'] = fetchProjectAssemblies($projectId, $view['assemblySlice']);
    $view['summary'] = getProjectSummary($projectId);
}

template('page/view-project', $view);

<?php

$slice = paginate(countProjects());

template('page/all-projects', [
    'projects' => fetchProjects($slice),
    'slice'    => $slice,
]);

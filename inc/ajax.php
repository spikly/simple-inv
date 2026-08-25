<?php

header('Content-Type: application/json');

require __DIR__ . '/bootstrap.php';

$request = json_decode(file_get_contents('php://input'), true) ?: [];

/**
 * The item form names its controls after the taxonomy they edit, so
 * "add_new_brand" and "item_brand" both resolve to the "brand" taxonomy.
 */
function taxonomyFor(?string $control, string $prefix): ?string
{
    $key = substr((string)$control, strlen($prefix));

    return isset(taxonomies()[$key]) ? $key : null;
}

switch ($request['requestType'] ?? '') {

    case 'load-form':
        $key = taxonomyFor($request['buttonId'] ?? null, 'add_new_');

        if (!$key) {
            echo json_encode(['error' => 'Invalid request. No button ID provided.']);
            break;
        }

        echo json_encode([
            'success'  => true,
            'formHtml' => taxonomyModalForm($key),
            'selectId' => 'item_' . $key,
        ]);
        break;

    case 'submit-form':
        $key = taxonomyFor($request['formId'] ?? null, 'item_');

        echo json_encode(
            $key
                ? taxonomyInsert($key, $request['formData'] ?? [])
                : ['success' => false, 'error' => 'Unknown form.']
        );
        break;

    case 'get-downdown-options':
        $key = taxonomyFor($request['dropdownId'] ?? null, 'item_');
        $multiple = !empty($request['multiple']);

        // Keep what was already chosen and add whatever was just created.
        $selected = $request['selected'] ?? [];
        $selected = is_array($selected) ? $selected : [$selected];
        $selected[] = $request['newId'] ?? null;

        echo json_encode([
            'success'     => true,
            'optionsHtml' => ($multiple ? '' : '<option value="0">Select</option>')
                . ($key ? selectOptions(taxonomyOptions($key), array_filter($selected, 'strlen')) : ''),
        ]);
        break;
}

exit;

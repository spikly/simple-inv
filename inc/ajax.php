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

        echo json_encode([
            'success'     => true,
            'optionsHtml' => '<option>Select</option>'
                . ($key ? selectOptions(taxonomyOptions($key), $request['newId'] ?? null) : ''),
        ]);
        break;
}

exit;

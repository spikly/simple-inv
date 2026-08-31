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

/**
 * The kind of item the form doing the asking is for. A category added from
 * the item form has to file that kind, and the category dropdown only ever
 * offers the categories that do.
 */
$requestedType = itemType($request['itemType'] ?? 'part');

switch ($request['requestType'] ?? '') {

    case 'load-form':
        $key = taxonomyFor($request['buttonId'] ?? null, 'add_new_');

        if (!$key) {
            echo json_encode(['error' => 'Invalid request. No button ID provided.']);
            break;
        }

        echo json_encode([
            'success'  => true,
            'formHtml' => taxonomyModalForm(
                $key,
                $key === 'category' ? ['cat_type' => $requestedType] : []
            ),
            'selectId' => 'item_' . $key,
        ]);
        break;

    case 'submit-form':
        $key = taxonomyFor($request['formId'] ?? null, 'item_');
        $formData = $request['formData'] ?? [];

        if ($key === 'category') {
            $formData['cat_type'] = $requestedType;
        }

        echo json_encode(
            $key
                ? taxonomyInsert($key, $formData)
                : ['success' => false, 'error' => 'Unknown form.']
        );
        break;

    case 'get-downdown-options':
        $key = taxonomyFor($request['dropdownId'] ?? null, 'item_');
        $multiple = !empty($request['multiple']);

        $newId = $request['newId'] ?? null;
        $selected = $request['selected'] ?? [];
        $selected = is_array($selected) ? $selected : [$selected];

        if (!$multiple && $newId !== null && $newId !== '') {
            $selected = [];
        }

        $selected[] = $newId;
        $selected = array_filter($selected, function ($value) {
            return $value !== null && $value !== '';
        });

        $options = [];

        if ($key === 'category') {
            $options = categoryOptions($requestedType);
        } elseif ($key) {
            $options = taxonomyOptions($key);
        }

        echo json_encode([
            'success'     => true,
            'optionsHtml' => ($multiple ? '' : '<option value="0">Select</option>')
                . selectOptions($options, $selected),
        ]);
        break;
}

exit;

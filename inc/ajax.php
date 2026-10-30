<?php

header('Content-Type: application/json');

require __DIR__ . '/bootstrap.php';

// The usual error page is HTML, which the script cannot read.
set_exception_handler(function (\Throwable $e) {
    error_log('[inventory] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    ajaxFailure(500, 'Something went wrong on the server.');
});

/** A request that cannot be answered. Validation failures are not this: they come back as success false. */
function ajaxFailure(int $status, string $error): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

$request = json_decode(file_get_contents('php://input'), true) ?: [];

/** "add_new_brand" and "item_brand" both resolve to the "brand" taxonomy. */
function taxonomyFor(?string $control, string $prefix): ?string
{
    $control = (string)$control;

    if (strpos($control, $prefix) !== 0) {
        return null;
    }

    $key = substr($control, strlen($prefix));

    return isset(taxonomies()[$key]) ? $key : null;
}

// A category added from the item form takes that item's kind.
$requestedType = itemType($request['itemType'] ?? 'part');

switch ($request['requestType'] ?? '') {

    case 'load-form':
        $key = taxonomyFor($request['buttonId'] ?? null, 'add_new_');

        if (!$key) {
            ajaxFailure(400, 'Invalid request. No button ID provided.');
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

        if (!$key) {
            ajaxFailure(400, 'Unknown dropdown.');
        }

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

        $options = ($key === 'category') ? categoryOptions($requestedType) : taxonomyOptions($key);

        echo json_encode([
            'success'     => true,
            'optionsHtml' => ($multiple ? '' : '<option value="0">Select</option>')
                . selectOptions($options, $selected),
        ]);
        break;

    default:
        ajaxFailure(400, 'Unknown request.');
}

exit;

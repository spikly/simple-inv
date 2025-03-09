<?php
header("Content-Type: application/json");

$db = require __DIR__ . '/utils.php';
$config = require __DIR__ . '/../config/user.config.php';
$db = require __DIR__ . '/db.php';

$rawData = file_get_contents("php://input");
$requestData = json_decode($rawData, true);

if($requestData['requestType'] == 'load-form') {
    if (!isset($requestData['buttonId'])) {
        echo json_encode(["error" => "Invalid request. No button ID provided."]);
        exit;
    }

    $idToTemplateMap = [
        'add_new_brand' => [
            'template' => '../forms/view/new-brand.phtml',
            'selectId' => 'item_brand',
        ],
        'add_new_supplier' => [
            'template' => '../forms/view/new-supplier.phtml',
            'selectId' => 'item_supplier',
        ],
        'add_new_category' => [
            'template' => '../forms/view/new-category.phtml',
            'selectId' => 'item_category',
        ],
        'add_new_location' => [
            'template' => '../forms/view/new-location.phtml',
            'selectId' => 'item_location',
        ],
        'add_new_status' => [
            'template' => '../forms/view/new-status.phtml',
            'selectId' => 'item_status',
        ],
    ];

    ob_start();
    require $idToTemplateMap[htmlspecialchars($requestData['buttonId'])]['template'];
    $formHtml = ob_get_clean();

    echo json_encode([
        "success" => true,
        "formHtml" => $formHtml,
        "selectId" => $idToTemplateMap[htmlspecialchars($requestData['buttonId'])]['selectId'],
    ]);
}

if($requestData['requestType'] == 'submit-form') {

    require __DIR__ . '/../forms/form-processor.php';

    switch ($requestData['formId']) {
        case 'item_brand':
            $result = addNewBrand($db, $requestData['formData']);
            break;
        case 'item_supplier':
            $result = addNewSupplier($db, $requestData['formData']);
            break;
        case 'item_category':
            $result = addNewCategory($db, $requestData['formData']);
            break;
        case 'item_location':
            $result = addNewLocation($db, $requestData['formData']);
            break;
        case 'item_status':
            $result = addNewStatus($db, $requestData['formData']);
            break;
    }

    echo json_encode($result);
}

if($requestData['requestType'] == 'get-downdown-options') {

    $selectOptions = [];
    $optionsHtml = '<option>Select</option>';

    switch ($requestData['dropdownId']) {
        case 'item_brand':
            $sql = 'SELECT brand_id AS option_id, brand_name AS option_name FROM inv_brands ORDER BY brand_name asc';
            break;
        case 'item_supplier':
            $sql = 'SELECT sup_id AS option_id, sup_name AS option_name FROM inv_suppliers ORDER BY sup_name asc';
            break;
        case 'item_category':
            $sql = 'SELECT cat_id AS option_id, cat_name AS option_name FROM inv_categories ORDER BY cat_name asc';
            break;
        case 'item_location':
            $sql = 'SELECT loc_id AS option_id, loc_name AS option_name FROM inv_locations ORDER BY loc_name asc';
            break;
        case 'item_status':
            $sql = 'SELECT status_id AS option_id, status_name AS option_name FROM inv_statuses ORDER BY status_name asc';
            break;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $selectOptions = $stmt->fetchAll();

    foreach ($selectOptions as $option) {
        $optionsHtml .= '<option value="' . $option['option_id'] . '">' . $option['option_name'] . '</option>';
    }


    echo json_encode([
        "success" => true,
        "optionsHtml" => $optionsHtml,
    ]);
}

exit;

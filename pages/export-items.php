<?php

$allItems = [];
$params = [];
$clauses = [];

$sql = 'SELECT i.item_id, i.item_name, b.brand_name, c.cat_name, l.loc_name, s.status_name, i.item_quantity, d.item_deployed_count, i.item_notes
FROM inv_items i
LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
LEFT JOIN inv_statuses s ON s.status_id  = i.item_status
LEFT JOIN categories_items ci ON i.item_id = ci.item_id
LEFT JOIN inv_categories c ON ci.cat_id = c.cat_id
LEFT JOIN (select dep_item_id, sum(dep_quantity) as item_deployed_count from inv_deployments group by dep_item_id) d on i.item_id = d.dep_item_id';

if(isset($_GET['brand_id'])) {
    $clauses[] = 'i.item_brand_id = :brand_id';
    $params['brand_id'] = $_GET['brand_id'];
}

if(isset($_GET['category_id'])) {
    $clauses[] = 'ci.cat_id = :category_id';
    $params['category_id'] = $_GET['category_id'];
}

if(isset($_GET['location_id'])) {
    $clauses[] = 'i.item_loc_id = :location_id';
    $params['location_id'] = $_GET['location_id'];
}

if(isset($_GET['status_id'])) {
    $clauses[] = 'i.item_status = :status_id';
    $params['status_id'] = $_GET['status_id'];
}

if(count($clauses) > 0) {
    $sql .= 'WHERE ' . implode(' AND ', $clauses);
}

$sql .= ' ORDER BY item_name asc';
$stmt = $db->prepare($sql);
$stmt->execute($params);

$allItems = $stmt->fetchAll();

// Open the temporary file for writing
$tempFile = tempnam(sys_get_temp_dir(), 'csv');
$fileHandle = fopen($tempFile, 'w');

fputcsv($fileHandle, ['Item ID', 'Name', 'Brand', 'Category', 'Location', 'Status', 'Quantity', 'Deployed', 'Notes']);

foreach ($allItems as $row) {
    fputcsv($fileHandle, $row);
}

fclose($fileHandle);

$filename = 'inventory-export_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Description: File Transfer');
header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($tempFile));

readfile($tempFile);
unlink($tempFile);
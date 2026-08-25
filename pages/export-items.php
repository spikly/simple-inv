<?php

[$where, $params] = itemFilters();

$items = fetchItemsForExport($where, $params);

$tempFile = tempnam(sys_get_temp_dir(), 'csv');
$fileHandle = fopen($tempFile, 'w');

fputcsv($fileHandle, [
    'Item ID', 'Name', 'Brand', 'Category', 'Location', 'Status', 'Quantity', 'Deployed', 'Notes',
], ',', '"', '\\');

foreach ($items as $item) {
    fputcsv($fileHandle, $item, ',', '"', '\\');
}

fclose($fileHandle);

header('Content-Description: File Transfer');
header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="inventory-export_' . date('Y-m-d_H-i-s') . '.csv"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($tempFile));

readfile($tempFile);
unlink($tempFile);

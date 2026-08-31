<?php

/**
 * CSV of the current item filters. The columns match what the import page
 * expects, so an export can be edited and read straight back in.
 */

[$where, $params] = itemFilters();

$items = fetchItemsForExport($where, $params);

$tempFile = tempnam(sys_get_temp_dir(), 'csv');
$fileHandle = fopen($tempFile, 'w');

// The query aliases each column to its heading.
$headings = $items
    ? array_keys($items[0])
    : ['Name', 'Part No', 'Colour', 'Product URL', 'Manufacturer', 'Supplier', 'Categories',
       'Location', 'Status', 'Quantity', 'Min Quantity', 'Unit', 'Type', 'Allocated', 'Notes'];

fputcsv($fileHandle, $headings, ',', '"', '\\');

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

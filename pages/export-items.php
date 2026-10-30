<?php

/** Columns match the import format, so an export can be edited and imported back. */

[$where, $params] = itemFilters();

$items = fetchItemsForExport($where, $params);

// Free the session, or other tabs wait while the file downloads.
session_write_close();

$tempFile = tempnam(sys_get_temp_dir(), 'csv');
$fileHandle = fopen($tempFile, 'w');

// So Excel reads it as UTF-8 rather than garbling anything accented.
fwrite($fileHandle, "\xEF\xBB\xBF");

// The query aliases each column to its heading.
$headings = $items
    ? array_keys($items[0])
    : ['Name', 'Part No', 'Colour', 'Product URL', 'Manufacturer', 'Supplier', 'Categories',
       'Location', 'Status', 'Quantity', 'Min Quantity', 'Unit', 'Type', 'Allocated', 'Notes'];

fputcsv($fileHandle, $headings, ',', '"', '\\');

foreach ($items as $item) {
    fputcsv($fileHandle, array_map('csvSafeCell', $item), ',', '"', '\\');
}

fclose($fileHandle);

header('Content-Description: File Transfer');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory-export_' . date('Y-m-d_H-i-s') . '.csv"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($tempFile));

readfile($tempFile);
unlink($tempFile);

<?php

/**
 * Two steps: upload a file to see what it would do, then confirm.
 * The reviewed rows are held in the session between the two.
 */

$formMessage = takeFlash();
$preview = storedImportPreview();
$rows = $preview['rows'];

if ($preview['stale']) {
    $formMessage = errorMessage(
        'The import waiting here was prepared by an older version of this page, so it has been'
        . ' cleared rather than shown. Upload the file again to see a fresh preview.'
    );
}

if (isset($_POST['import_upload'])) {
    $result = parseItemCsv($_FILES['csv_file'] ?? []);

    if (isset($result['error'])) {
        clearImportPreview();
        $rows = [];
        $formMessage = errorMessage(['csv_file' => $result['error']]);
    } else {
        $rows = $result['rows'];
        storeImportPreview($rows);
        $formMessage = false;
    }
} elseif (isset($_POST['import_confirm'])) {
    if (!$rows) {
        $formMessage = errorMessage('There is nothing to import. Upload a file first.');
    } else {
        $counts = importItemRows($rows);
        clearImportPreview();

        redirectWith('index.php?page=items', successMessage(
            $counts['imported'] . ' ' . ($counts['imported'] === 1 ? 'item' : 'items') . ' imported.'
            . ($counts['skipped'] ? ' ' . $counts['skipped'] . ' skipped because of errors.' : '')
        ));
    }
} elseif (isset($_POST['import_cancel'])) {
    clearImportPreview();

    redirectWith('index.php?page=import-items', successMessage('Import cancelled.'));
}

$valid = array_filter($rows, function ($row) {
    return empty($row['error']);
});

$creates = [];

foreach ($valid as $row) {
    foreach ($row['creates'] as $create) {
        $creates[$create] = true;
    }
}

template('page/import-items', compact('rows', 'valid', 'creates', 'formMessage'));

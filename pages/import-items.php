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

pageHeader('Import Items', ['Back to Items' => 'index.php?page=items']);
formMessage($formMessage);

if (!$rows):
?>
<p>
    Upload a CSV with a heading row. The columns are the same ones
    <a href="index.php?page=export-items">Export</a> produces, so the easiest start is to export what you
    have, edit it in a spreadsheet, and bring it back.
</p>
<p>
    <strong>Name</strong>, <strong>Manufacturer</strong>, <strong>Categories</strong>,
    <strong>Location</strong> and <strong>Status</strong> are required. Put more than one category in a cell
    by separating them with <code>|</code>. Manufacturers, suppliers, categories, locations and statuses that
    do not exist yet are created. A file exported before manufacturers were renamed still works: its
    <strong>Brand</strong> column is read as <strong>Manufacturer</strong>.
    <strong>Unit</strong> must match a measurement unit's symbol or name.
    <strong>Type</strong> is <code>Part</code> or <code>Tool</code> and defaults to <code>Part</code>; it
    decides which kind any categories the file creates will file, and a row naming a category that already
    files the other kind is skipped. The Allocated column is ignored, because that comes from projects.
</p>
<form method="post" enctype="multipart/form-data">
<?php formRow('csv_file', 'CSV File',
    '<input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required'
    . invalidAttributes('csv_file') . '>'); ?>
    <p>
        <input type="submit" name="import_upload" value="Preview Import">
    </p>
</form>
<?php else: ?>
<p>
    <strong><?php echo count($valid); ?></strong> of <?php echo count($rows); ?> rows will be imported.
    Nothing has been written yet.
</p>

<?php if ($creates): ?>
    <p>These will be created as part of the import: <?php echo escapeHtml(implode(', ', array_keys($creates))); ?>.</p>
<?php endif; ?>

<?php
renderTable(
    ['Line', 'Name', 'Type', 'Manufacturer', 'Categories', 'Location', 'Status', 'Quantity', 'Result'],
    $rows,
    function ($row) {
        return [
            (int)$row['line'],
            escapeHtml($row['name']),
            ITEM_TYPES[$row['type']],
            escapeHtml($row['manufacturer']),
            escapeHtml(implode(', ', $row['categories'])),
            escapeHtml($row['location']),
            escapeHtml($row['status']),
            $row['type'] === 'tool' ? '-' : escapeHtml($row['quantity'] !== '' ? $row['quantity'] : '1'),
            $row['error']
                ? '<span class="stock stock-over">' . escapeHtml($row['error']) . '</span>'
                : '<span class="stock stock-ok">Will import</span>',
        ];
    }
);
?>

<form method="post">
    <p>
        <?php if ($valid): ?>
            <input type="submit" name="import_confirm" value="Import <?php echo count($valid); ?> Items">
        <?php endif; ?>
        <input type="submit" name="import_cancel" value="Cancel" class="delete">
    </p>
</form>
<?php endif; ?>

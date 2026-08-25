<?php

/**
 * Two steps: upload a file to see what it would do, then confirm.
 * The reviewed rows are held in the session between the two.
 */

$formMessage = takeFlash();
$rows = $_SESSION['import_rows'] ?? [];

if (isset($_POST['import_upload'])) {
    $result = parseItemCsv($_FILES['csv_file'] ?? []);

    if (isset($result['error'])) {
        unset($_SESSION['import_rows']);
        $rows = [];
        $formMessage = errorMessage($result['error']);
    } else {
        $_SESSION['import_rows'] = $rows = $result['rows'];
    }
} elseif (isset($_POST['import_confirm'])) {
    if (!$rows) {
        $formMessage = errorMessage('There is nothing to import. Upload a file first.');
    } else {
        $counts = importItemRows($rows);
        unset($_SESSION['import_rows']);

        redirectWith('index.php?page=items', successMessage(
            $counts['imported'] . ' ' . ($counts['imported'] === 1 ? 'item' : 'items') . ' imported.'
            . ($counts['skipped'] ? ' ' . $counts['skipped'] . ' skipped because of errors.' : '')
        ));
    }
} elseif (isset($_POST['import_cancel'])) {
    unset($_SESSION['import_rows']);

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
    <strong>Name</strong>, <strong>Brand</strong>, <strong>Categories</strong>, <strong>Location</strong> and
    <strong>Status</strong> are required. Put more than one category in a cell by separating them with
    <code>|</code>. Brands, suppliers, categories, locations and statuses that do not exist yet are created.
    <strong>Unit</strong> must match a measurement unit's symbol or name. Deployed and Allocated columns are
    ignored, because those come from deployments and projects.
</p>
<form method="post" enctype="multipart/form-data">
    <p>
        <label for="csv_file">CSV File</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
    </p>
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
    ['Line', 'Name', 'Brand', 'Categories', 'Location', 'Status', 'Quantity', 'Result'],
    $rows,
    function ($row) {
        return [
            (int)$row['line'],
            escapeHtml($row['name']),
            escapeHtml($row['brand']),
            escapeHtml(implode(', ', $row['categories'])),
            escapeHtml($row['location']),
            escapeHtml($row['status']),
            escapeHtml($row['quantity'] !== '' ? $row['quantity'] : '1'),
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

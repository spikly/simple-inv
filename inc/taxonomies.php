<?php

/**
 * The simple "lookup" entities (brand, supplier, category, location, status).
 *
 * Every one of them is a table with an id, a required name and the odd extra
 * column, so a single definition drives their listing, add and edit pages, the
 * item form dropdowns and the "add new" modal served over AJAX.
 *
 * fields is ordered; the first field is the required name column.
 */
function taxonomies(): array
{
    return [
        'brand' => [
            'label'      => 'Brand',
            'plural'     => 'Brands',
            'table'      => 'inv_brands',
            'id'         => 'brand_id',
            'param'      => 'brand_id',
            'itemFilter' => 'i.item_brand_id',
            'submit'     => 'brand',
            'routes'     => ['index' => 'brands', 'add' => 'add-brand', 'edit' => 'edit-brand'],
            'fields'     => ['brand_name' => 'Brand Name'],
        ],
        'supplier' => [
            'label'      => 'Supplier',
            'plural'     => 'Suppliers',
            'table'      => 'inv_suppliers',
            'id'         => 'sup_id',
            'param'      => 'supplier_id',
            'itemFilter' => 'i.item_sup_id',
            'submit'     => 'sup',
            'routes'     => ['index' => 'suppliers', 'add' => 'add-supplier', 'edit' => 'edit-supplier'],
            'fields'     => [
                'sup_name'    => 'Supplier Name',
                'sup_website' => ['label' => 'Supplier Website (optional)', 'type' => 'url'],
            ],
            'columns'    => ['Website' => 'supplierWebsiteCell'],
        ],
        'category' => [
            'label'      => 'Category',
            'plural'     => 'Categories',
            'table'      => 'inv_categories',
            'id'         => 'cat_id',
            'param'      => 'category_id',
            'itemFilter' => 'ci.cat_id',
            'submit'     => 'cat',
            'routes'     => ['index' => 'categories', 'add' => 'add-cat', 'edit' => 'edit-cat'],
            'fields'     => ['cat_name' => 'Category Name'],
            'derived'    => 'categorySlug',
        ],
        'location' => [
            'label'      => 'Location',
            'plural'     => 'Locations',
            'table'      => 'inv_locations',
            'id'         => 'loc_id',
            'param'      => 'location_id',
            'itemFilter' => 'i.item_loc_id',
            'submit'     => 'loc',
            'routes'     => ['index' => 'locations', 'add' => 'add-loc', 'edit' => 'edit-loc'],
            'fields'     => ['loc_name' => 'Location Name'],
        ],
        'status' => [
            'label'      => 'Status',
            'plural'     => 'Statuses',
            'table'      => 'inv_statuses',
            'id'         => 'status_id',
            'param'      => 'status_id',
            'itemFilter' => 'i.item_status',
            'submit'     => 'status',
            'routes'     => ['index' => 'statuses', 'add' => 'add-status', 'edit' => 'edit-status'],
            'fields'     => ['status_name' => 'Status Name'],
        ],
    ];
}

function taxonomy(string $key): array
{
    return taxonomies()[$key];
}

/** Extra listing column for suppliers. */
function supplierWebsiteCell(array $row): string
{
    return $row['sup_website']
        ? '<a href="' . escapeHtml($row['sup_website']) . '" target="_blank">Visit Website</a>'
        : '';
}

/** Columns written on save but not shown on the form. */
function categorySlug(array $values): array
{
    return ['cat_slug' => slugify($values['cat_name'])];
}

/** The required name column of a taxonomy. */
function taxonomyNameField(array $tax): string
{
    return array_key_first($tax['fields']);
}

/** All rows, ordered by name. */
function taxonomyRows(array $tax): array
{
    return dbAll('SELECT * FROM ' . $tax['table'] . ' ORDER BY ' . taxonomyNameField($tax) . ' asc');
}

/** Rows as a value => label map, for use in a <select>. */
function taxonomyOptions(string $key): array
{
    $tax = taxonomy($key);

    return array_column(taxonomyRows($tax), taxonomyNameField($tax), $tax['id']);
}

/**
 * Turn submitted input into the columns to write, or an error message when the
 * required name is missing.
 */
function taxonomyValues(array $tax, array $input): array
{
    $values = [];

    foreach ($tax['fields'] as $column => $field) {
        $values[$column] = trim($input[$column] ?? '');
    }

    if ($values[taxonomyNameField($tax)] === '') {
        return ['error' => $tax['label'] . ' name cannot be empty'];
    }

    if (isset($tax['derived'])) {
        $values += $tax['derived']($values);
    }

    return ['values' => $values];
}

/** Insert a new row. Returns the AJAX response shape used by the modal forms. */
function taxonomyInsert(string $key, array $input): array
{
    $tax = taxonomy($key);
    $result = taxonomyValues($tax, $input);

    if (isset($result['error'])) {
        return ['success' => false, 'error' => $result['error']];
    }

    $columns = array_keys($result['values']);

    try {
        $newId = dbInsert(
            'INSERT INTO ' . $tax['table'] . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (:' . implode(', :', $columns) . ')',
            $result['values']
        );
    } catch (\PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    return ['success' => true, 'newId' => $newId];
}

/**
 * The <p><label><input></p> blocks for a taxonomy's fields.
 */
function taxonomyFields(array $tax, array $values = []): void
{
    foreach ($tax['fields'] as $column => $field) {
        textField(
            $column,
            is_array($field) ? $field['label'] : $field,
            $values[$column] ?? '',
            is_array($field) ? $field['type'] : 'text'
        );
    }
}

/**
 * Add/edit form: fields plus a save button.
 */
function taxonomyForm(array $tax, string $action, array $values = [], $formMessage = false): void
{
    echo '<form method="post">' . "\n";
    formMessage($formMessage);
    taxonomyFields($tax, $values);
    submitButton($action . '_' . $tax['submit'] . '_submit');
    echo '</form>' . "\n";
}

/** The form loaded into the "add new" modal on the item pages. */
function taxonomyModalForm(string $key): string
{
    $tax = taxonomy($key);

    ob_start();
    echo '<h2>Add New ' . $tax['label'] . '</h2>' . "\n";
    taxonomyForm($tax, 'add');

    return ob_get_clean();
}

/**
 * Listing page: every row, searchable, linking through to the filtered items.
 */
function taxonomyIndexPage(string $key): void
{
    $tax = taxonomy($key);
    $rows = taxonomyRows($tax);
    $nameField = taxonomyNameField($tax);
    $extraColumns = $tax['columns'] ?? [];

    pageHeader(
        $tax['plural'] . countBadge(count($rows)),
        ['Add New ' . $tax['label'] => 'index.php?page=' . $tax['routes']['add']]
    );

    if (!$rows) {
        echo 'No items' . "\n";
        return;
    }

    searchBox('Search for ' . strtolower($tax['plural']) . '...');

    renderTable(
        array_merge(['Name'], array_keys($extraColumns), ['Edit']),
        $rows,
        function ($row) use ($tax, $nameField, $extraColumns) {
            $id = $row[$tax['id']];

            $cells = ['<a href="index.php?page=items&' . $tax['param'] . '=' . $id . '">'
                . escapeHtml($row[$nameField]) . '</a>'];

            foreach ($extraColumns as $render) {
                $cells[] = $render($row);
            }

            $cells[] = '<a href="index.php?page=' . $tax['routes']['edit'] . '&' . $tax['param'] . '=' . $id . '">Edit</a>';

            return $cells;
        },
        true
    );
}

/**
 * Add page.
 */
function taxonomyAddPage(string $key): void
{
    $tax = taxonomy($key);
    $formMessage = false;

    if (isset($_POST['add_' . $tax['submit'] . '_submit'])) {
        $result = taxonomyInsert($key, $_POST);

        $formMessage = $result['success']
            ? successMessage($tax['label'] . ' added!')
            : errorMessage($result['error']);
    }

    pageHeader('Add ' . $tax['label']);
    taxonomyForm($tax, 'add', [], $formMessage);
}

/**
 * Edit page, including deletion.
 */
function taxonomyEditPage(string $key): void
{
    $tax = taxonomy($key);
    $editId = queryParam($tax['param']);
    $formMessage = false;
    $deleted = false;

    if (isset($_POST['edit_' . $tax['submit'] . '_submit'])) {
        $result = taxonomyValues($tax, $_POST);

        if (isset($result['error'])) {
            $formMessage = errorMessage($result['error']);
        } else {
            $assignments = [];

            foreach (array_keys($result['values']) as $column) {
                $assignments[] = $column . ' = :' . $column;
            }

            dbRun(
                'UPDATE ' . $tax['table'] . ' SET ' . implode(', ', $assignments)
                . ' WHERE ' . $tax['id'] . ' = :edit_id',
                $result['values'] + ['edit_id' => $editId]
            );

            $formMessage = successMessage($tax['label'] . ' updated!');
        }
    } elseif (isset($_POST['delete_' . $tax['submit'] . '_submit'])) {
        dbRun('DELETE FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :edit_id LIMIT 1', ['edit_id' => $editId]);

        $formMessage = successMessage($tax['label'] . ' deleted!');
        $deleted = true;
    }

    $row = dbRow('SELECT * FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :edit_id', ['edit_id' => $editId]);

    pageHeader('Edit ' . $tax['label']);

    if ($row) {
        taxonomyForm($tax, 'edit', $row, $formMessage);
        deleteSection($tax['label'], 'delete_' . $tax['submit'] . '_submit');
    } elseif ($deleted) {
        formMessage($formMessage);
    } else {
        echo '<p>No ' . strtolower($tax['label']) . ' found</p>' . "\n";
    }
}

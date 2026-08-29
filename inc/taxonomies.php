<?php

/**
 * The simple "lookup" entities (manufacturer, supplier, category, location,
 * status).
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
        // Shown as "Manufacturer" throughout. The key and the columns behind it
        // keep the original brand naming, so the database is left alone.
        'brand' => [
            'label'      => 'Manufacturer',
            'plural'     => 'Manufacturers',
            'table'      => 'inv_brands',
            'id'         => 'brand_id',
            'param'      => 'brand_id',
            'itemFilter' => 'i.item_brand_id = :brand_id',
            'usedBy'     => ['inv_items', 'item_brand_id'],
            'submit'     => 'brand',
            'routes'     => [
                'index' => 'manufacturers',
                'add'   => 'add-manufacturer',
                'edit'  => 'edit-manufacturer',
            ],
            'fields'     => ['brand_name' => 'Manufacturer Name'],
        ],
        'supplier' => [
            'label'      => 'Supplier',
            'plural'     => 'Suppliers',
            'table'      => 'inv_suppliers',
            'id'         => 'sup_id',
            'param'      => 'supplier_id',
            'itemFilter' => 'i.item_sup_id = :supplier_id',
            'usedBy'     => ['inv_items', 'item_sup_id'],
            'submit'     => 'sup',
            'routes'     => ['index' => 'suppliers', 'add' => 'add-supplier', 'edit' => 'edit-supplier'],
            'fields'     => [
                'sup_name'    => 'Supplier Name',
                'sup_website' => ['label' => 'Supplier Website (optional)', 'type' => 'url'],
            ],
            'columns'    => ['Website' => 'supplierWebsiteCell'],
        ],
        // The one taxonomy that changes how its items behave: a category files
        // either parts or tools, and an item takes its kind from the
        // categories it is in. See itemType() in inc/items.php.
        'category' => [
            'label'      => 'Category',
            'plural'     => 'Categories',
            'table'      => 'inv_categories',
            'id'         => 'cat_id',
            'param'      => 'category_id',
            'itemFilter' => 'EXISTS (SELECT 1 FROM categories_items fci'
                . ' WHERE fci.item_id = i.item_id AND fci.cat_id = :category_id)',
            'usedBy'     => ['categories_items', 'cat_id'],
            'multiple'   => true,
            'submit'     => 'cat',
            'routes'     => ['index' => 'categories', 'add' => 'add-cat', 'edit' => 'edit-cat'],
            'fields'     => [
                'cat_name' => 'Category Name',
                'cat_type' => [
                    'label'   => 'This Category Files',
                    'type'    => 'select',
                    'options' => ITEM_TYPE_PLURALS,
                    'default' => 'part',
                ],
            ],
            'columns'    => ['Files' => 'categoryTypeCell'],
            'derived'    => 'categorySlug',
            'guard'      => 'categoryTypeGuard',
            'notice'     => 'categoryTypeNotice',
            'onSave'     => 'categoryTypeApply',
        ],
        'location' => [
            'label'      => 'Location',
            'plural'     => 'Locations',
            'table'      => 'inv_locations',
            'id'         => 'loc_id',
            'param'      => 'location_id',
            'itemFilter' => 'i.item_loc_id = :location_id',
            'usedBy'     => ['inv_items', 'item_loc_id'],
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
            'itemFilter' => 'i.item_status = :status_id',
            'usedBy'     => ['inv_items', 'item_status'],
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

/** Extra listing column for categories. */
function categoryTypeCell(array $row): string
{
    return escapeHtml(ITEM_TYPE_PLURALS[$row['cat_type']] ?? $row['cat_type']);
}

/**
 * What stops the items in a category all becoming $newType, as one phrase per
 * item that has something against it.
 *
 * Two things do: another category of the other kind, which would leave the
 * item disagreeing with itself about what it is, and the item's own records,
 * which is itemsBlockingKindChange()'s question.
 */
function categoryConversionBlockers($cat_id, string $newType): array
{
    $items = dbAll(
        'SELECT i.item_id, i.item_name,
                EXISTS (SELECT 1 FROM categories_items oci
                        INNER JOIN inv_categories oc ON oc.cat_id = oci.cat_id
                        WHERE oci.item_id = i.item_id
                          AND oci.cat_id <> :other_cat_id
                          AND oc.cat_type <> :new_type) AS in_other_kind
         FROM categories_items ci
         INNER JOIN inv_items i ON i.item_id = ci.item_id
         WHERE ci.cat_id = :cat_id
         ORDER BY i.item_name asc',
        ['cat_id' => $cat_id, 'other_cat_id' => $cat_id, 'new_type' => $newType]
    );

    if (!$items) {
        return [];
    }

    $blocked = itemsBlockingKindChange(array_column($items, 'item_id'), $newType);
    $otherKind = ($newType === 'part') ? 'tool' : 'part';

    $reasons = [
        'assembly' => 'is on a project assembly',
        'loans'    => 'has been signed out before',
    ];

    $problems = [];

    foreach ($items as $item) {
        $why = [];

        if ($item['in_other_kind']) {
            $why[] = 'is also in a category that files ' . strtolower(ITEM_TYPE_PLURALS[$otherKind]);
        }

        if (isset($blocked[$item['item_id']])) {
            $why[] = $reasons[$blocked[$item['item_id']]];
        }

        if ($why) {
            $problems[] = '&ldquo;' . escapeHtml($item['item_name']) . '&rdquo; '
                . implode(' and ', $why);
        }
    }

    return $problems;
}

/**
 * A category's kind decides how everything filed under it behaves, so
 * switching it takes every item in it along. Allowed, but only where each of
 * those items can make the move, since converting one that cannot would leave
 * it in a state nothing else expects. The first few offenders are named, a
 * count alone leaving you to hunt for them.
 */
function categoryTypeGuard($id, array $values): ?string
{
    $current = dbValue('SELECT cat_type FROM inv_categories WHERE cat_id = :id', ['id' => $id]);

    if ($current === null || $current === $values['cat_type']) {
        return null;
    }

    $problems = categoryConversionBlockers($id, $values['cat_type']);

    if (!$problems) {
        return null;
    }

    $shown = array_slice($problems, 0, 5);
    $rest = count($problems) - count($shown);

    return count($problems) . ' ' . (count($problems) === 1 ? 'item cannot' : 'items cannot')
        . ' become ' . strtolower(ITEM_TYPE_PLURALS[$values['cat_type']]) . ': '
        . implode('; ', $shown)
        . ($rest > 0 ? '; and ' . $rest . ' more' : '')
        . '. Sort ' . (count($problems) === 1 ? 'it' : 'them') . ' out first.';
}

/**
 * The items a switched category takes with it.
 *
 * They convert themselves, since nothing on an item says which kind it is; all
 * that needs writing is stock. A tool has none, and the columns are not
 * nullable, so they go back to the single piece itemColumns() stores, leaving
 * the movements already against the item as history. The other direction
 * writes nothing: there is no earlier quantity to put back.
 *
 * Runs before the category's own update and in the same transaction, so
 * cat_type still says what it was.
 */
function categoryTypeApply($id, array $values): void
{
    $current = dbValue('SELECT cat_type FROM inv_categories WHERE cat_id = :id', ['id' => $id]);

    if ($current === $values['cat_type'] || $values['cat_type'] !== 'tool') {
        return;
    }

    dbRun(
        'UPDATE inv_items
            SET item_quantity = 1, item_min_quantity = 0, item_measurement_unit = :unit
          WHERE item_id IN (SELECT item_id FROM categories_items WHERE cat_id = :cat_id)',
        ['unit' => pieceUnitId(), 'cat_id' => $id]
    );
}

/**
 * What switching this category would do, said above the form, since it reaches
 * further than the row being edited.
 */
function categoryTypeNotice(array $row): void
{
    $inUse = taxonomyUsageCount(taxonomy('category'), $row['cat_id']);

    if ($inUse === 0) {
        return;
    }

    template('taxonomy/category-notice', [
        'catId'     => (int)$row['cat_id'],
        'inUse'     => $inUse,
        'otherKind' => ($row['cat_type'] === 'part') ? 'tool' : 'part',
    ]);
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

/** The name search a listing was asked for, as [sql fragment, bound params]. */
function taxonomySearch(array $tax, string $search): array
{
    if ($search === '') {
        return ['', []];
    }

    return [
        ' WHERE ' . taxonomyNameField($tax) . ' LIKE :search',
        ['search' => '%' . $search . '%'],
    ];
}

/**
 * Rows ordered by name, optionally narrowed to a name search and to one page.
 *
 * The dropdowns and the label sheets pass no slice, because those want every
 * row rather than a screenful.
 */
function taxonomyRows(array $tax, string $search = '', ?array $slice = null): array
{
    [$where, $params] = taxonomySearch($tax, $search);

    return dbAll(
        'SELECT * FROM ' . $tax['table'] . $where
        . ' ORDER BY ' . taxonomyNameField($tax) . ' asc'
        . ($slice ? paginationLimit($slice) : ''),
        $params
    );
}

/** How many rows that same search matches. */
function taxonomyRowCount(array $tax, string $search = ''): int
{
    [$where, $params] = taxonomySearch($tax, $search);

    return (int)dbValue('SELECT COUNT(*) FROM ' . $tax['table'] . $where, $params, 0);
}

/** Rows as a value => label map, for use in a <select>. */
function taxonomyOptions(string $key): array
{
    $tax = taxonomy($key);

    return array_column(taxonomyRows($tax), taxonomyNameField($tax), $tax['id']);
}

/**
 * Category rows as a value => label map, optionally only those filing one kind
 * of thing. The item form offers just the kind the item being edited is.
 */
function categoryOptions(?string $type = null): array
{
    $where = ($type === null) ? '' : ' WHERE cat_type = :cat_type';
    $params = ($type === null) ? [] : ['cat_type' => $type];

    return array_column(
        dbAll('SELECT cat_id, cat_name FROM inv_categories' . $where . ' ORDER BY cat_name asc', $params),
        'cat_name',
        'cat_id'
    );
}

/** The kinds of thing the categories an item is in file, as a unique list. */
function categoryTypesFor(array $categoryIds): array
{
    $categoryIds = array_filter(array_map('intval', $categoryIds));

    if (!$categoryIds) {
        return [];
    }

    // The ids are cast to integers above, so they are safe to inline; a bound
    // parameter cannot stand in for a list.
    return array_column(dbAll(
        'SELECT DISTINCT cat_type FROM inv_categories
         WHERE cat_id IN (' . implode(',', $categoryIds) . ')
         ORDER BY cat_type',
        []
    ), 'cat_type');
}

/** The name of one row, used for filter labels. Null when it no longer exists. */
function taxonomyName(string $key, $id): ?string
{
    $tax = taxonomy($key);

    return dbValue(
        'SELECT ' . taxonomyNameField($tax) . ' FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :id',
        ['id' => $id]
    );
}

/** How many items still refer to one row. */
function taxonomyUsageCount(array $tax, $id): int
{
    [$table, $column] = $tax['usedBy'];

    return (int)dbValue('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :id', ['id' => $id], 0);
}

/**
 * The same for a set of rows at once, as id => count, so a listing does not
 * ask once per line. Ids with nothing against them are left out.
 */
function taxonomyUsageCounts(array $tax, array $ids): array
{
    [$table, $column] = $tax['usedBy'];
    $ids = array_filter(array_map('intval', $ids));

    if (!$ids) {
        return [];
    }

    // The ids come from rows just read out of the database, so they are
    // already integers; a bound parameter cannot stand in for a list.
    return array_column(dbAll(
        'SELECT ' . $column . ' AS id, COUNT(*) AS total FROM ' . $table
        . ' WHERE ' . $column . ' IN (' . implode(',', $ids) . ')'
        . ' GROUP BY ' . $column
    ), 'total', 'id');
}

/**
 * Turn submitted input into the columns to write, or ['errors' => [...]] keyed
 * by the field, when the required name is missing.
 */
function taxonomyValues(array $tax, array $input): array
{
    $values = [];

    foreach ($tax['fields'] as $column => $field) {
        $value = trim((string)($input[$column] ?? ''));

        // A field offering a fixed set of choices only ever stores one of
        // them, whatever was posted.
        if (is_array($field) && isset($field['options']) && !isset($field['options'][$value])) {
            $value = $field['default'] ?? array_key_first($field['options']);
        }

        $values[$column] = $value;
    }

    $nameField = taxonomyNameField($tax);

    if ($values[$nameField] === '') {
        return ['errors' => [$nameField => $tax['label'] . ' name cannot be empty']];
    }

    if (isset($tax['derived'])) {
        $values += $tax['derived']($values);
    }

    return ['values' => $values];
}

/**
 * A failed insert in the shape both callers want: 'errors' keyed by field for
 * the page form, and the same thing as one string for the modal, which has
 * only the one place to put it.
 */
function taxonomyInsertFailure(array $errors): array
{
    return ['success' => false, 'errors' => $errors, 'error' => implode(' ', $errors)];
}

/** Insert a new row. Returns the AJAX response shape used by the modal forms. */
function taxonomyInsert(string $key, array $input): array
{
    $tax = taxonomy($key);
    $result = taxonomyValues($tax, $input);

    if (isset($result['errors'])) {
        return taxonomyInsertFailure($result['errors']);
    }

    $columns = array_keys($result['values']);

    try {
        $newId = dbInsert(
            'INSERT INTO ' . $tax['table'] . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (:' . implode(', :', $columns) . ')',
            $result['values']
        );
    } catch (\PDOException $e) {
        // Most often a duplicate name, which is the field worth pointing at.
        return taxonomyInsertFailure([taxonomyNameField($tax) => $e->getMessage()]);
    }

    return ['success' => true, 'newId' => $newId];
}

/**
 * The <p><label><input></p> blocks for a taxonomy's fields.
 */
function taxonomyFields(array $tax, array $values = [], array $locked = []): void
{
    template('taxonomy/fields', compact('tax', 'values', 'locked'));
}

/**
 * Add/edit form: fields plus a save button.
 */
function taxonomyForm(
    array $tax,
    string $action,
    array $values = [],
    $formMessage = false,
    array $locked = []
): void {
    template('taxonomy/form', compact('tax', 'action', 'values', 'formMessage', 'locked'));
}

/**
 * The form loaded into the "add new" modal on the item pages.
 *
 * $locked pins a field to one value: a category added from the item form has
 * to file the kind of thing that item is, so there is nothing to choose.
 */
function taxonomyModalForm(string $key, array $locked = []): string
{
    return templateHtml('taxonomy/modal-form', ['tax' => taxonomy($key), 'locked' => $locked]);
}

/**
 * Listing page: every row, searchable, linking through to the filtered items.
 */
function taxonomyIndexPage(string $key): void
{
    $tax = taxonomy($key);
    $search = trim((string)queryParam('q'));
    $slice = paginate(taxonomyRowCount($tax, $search));
    $rows = taxonomyRows($tax, $search, $slice);

    $links = ['Add New ' . $tax['label'] => 'index.php?page=' . $tax['routes']['add']];

    if ($key === 'location' && $rows) {
        $links['Labels'] = 'index.php?page=labels&amp;type=location';
    }

    template('page/taxonomy-index', [
        'tax'          => $tax,
        'rows'         => $rows,
        'slice'        => $slice,
        'search'       => $search,
        // One query for the whole page rather than one per line.
        'usage'        => taxonomyUsageCounts($tax, array_column($rows, $tax['id'])),
        'links'        => $links,
        'nameField'    => taxonomyNameField($tax),
        'extraColumns' => $tax['columns'] ?? [],
    ]);
}

/**
 * Add page.
 */
function taxonomyAddPage(string $key): void
{
    $tax = taxonomy($key);
    $formMessage = takeFlash();
    $values = [];

    if (isset($_POST['add_' . $tax['submit'] . '_submit'])) {
        $result = taxonomyInsert($key, $_POST);

        if ($result['success']) {
            redirectWith(
                'index.php?page=' . $tax['routes']['index'],
                successMessage($tax['label'] . ' added!')
            );
        }

        $values = $_POST;
        $formMessage = errorMessage($result['errors']);
    }

    template('page/taxonomy-add', compact('tax', 'values', 'formMessage'));
}

/**
 * Edit page, including deletion.
 */
function taxonomyEditPage(string $key): void
{
    $tax = taxonomy($key);
    $editId = queryParam($tax['param']);
    $formMessage = takeFlash();
    $editUrl = 'index.php?page=' . $tax['routes']['edit'] . '&' . $tax['param'] . '=' . urlencode((string)$editId);
    $indexUrl = 'index.php?page=' . $tax['routes']['index'];

    // What a rejected save was trying to do, so the form comes back holding it
    // rather than the stored row, the same as the add page does.
    $posted = null;

    if (isset($_POST['edit_' . $tax['submit'] . '_submit'])) {
        $result = taxonomyValues($tax, $_POST);

        $blocked = isset($result['values']) && isset($tax['guard'])
            ? $tax['guard']($editId, $result['values'])
            : null;

        // A guard is about the row as a whole, so it goes in without a field.
        $errors = ($result['errors'] ?? []) + ($blocked !== null ? [$blocked] : []);

        if ($errors) {
            $formMessage = errorMessage($errors);
            $posted = $_POST;
        } else {
            dbTransaction(function () use ($tax, $result, $editId) {
                // A row that decides how other rows behave puts those right
                // itself, first, while it can still see what it used to say.
                if (isset($tax['onSave'])) {
                    $tax['onSave']($editId, $result['values']);
                }

                $assignments = [];

                foreach (array_keys($result['values']) as $column) {
                    $assignments[] = $column . ' = :' . $column;
                }

                dbRun(
                    'UPDATE ' . $tax['table'] . ' SET ' . implode(', ', $assignments)
                    . ' WHERE ' . $tax['id'] . ' = :edit_id',
                    $result['values'] + ['edit_id' => $editId]
                );
            });

            redirectWith($editUrl, successMessage($tax['label'] . ' updated!'));
        }
    } elseif (isset($_POST['delete_' . $tax['submit'] . '_submit'])) {
        $inUse = taxonomyUsageCount($tax, $editId);

        if ($inUse > 0) {
            $formMessage = errorMessage(
                $inUse . ' ' . ($inUse === 1 ? 'item still uses' : 'items still use')
                . ' this ' . strtolower($tax['label']) . ', so it cannot be deleted.'
                . ' Reassign ' . ($inUse === 1 ? 'it' : 'them') . ' first.'
            );
        } else {
            dbRun(
                'DELETE FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :edit_id LIMIT 1',
                ['edit_id' => $editId]
            );

            redirectWith($indexUrl, successMessage($tax['label'] . ' deleted!'));
        }
    }

    $row = dbRow('SELECT * FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :edit_id', ['edit_id' => $editId]);

    template('page/taxonomy-edit', [
        'tax'         => $tax,
        'row'         => $row,
        'values'      => $posted ?? ($row ?: []),
        'formMessage' => $formMessage,
        'indexUrl'    => $indexUrl,
        'editId'      => $editId,
        'inUse'       => $row ? taxonomyUsageCount($tax, $editId) : 0,
    ]);
}

<?php

/**
 * One definition per lookup table drives its pages, the item form dropdowns and
 * the modal. The first entry in fields is the required name column.
 */
function taxonomies(): array
{
    return [
        // Shown as "Manufacturer"; the columns keep the original brand naming.
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
                'sup_website' => [
                    'label'   => 'Supplier Website (optional)',
                    'type'    => 'url',
                    'invalid' => 'The supplier website must be a web address starting http:// or https://',
                ],
            ],
            'columns'    => ['Website' => 'supplierWebsiteCell'],
        ],
        // An item takes its kind from its categories. See itemType().
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
            'filter'     => [
                'param'   => 'type',
                'column'  => 't.cat_type',
                'label'   => 'Files',
                'any'     => 'Any Type',
                'options' => ITEM_TYPE_PLURALS,
            ],
            'derived'    => 'categorySlug',
            'guard'      => 'categoryTypeGuard',
            'notice'     => 'categoryTypeNotice',
            'onSave'     => 'categoryTypeApply',
        ],
        // Nests one level deep only.
        'location' => [
            'label'      => 'Location',
            'plural'     => 'Locations',
            'table'      => 'inv_locations',
            'id'         => 'loc_id',
            'param'      => 'location_id',
            // Includes sub-locations. One placeholder, since named parameters
            // cannot be reused.
            'itemFilter' => 'EXISTS (SELECT 1 FROM inv_locations fl
                WHERE fl.loc_id = i.item_loc_id
                  AND :location_id IN (fl.loc_id, fl.loc_parent_id))',
            'usedBy'     => ['inv_items', 'item_loc_id'],
            'submit'     => 'loc',
            'routes'     => ['index' => 'locations', 'add' => 'add-loc', 'edit' => 'edit-loc'],
            'fields'     => [
                'loc_name'      => 'Location Name',
                'loc_parent_id' => [
                    'label'       => 'Inside (optional)',
                    'type'        => 'select',
                    'optionsFrom' => 'locationParentOptions',
                    'placeholder' => 'Not inside another location',
                    'nullable'    => true,
                ],
            ],
            'uniqueWith'    => 'loc_parent_id',
            'joins'         => 'LEFT JOIN inv_locations parent ON parent.loc_id = t.loc_parent_id',
            'select'        => 'parent.loc_name AS loc_parent_name,
                (SELECT COUNT(*) FROM inv_locations child
                 WHERE child.loc_parent_id = t.loc_id) AS child_count',
            // Each location followed by its children. The id keeps two
            // same-named parents from interleaving their children.
            'orderBy'       => 'COALESCE(parent.loc_name, t.loc_name) asc,
                COALESCE(t.loc_parent_id, t.loc_id) asc,
                t.loc_parent_id IS NOT NULL asc,
                t.loc_name asc',
            'columns'       => ['Sub-Locations' => 'locationChildrenCell'],
            'nameCell'      => 'locationNameCell',
            'options'       => 'locationOptions',
            'name'          => 'locationName',
            'counts'        => 'locationItemCounts',
            'notice'        => 'locationParentNotice',
            'deleteBlocker' => 'locationDeleteBlocker',
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

function categoryTypeCell(array $row): string
{
    return escapeHtml(ITEM_TYPE_PLURALS[$row['cat_type']] ?? $row['cat_type']);
}

/** One phrase per item that cannot change kind. */
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

/** A category can switch kind only if every item in it can. */
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
 * Only stock needs writing: becoming tools resets it to the single piece
 * itemColumns() stores. Runs before the category's own update, in the same
 * transaction, so cat_type still holds the old kind.
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

function supplierWebsiteCell(array $row): string
{
    $website = (string)$row['sup_website'];

    if ($website === '') {
        return '';
    }

    // A row stored before this was checked on save can hold anything.
    return isWebUrl($website)
        ? '<a href="' . escapeHtml($website) . '" target="_blank">Visit Website</a>'
        : escapeHtml($website);
}

/** Columns written on save but not shown on the form. */
function categorySlug(array $values): array
{
    return ['cat_slug' => slugify($values['cat_name'])];
}

/*
 * Locations nest at most one level: one holding sub-locations is offered no
 * parent of its own.
 */

const LOCATION_PATH_SEPARATOR = ' > ';

/** Eg "Tool Chest > Drawer 1". Needs loc_name and, if nested, loc_parent_name. */
function locationPath(array $row): string
{
    $name = (string)($row['loc_name'] ?? '');

    return isset($row['loc_parent_name'])
        ? $row['loc_parent_name'] . LOCATION_PATH_SEPARATOR . $name
        : $name;
}

function locationName($id): ?string
{
    $row = dbRow(
        'SELECT l.loc_name, p.loc_name AS loc_parent_name
         FROM inv_locations l
         LEFT JOIN inv_locations p ON p.loc_id = l.loc_parent_id
         WHERE l.loc_id = :id',
        ['id' => $id]
    );

    return $row ? locationPath($row) : null;
}

/** Named in full rather than indented, as the searchable dropdowns reorder matches. */
function locationOptions(): array
{
    $options = [];

    foreach (taxonomyRows(taxonomy('location')) as $row) {
        $options[$row['loc_id']] = locationPath($row);
    }

    return $options;
}

/** Top-level locations other than $id; none if $id has sub-locations. */
function locationParentOptions($id = null): array
{
    if ($id !== null && locationChildCount($id) > 0) {
        return [];
    }

    return array_column(
        dbAll(
            'SELECT loc_id, loc_name FROM inv_locations
             WHERE loc_parent_id IS NULL'
            . ($id === null ? '' : ' AND loc_id <> :id')
            . ' ORDER BY loc_name asc',
            $id === null ? [] : ['id' => $id]
        ),
        'loc_name',
        'loc_id'
    );
}

function locationChildCount($id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_locations WHERE loc_parent_id = :id',
        ['id' => $id],
        0
    );
}

/**
 * Includes sub-locations, to match the linked listing. COUNT(DISTINCT) because
 * the join would otherwise count a parent's item once per sub-location.
 */
function locationItemCounts(array $ids): array
{
    $ids = array_filter(array_map('intval', $ids));

    if (!$ids) {
        return [];
    }

    // Cast to integers, so safe to inline.
    return array_column(dbAll(
        'SELECT l.loc_id AS id, COUNT(DISTINCT i.item_id) AS total
         FROM inv_locations l
         LEFT JOIN inv_locations c ON c.loc_parent_id = l.loc_id
         LEFT JOIN inv_items i ON i.item_loc_id IN (l.loc_id, c.loc_id)
         WHERE l.loc_id IN (' . implode(',', $ids) . ')
         GROUP BY l.loc_id'
    ), 'total', 'id');
}

/** Shows the parent, since a search can return a drawer without its chest. */
function locationNameCell(array $row): string
{
    $link = '<a href="index.php?page=items&location_id=' . (int)$row['loc_id'] . '">'
        . escapeHtml($row['loc_name']) . '</a>';

    if (!isset($row['loc_parent_name'])) {
        return $link;
    }

    return '<span class="sub-location">' . $link
        . '<small class="row-note">in ' . escapeHtml($row['loc_parent_name']) . '</small></span>';
}

function locationChildrenCell(array $row): string
{
    if (isset($row['loc_parent_name'])) {
        return '-';
    }

    $count = (int)($row['child_count'] ?? 0);

    return ($count > 0 ? $count : 'None')
        . '<small class="row-note"><a href="index.php?page=add-loc&amp;loc_parent_id='
        . (int)$row['loc_id'] . '">Add sub-location</a></small>';
}

function locationParentNotice(array $row): void
{
    $children = locationChildCount($row['loc_id']);

    if ($children === 0) {
        return;
    }

    template('taxonomy/location-notice', ['children' => $children]);
}

function locationDeleteBlocker($id): ?string
{
    $children = locationChildCount($id);

    if ($children === 0) {
        return null;
    }

    return 'This location holds ' . $children . ' sub-location' . ($children === 1 ? '' : 's')
        . ', so it cannot be deleted. Delete ' . ($children === 1 ? 'it' : 'them') . ' first.';
}

/**
 * $id (the row being edited) is excluded. uniqueWith scopes the check, eg to a
 * parent location. MySQL unique indexes treat NULLs as distinct, so duplicate
 * top-level locations are only caught here.
 */
function taxonomyNameTaken(array $tax, array $values, $id = null): bool
{
    $nameField = taxonomyNameField($tax);
    $where = $nameField . ' = :name';
    $params = ['name' => $values[$nameField]];

    if (isset($tax['uniqueWith'])) {
        $scope = $values[$tax['uniqueWith']] ?? null;
        $where .= ' AND ' . $tax['uniqueWith'] . ($scope === null ? ' IS NULL' : ' = :scope');

        if ($scope !== null) {
            $params['scope'] = $scope;
        }
    }

    if ($id !== null && $id !== '') {
        $where .= ' AND ' . $tax['id'] . ' <> :id';
        $params['id'] = $id;
    }

    return dbValue('SELECT 1 FROM ' . $tax['table'] . ' WHERE ' . $where . ' LIMIT 1', $params) !== null;
}

function taxonomyNameField(array $tax): string
{
    return array_key_first($tax['fields']);
}

/**
 * Returns [where, params]. Columns are qualified with the 't' alias, since a
 * taxonomy that joins to itself has each column twice.
 */
function taxonomySearch(array $tax, string $search, string $filter = ''): array
{
    $clauses = [];
    $params = [];

    if ($search !== '') {
        $clauses[] = 't.' . taxonomyNameField($tax) . ' LIKE :search';
        $params['search'] = '%' . likeEscape($search) . '%';
    }

    if ($filter !== '') {
        $clauses[] = $tax['filter']['column'] . ' = :filter';
        $params['filter'] = $filter;
    }

    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

/** '' when there is no filter or the query string names an unknown option. */
function taxonomyFilterValue(array $tax): string
{
    if (!isset($tax['filter'])) {
        return '';
    }

    $value = (string)queryParam($tax['filter']['param']);

    return isset($tax['filter']['options'][$value]) ? $value : '';
}

function taxonomyFrom(array $tax): string
{
    return ' FROM ' . $tax['table'] . ' t'
        . (isset($tax['joins']) ? ' ' . $tax['joins'] : '');
}

function taxonomyRows(array $tax, string $search = '', ?array $slice = null, string $filter = ''): array
{
    [$where, $params] = taxonomySearch($tax, $search, $filter);

    return dbAll(
        'SELECT t.*' . (isset($tax['select']) ? ', ' . $tax['select'] : '')
        . taxonomyFrom($tax) . $where
        . ' ORDER BY ' . ($tax['orderBy'] ?? 't.' . taxonomyNameField($tax) . ' asc')
        . ($slice ? paginationLimit($slice) : ''),
        $params
    );
}

function taxonomyRowCount(array $tax, string $search = '', string $filter = ''): int
{
    [$where, $params] = taxonomySearch($tax, $search, $filter);

    return (int)dbValue('SELECT COUNT(*)' . taxonomyFrom($tax) . $where, $params, 0);
}

function taxonomyOptions(string $key): array
{
    $tax = taxonomy($key);

    if (isset($tax['options'])) {
        return $tax['options']();
    }

    return array_column(taxonomyRows($tax), taxonomyNameField($tax), $tax['id']);
}

/** $id is the row being edited, so a location is not offered itself. */
function taxonomyFieldOptions(array $field, $id = null): array
{
    return isset($field['optionsFrom'])
        ? $field['optionsFrom']($id)
        : ($field['options'] ?? []);
}

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

function categoryTypesFor(array $categoryIds): array
{
    $categoryIds = array_filter(array_map('intval', $categoryIds));

    if (!$categoryIds) {
        return [];
    }

    // Cast to integers, so safe to inline.
    return array_column(dbAll(
        'SELECT DISTINCT cat_type FROM inv_categories
         WHERE cat_id IN (' . implode(',', $categoryIds) . ')
         ORDER BY cat_type',
        []
    ), 'cat_type');
}

/** Null when the row no longer exists. */
function taxonomyName(string $key, $id): ?string
{
    $tax = taxonomy($key);

    if (isset($tax['name'])) {
        return $tax['name']($id);
    }

    return dbValue(
        'SELECT ' . taxonomyNameField($tax) . ' FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :id',
        ['id' => $id]
    );
}

function taxonomyUsageCount(array $tax, $id): int
{
    return (int)(taxonomyUsageCounts($tax, [$id])[(int)$id] ?? 0);
}

/** Returns id => count. */
function taxonomyUsageCounts(array $tax, array $ids): array
{
    if (isset($tax['counts'])) {
        return $tax['counts']($ids);
    }

    [$table, $column] = $tax['usedBy'];
    $ids = array_filter(array_map('intval', $ids));

    if (!$ids) {
        return [];
    }

    // Cast to integers, so safe to inline.
    return array_column(dbAll(
        'SELECT ' . $column . ' AS id, COUNT(*) AS total FROM ' . $table
        . ' WHERE ' . $column . ' IN (' . implode(',', $ids) . ')'
        . ' GROUP BY ' . $column
    ), 'total', 'id');
}

/**
 * Returns ['values' => ...] or ['errors' => ...] keyed by field. Every save
 * (pages, modal, CSV import) comes through here, so validate here.
 */
function taxonomyValues(array $tax, array $input, $id = null): array
{
    $values = [];

    foreach ($tax['fields'] as $column => $field) {
        $value = trim((string)($input[$column] ?? ''));
        $nullable = is_array($field) && !empty($field['nullable']);

        // Only ever store one of the offered choices, or null if nullable.
        if (is_array($field) && ($field['type'] ?? '') === 'select') {
            $options = taxonomyFieldOptions($field, $id);

            if (!isset($options[$value])) {
                $value = $nullable ? '' : ($field['default'] ?? (string)array_key_first($options));
            }
        }

        $values[$column] = ($nullable && $value === '') ? null : $value;
    }

    $nameField = taxonomyNameField($tax);
    $errors = [];

    if ($values[$nameField] === '') {
        $errors[$nameField] = $tax['label'] . ' name cannot be empty';
    } elseif (taxonomyNameTaken($tax, $values, $id)) {
        $errors[$nameField] = 'There is already a ' . strtolower($tax['label']) . ' called "'
            . escapeHtml($values[$nameField]) . '"'
            . (empty($values[$tax['uniqueWith'] ?? '']) ? '' : ' in there') . '.';
    }

    // type="url" can be bypassed, and the value ends up in an href.
    foreach ($tax['fields'] as $column => $field) {
        if (is_array($field) && ($field['type'] ?? '') === 'url'
            && !isWebUrl(($values[$column] === '') ? null : $values[$column])) {
            $errors[$column] = $field['invalid']
                ?? 'That must be a web address starting http:// or https://';
        }
    }

    if ($errors) {
        return ['errors' => $errors];
    }

    if (isset($tax['derived'])) {
        $values += $tax['derived']($values);
    }

    return ['values' => $values];
}

/** 'errors' keyed by field for the page form; 'error' as one string for the modal. */
function taxonomyInsertFailure(array $errors): array
{
    return ['success' => false, 'errors' => $errors, 'error' => implode(' ', $errors)];
}

/** Returns the AJAX response shape used by the modal forms. */
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
        // The driver's message can leak SQL, and form messages are drawn as HTML.
        error_log('[inventory] ' . $e->getMessage());

        return taxonomyInsertFailure([taxonomyNameField($tax) => config('debug')
            ? escapeHtml($e->getMessage())
            : 'That ' . strtolower($tax['label']) . ' could not be saved.']);
    }

    return ['success' => true, 'newId' => $newId];
}

function taxonomyFields(array $tax, array $values = [], array $locked = [], $id = null): void
{
    template('taxonomy/fields', compact('tax', 'values', 'locked', 'id'));
}

function taxonomyForm(
    array $tax,
    string $action,
    array $values = [],
    $formMessage = false,
    array $locked = [],
    $id = null
): void {
    template('taxonomy/form', compact('tax', 'action', 'values', 'formMessage', 'locked', 'id'));
}

/** $locked pins fields to fixed values, eg a category's kind from the item form. */
function taxonomyModalForm(string $key, array $locked = []): string
{
    return templateHtml('taxonomy/modal-form', ['tax' => taxonomy($key), 'locked' => $locked]);
}

function taxonomyIndexPage(string $key): void
{
    $tax = taxonomy($key);
    $search = trim((string)queryParam('q'));
    $filter = taxonomyFilterValue($tax);
    $slice = paginate(taxonomyRowCount($tax, $search, $filter));
    $rows = taxonomyRows($tax, $search, $slice, $filter);

    $links = ['Add New ' . $tax['label'] => 'index.php?page=' . $tax['routes']['add']];

    if ($key === 'location' && $rows) {
        $links['Labels'] = 'index.php?page=labels&amp;type=location';
    }

    template('page/taxonomy-index', [
        'tax'          => $tax,
        'rows'         => $rows,
        'slice'        => $slice,
        'search'       => $search,
        'filter'       => $filter,
        'usage'        => taxonomyUsageCounts($tax, array_column($rows, $tax['id'])),
        'links'        => $links,
        'nameField'    => taxonomyNameField($tax),
        'extraColumns' => $tax['columns'] ?? [],
    ]);
}

function taxonomyAddPage(string $key): void
{
    $tax = taxonomy($key);
    $formMessage = takeFlash();

    // Prefill from the query string, eg "Add sub-location" passes the parent.
    $values = [];

    foreach (array_keys($tax['fields']) as $column) {
        $given = queryParam($column);

        if ($given !== false && $given !== '') {
            $values[$column] = $given;
        }
    }

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

function taxonomyEditPage(string $key): void
{
    $tax = taxonomy($key);
    $editId = queryParam($tax['param']);
    $formMessage = takeFlash();
    $editUrl = 'index.php?page=' . $tax['routes']['edit'] . '&' . $tax['param'] . '=' . urlencode((string)$editId);
    $indexUrl = 'index.php?page=' . $tax['routes']['index'];

    // A rejected save comes back holding what it tried, not the stored row.
    $posted = null;

    if (isset($_POST['edit_' . $tax['submit'] . '_submit'])) {
        $result = taxonomyValues($tax, $_POST, $editId);

        $blocked = isset($result['values']) && isset($tax['guard'])
            ? $tax['guard']($editId, $result['values'])
            : null;

        $errors = ($result['errors'] ?? []) + ($blocked !== null ? [$blocked] : []);

        if ($errors) {
            $formMessage = errorMessage($errors);
            $posted = $_POST;
        } else {
            dbTransaction(function () use ($tax, $result, $editId) {
                // Must run before the update, while it can see the old values.
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
        $blocker = isset($tax['deleteBlocker']) ? $tax['deleteBlocker']($editId) : null;

        if ($inUse > 0) {
            $formMessage = errorMessage(
                $inUse . ' ' . ($inUse === 1 ? 'item still uses' : 'items still use')
                . ' this ' . strtolower($tax['label']) . ', so it cannot be deleted.'
                . ' Reassign ' . ($inUse === 1 ? 'it' : 'them') . ' first.'
            );
        } elseif ($blocker !== null) {
            $formMessage = errorMessage($blocker);
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
        'blocker'     => ($row && isset($tax['deleteBlocker'])) ? $tax['deleteBlocker']($editId) : null,
    ]);
}

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
                'sup_website' => [
                    'label'   => 'Supplier Website (optional)',
                    'type'    => 'url',
                    'invalid' => 'The supplier website must be a web address starting http:// or https://',
                ],
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
        // The one taxonomy whose rows nest: a set of drawers is one location
        // with a location per drawer inside it, and the items are filed in the
        // drawer. Only one level deep, so a sub-location never holds any of
        // its own. See locationPath() and the helpers below it.
        'location' => [
            'label'      => 'Location',
            'plural'     => 'Locations',
            'table'      => 'inv_locations',
            'id'         => 'loc_id',
            'param'      => 'location_id',
            // Drilling into a location shows what is inside its sub-locations
            // as well, so the code stuck on a chest covers all of its drawers.
            // One placeholder, because named parameters cannot be reused.
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
            'joins'         => 'LEFT JOIN inv_locations parent ON parent.loc_id = t.loc_parent_id',
            'select'        => 'parent.loc_name AS loc_parent_name,
                (SELECT COUNT(*) FROM inv_locations child
                 WHERE child.loc_parent_id = t.loc_id) AS child_count',
            // Each location followed by whatever is inside it, so the listing
            // reads as the shelving does. The id keeps two same-named parents
            // from interleaving their children.
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
    $website = (string)$row['sup_website'];

    if ($website === '') {
        return '';
    }

    // A row stored before the address was checked on save can hold anything,
    // so one that cannot be linked to is shown as the text it is.
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
 * Locations
 *
 * A location may sit inside one other location, so a set of drawers is stored
 * as the chest plus a location per drawer, and an item is filed in the drawer
 * rather than in a location of its own. Nesting stops there: a location that
 * already holds sub-locations is not offered a parent of its own, which keeps
 * every path two names long at most and the dropdowns readable.
 */

/** How a location and the one it sits inside are written as one name. */
const LOCATION_PATH_SEPARATOR = ' > ';

/**
 * A location named in full, eg "Tool Chest > Drawer 1".
 *
 * Works on anything carrying loc_name with the parent alongside it as
 * loc_parent_name, which is how both the location rows and the item listings
 * read it, so a drawer is never shown without saying which chest it is in.
 */
function locationPath(array $row): string
{
    $name = (string)($row['loc_name'] ?? '');

    return isset($row['loc_parent_name'])
        ? $row['loc_parent_name'] . LOCATION_PATH_SEPARATOR . $name
        : $name;
}

/** One location named in full, or null when there is no such id. */
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

/**
 * Locations as a value => label map for a <select>, each sub-location named in
 * full and following the one it is inside.
 *
 * The full name rather than an indent, because the searchable dropdowns reorder
 * what they match as you type, which would leave an indented drawer sitting
 * nowhere near its chest.
 */
function locationOptions(): array
{
    $options = [];

    foreach (taxonomyRows(taxonomy('location')) as $row) {
        $options[$row['loc_id']] = locationPath($row);
    }

    return $options;
}

/**
 * The locations $id could be put inside, as a value => label map.
 *
 * Only locations that are not inside something themselves, and never itself.
 * Nothing at all is offered to a location that already holds sub-locations,
 * since moving it would take them a level deeper than anything here reads.
 */
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

/** How many locations sit inside this one. */
function locationChildCount($id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_locations WHERE loc_parent_id = :id',
        ['id' => $id],
        0
    );
}

/**
 * How many items each of these locations holds, counting whatever is in its
 * sub-locations, so the figure matches the listing the name links to.
 *
 * COUNT(DISTINCT) because an item filed in the parent itself would otherwise
 * be counted once per sub-location the join brings back.
 */
function locationItemCounts(array $ids): array
{
    $ids = array_filter(array_map('intval', $ids));

    if (!$ids) {
        return [];
    }

    // The ids come from rows just read out of the database, so they are
    // already integers; a bound parameter cannot stand in for a list.
    return array_column(dbAll(
        'SELECT l.loc_id AS id, COUNT(DISTINCT i.item_id) AS total
         FROM inv_locations l
         LEFT JOIN inv_locations c ON c.loc_parent_id = l.loc_id
         LEFT JOIN inv_items i ON i.item_loc_id IN (l.loc_id, c.loc_id)
         WHERE l.loc_id IN (' . implode(',', $ids) . ')
         GROUP BY l.loc_id'
    ), 'total', 'id');
}

/**
 * The name cell on the locations listing: sub-locations indented under the one
 * they are inside, and named as well, since a search can bring a drawer back
 * without its chest.
 */
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

/** Extra listing column for locations: what is inside one, and a way to add more. */
function locationChildrenCell(array $row): string
{
    // Nesting is one level deep, so a sub-location holds none of its own.
    if (isset($row['loc_parent_name'])) {
        return '-';
    }

    $count = (int)($row['child_count'] ?? 0);

    return ($count > 0 ? $count : 'None')
        . '<small class="row-note"><a href="index.php?page=add-loc&amp;loc_parent_id='
        . (int)$row['loc_id'] . '">Add sub-location</a></small>';
}

/**
 * Why a location cannot be put inside another one, said above the form, since
 * the field it is about has nothing to offer.
 */
function locationParentNotice(array $row): void
{
    $children = locationChildCount($row['loc_id']);

    if ($children === 0) {
        return;
    }

    template('taxonomy/location-notice', ['children' => $children]);
}

/** Why a location cannot be deleted yet, beyond the items filed in it. */
function locationDeleteBlocker($id): ?string
{
    $children = locationChildCount($id);

    if ($children === 0) {
        return null;
    }

    return 'This location holds ' . $children . ' sub-location' . ($children === 1 ? '' : 's')
        . ', so it cannot be deleted. Delete ' . ($children === 1 ? 'it' : 'them') . ' first.';
}

/** The required name column of a taxonomy. */
function taxonomyNameField(array $tax): string
{
    return array_key_first($tax['fields']);
}

/**
 * The name search a listing was asked for, as [sql fragment, bound params].
 *
 * Columns are qualified with the alias taxonomyRows() gives the table, since a
 * taxonomy that joins to itself has the same column name twice over.
 */
function taxonomySearch(array $tax, string $search): array
{
    if ($search === '') {
        return ['', []];
    }

    return [
        ' WHERE t.' . taxonomyNameField($tax) . ' LIKE :search',
        ['search' => '%' . $search . '%'],
    ];
}

/** The table under the alias every taxonomy query reads it by, plus its joins. */
function taxonomyFrom(array $tax): string
{
    return ' FROM ' . $tax['table'] . ' t'
        . (isset($tax['joins']) ? ' ' . $tax['joins'] : '');
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
        'SELECT t.*' . (isset($tax['select']) ? ', ' . $tax['select'] : '')
        . taxonomyFrom($tax) . $where
        . ' ORDER BY ' . ($tax['orderBy'] ?? 't.' . taxonomyNameField($tax) . ' asc')
        . ($slice ? paginationLimit($slice) : ''),
        $params
    );
}

/** How many rows that same search matches. */
function taxonomyRowCount(array $tax, string $search = ''): int
{
    [$where, $params] = taxonomySearch($tax, $search);

    return (int)dbValue('SELECT COUNT(*)' . taxonomyFrom($tax) . $where, $params, 0);
}

/**
 * Rows as a value => label map, for use in a <select>.
 *
 * A taxonomy whose rows nest builds its own labels, so a sub-location is named
 * in full rather than shown as a bare drawer number; see locationOptions().
 */
function taxonomyOptions(string $key): array
{
    $tax = taxonomy($key);

    if (isset($tax['options'])) {
        return $tax['options']();
    }

    return array_column(taxonomyRows($tax), taxonomyNameField($tax), $tax['id']);
}

/**
 * What one of a taxonomy's own select fields offers, either fixed in the
 * definition or worked out for the row being edited.
 *
 * $id is that row, so a location can be kept from being offered itself.
 */
function taxonomyFieldOptions(array $field, $id = null): array
{
    return isset($field['optionsFrom'])
        ? $field['optionsFrom']($id)
        : ($field['options'] ?? []);
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

    if (isset($tax['name'])) {
        return $tax['name']($id);
    }

    return dbValue(
        'SELECT ' . taxonomyNameField($tax) . ' FROM ' . $tax['table'] . ' WHERE ' . $tax['id'] . ' = :id',
        ['id' => $id]
    );
}

/** How many items still refer to one row. */
function taxonomyUsageCount(array $tax, $id): int
{
    return (int)(taxonomyUsageCounts($tax, [$id])[(int)$id] ?? 0);
}

/**
 * The same for a set of rows at once, as id => count, so a listing does not
 * ask once per line. Ids with nothing against them are left out.
 *
 * A taxonomy whose rows nest counts its own, so a location is credited with
 * whatever is in its sub-locations as well: that is what its listing links to,
 * and what has to be moved before it can be deleted.
 */
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
 * by the field, when something is wrong with them.
 *
 * $id is the row being edited, for a field whose choices depend on it: a
 * location cannot be put inside itself, so it is not among the options its own
 * edit form offers.
 *
 * Every save comes through here -- the add page, the edit page, the "+" modal
 * on the item form and the CSV import -- so it is the one place a field has to
 * be checked to be checked everywhere.
 */
function taxonomyValues(array $tax, array $input, $id = null): array
{
    $values = [];

    foreach ($tax['fields'] as $column => $field) {
        $value = trim((string)($input[$column] ?? ''));
        $nullable = is_array($field) && !empty($field['nullable']);

        // A field offering a set of choices only ever stores one of them,
        // whatever was posted. One that may be left unset takes nothing as
        // well, and stores it as nothing rather than as a choice.
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
    }

    // A url field is checked here rather than left to the browser, which only
    // asks nicely: type="url" is skipped by anything posting straight to the
    // page, and the value ends up in an href. See isWebUrl().
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
 *
 * $id is the row being edited, or null on an add form, for a field whose
 * choices depend on which row it belongs to.
 */
function taxonomyFields(array $tax, array $values = [], array $locked = [], $id = null): void
{
    template('taxonomy/fields', compact('tax', 'values', 'locked', 'id'));
}

/**
 * Add/edit form: fields plus a save button.
 */
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

    // A field named in the query string starts filled in, which is how "Add
    // sub-location" arrives with the location it goes inside already chosen.
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
        $result = taxonomyValues($tax, $_POST, $editId);

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
        // Something other than the items filed under it standing in the way of
        // deleting it, such as the sub-locations inside a location.
        'blocker'     => ($row && isset($tax['deleteBlocker'])) ? $tax['deleteBlocker']($editId) : null,
    ]);
}

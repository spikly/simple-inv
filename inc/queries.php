<?php

/*
 * Items
 */

/**
 * Joins shared by every item listing.
 *
 * The two derived tables give a per item total, so they stay correct when the
 * category join multiplies rows for an item in several categories.
 */
const ITEM_JOINS = '
    FROM inv_items i
    LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
    LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
    LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
    LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
    LEFT JOIN inv_statuses s ON s.status_id = i.item_status
    LEFT JOIN categories_items ci ON ci.item_id = i.item_id
    LEFT JOIN inv_categories c ON c.cat_id = ci.cat_id
    LEFT JOIN (
        SELECT dep_item_id, SUM(dep_quantity) AS total
        FROM inv_deployments
        GROUP BY dep_item_id
    ) dep ON dep.dep_item_id = i.item_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_allocated) AS total
        FROM inv_assembly_items
        GROUP BY item_id
    ) alloc ON alloc.item_id = i.item_id
';

/**
 * Stock figures every item listing exposes.
 *
 * deployed  quantity signed out through the deployment pages
 * allocated quantity set aside for project assemblies
 * committed the two added together
 * free      what is left of item_quantity once both are taken off
 */
const ITEM_STOCK_COLUMNS = '
    COALESCE(MAX(dep.total), 0) AS item_deployed_count,
    COALESCE(MAX(alloc.total), 0) AS item_allocated_count,
    COALESCE(MAX(dep.total), 0) + COALESCE(MAX(alloc.total), 0) AS item_committed_count,
    i.item_quantity - COALESCE(MAX(dep.total), 0) - COALESCE(MAX(alloc.total), 0) AS item_free_count,
    GROUP_CONCAT(DISTINCT c.cat_name ORDER BY c.cat_name SEPARATOR \', \') AS cat_names
';

/**
 * The ?brand_id=1 style filters present in the query string.
 *
 * Returns [sql fragment, bound params, taxonomy keys applied].
 */
function itemFilters(): array
{
    $clauses = [];
    $params = [];
    $applied = [];

    foreach (taxonomies() as $key => $tax) {
        $value = queryParam($tax['param']);

        if ($value === false || $value === '') {
            continue;
        }

        $clauses[] = $tax['itemFilter'];
        $params[$tax['param']] = $value;
        $applied[] = $key;
    }

    $search = trim((string)queryParam('q'));

    if ($search !== '') {
        // One placeholder over the three searchable columns: named parameters
        // cannot be reused while prepare emulation is off.
        $clauses[] = "CONCAT_WS(' ', i.item_name, i.item_part_no, i.item_notes) LIKE :search";
        $params['search'] = '%' . $search . '%';
    }

    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params, $applied];
}

/** Items for the listing page, honouring the query string filters. */
function fetchItems(string $where, array $params): array
{
    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_part_no, i.item_quantity, i.item_min_quantity, i.item_image,
                mu.unit_symbol, b.brand_name, sp.sup_name, l.loc_name, s.status_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS . $where
        . ' GROUP BY i.item_id ORDER BY i.item_name asc',
        $params
    );
}

/** Items for the CSV export. The column order matches the export headings. */
function fetchItemsForExport(string $where, array $params): array
{
    return dbAll(
        'SELECT i.item_name AS `Name`,
                i.item_part_no AS `Part No`,
                b.brand_name AS `Manufacturer`,
                sp.sup_name AS `Supplier`,
                GROUP_CONCAT(DISTINCT c.cat_name ORDER BY c.cat_name SEPARATOR \'|\') AS `Categories`,
                l.loc_name AS `Location`,
                s.status_name AS `Status`,
                i.item_quantity AS `Quantity`,
                i.item_min_quantity AS `Min Quantity`,
                mu.unit_symbol AS `Unit`,
                COALESCE(MAX(dep.total), 0) AS `Deployed`,
                COALESCE(MAX(alloc.total), 0) AS `Allocated`,
                i.item_notes AS `Notes`'
        . ITEM_JOINS . $where
        . ' GROUP BY i.item_id ORDER BY i.item_name asc',
        $params
    );
}

function fetchSingleItem($item_id)
{
    return dbRow(
        'SELECT i.*, mu.unit_symbol, mu.unit_label, b.brand_id, b.brand_name,
                sp.sup_id, sp.sup_name, sp.sup_website, l.loc_id, l.loc_name,
                s.status_id, s.status_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS
        . ' WHERE i.item_id = :item_id GROUP BY i.item_id LIMIT 1',
        ['item_id' => $item_id]
    );
}

/** Category ids for an item, in the order they are shown. */
function fetchItemCategoryIds($item_id): array
{
    return array_map('intval', array_column(dbAll(
        'SELECT ci.cat_id
         FROM categories_items ci
         INNER JOIN inv_categories c ON c.cat_id = ci.cat_id
         WHERE ci.item_id = :item_id
         ORDER BY c.cat_name',
        ['item_id' => $item_id]
    ), 'cat_id'));
}

/** Replace the categories an item belongs to. */
function saveItemCategories($item_id, array $categoryIds): void
{
    dbRun('DELETE FROM categories_items WHERE item_id = :item_id', ['item_id' => $item_id]);

    foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
        if ($categoryId > 0) {
            dbRun('INSERT IGNORE INTO categories_items (cat_id, item_id) VALUES (:cat_id, :item_id)', [
                'cat_id'  => $categoryId,
                'item_id' => $item_id,
            ]);
        }
    }
}

/**
 * Dropdown options for the item add/edit form, keyed by form field name.
 * Measurement units are grouped by the unit type stored against them.
 */
function fetchItemFormOptions(): array
{
    $units = [];

    foreach (dbAll('SELECT unit_id, unit_label, unit_symbol, unit_type
                    FROM inv_measurement_units ORDER BY unit_type, unit_id asc') as $unit) {
        $units[$unit['unit_type']][$unit['unit_id']] = $unit['unit_label'] . ' (' . $unit['unit_symbol'] . ')';
    }

    $options = ['item_measurement_unit' => $units];

    foreach (array_keys(taxonomies()) as $key) {
        $options['item_' . $key] = taxonomyOptions($key);
    }

    return $options;
}

/*
 * Dashboard
 */

/** Headline counts for the dashboard tiles. */
function fetchDashboardTotals(): array
{
    return dbRow('
        SELECT
            (SELECT COUNT(*) FROM inv_items) AS item_count,
            (SELECT COALESCE(SUM(item_quantity), 0) FROM inv_items) AS total_quantity,
            (SELECT COUNT(*) FROM inv_deployments) AS deployment_count,
            (SELECT COUNT(*) FROM inv_projects p
                INNER JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
                WHERE ps.project_status_name IN (\'Planning\', \'Active\', \'On Hold\')) AS open_project_count
    ');
}

/**
 * Items needing attention.
 *
 * $mode "low" is stock at or under its reorder level, "over" is stock
 * committed beyond what is actually held.
 */
function fetchStockWarnings(string $mode, int $limit = 0): array
{
    $having = ($mode === 'over')
        ? 'item_free_count < 0'
        : 'i.item_min_quantity > 0 AND item_free_count <= i.item_min_quantity';

    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_quantity, i.item_min_quantity,
                mu.unit_symbol, l.loc_name, sp.sup_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS
        . ' GROUP BY i.item_id HAVING ' . $having
        . ' ORDER BY item_free_count asc, i.item_name asc'
        . ($limit > 0 ? ' LIMIT ' . $limit : '')
    );
}

/** Most recently added or changed items. */
function fetchRecentItems(string $column, int $limit = 6): array
{
    $order = ($column === 'created') ? 'i.item_created_at' : 'i.item_updated_at';

    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_image, i.item_created_at, i.item_updated_at,
                l.loc_name, s.status_name
         FROM inv_items i
         LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
         LEFT JOIN inv_statuses s ON s.status_id = i.item_status
         ORDER BY ' . $order . ' DESC, i.item_id DESC
         LIMIT ' . (int)$limit
    );
}

function fetchRecentDeployments(int $limit = 6): array
{
    return dbAll('
        SELECT d.*, i.item_name, mu.unit_symbol
        FROM inv_deployments d
        INNER JOIN inv_items i ON i.item_id = d.dep_item_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        ORDER BY d.dep_timestamp DESC, d.dep_id DESC
        LIMIT ' . (int)$limit);
}

/*
 * Deployments
 */

function fetchItemDeployments($item_id): array
{
    return dbAll(
        'SELECT d.*, i.item_name
         FROM inv_deployments d
         LEFT JOIN inv_items i ON i.item_id = d.dep_item_id
         WHERE d.dep_item_id = :item_id
         ORDER BY d.dep_timestamp DESC',
        ['item_id' => $item_id]
    );
}

function countItemDeployments($item_id)
{
    return dbValue(
        'SELECT SUM(dep_quantity) FROM inv_deployments WHERE dep_item_id = :item_id',
        ['item_id' => $item_id],
        0
    ) ?? 0;
}

/*
 * Projects
 */

function fetchProjectStatuses(): array
{
    return dbAll('SELECT * FROM inv_project_statuses ORDER BY project_status_id');
}

function fetchProjects(): array
{
    return dbAll('
        SELECT
            p.*,
            ps.project_status_name,
            COUNT(DISTINCT a.assembly_id) AS assembly_count,
            COALESCE(SUM(ai.quantity_required), 0) AS required_quantity,
            COALESCE(SUM(ai.quantity_installed), 0) AS installed_quantity
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
        LEFT JOIN inv_project_assemblies a ON a.assembly_project_id = p.project_id
        LEFT JOIN inv_assembly_items ai ON ai.assembly_id = a.assembly_id
        GROUP BY p.project_id
        ORDER BY p.project_name
    ');
}

function fetchProject($project_id)
{
    return dbRow('
        SELECT p.*, ps.project_status_name
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
        WHERE p.project_id = :project_id
        LIMIT 1
    ', ['project_id' => $project_id]);
}

function getProjectSummary($project_id)
{
    return dbRow('
        SELECT
            COALESCE(SUM(ai.quantity_required), 0) AS required_quantity,
            COALESCE(SUM(ai.quantity_allocated), 0) AS allocated_quantity,
            COALESCE(SUM(ai.quantity_installed), 0) AS installed_quantity
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        WHERE a.assembly_project_id = :project_id
    ', ['project_id' => $project_id]);
}

/**
 * What a project still needs, one row per item across all of its assemblies.
 *
 * free_elsewhere is the stock not already committed anywhere, so the shortfall
 * worked out from it is what actually has to be bought.
 */
function fetchProjectRequirements($project_id): array
{
    return dbAll('
        SELECT
            i.item_id,
            i.item_name,
            i.item_part_no,
            i.item_quantity,
            mu.unit_symbol,
            sp.sup_id,
            sp.sup_name,
            sp.sup_website,
            SUM(ai.quantity_required) AS required_quantity,
            SUM(ai.quantity_allocated) AS allocated_quantity,
            SUM(ai.quantity_installed) AS installed_quantity,
            i.item_quantity
                - COALESCE((SELECT SUM(dep_quantity) FROM inv_deployments WHERE dep_item_id = i.item_id), 0)
                - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items WHERE item_id = i.item_id), 0)
                AS free_elsewhere
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE a.assembly_project_id = :project_id
        GROUP BY i.item_id
        ORDER BY sp.sup_name, i.item_name
    ', ['project_id' => $project_id]);
}

/*
 * Assemblies
 */

function fetchProjectAssemblies($project_id): array
{
    return dbAll('
        SELECT
            a.*,
            COUNT(ai.assembly_item_id) AS item_count,
            COALESCE(SUM(ai.quantity_required), 0) AS quantity_required,
            COALESCE(SUM(ai.quantity_installed), 0) AS quantity_installed
        FROM inv_project_assemblies a
        LEFT JOIN inv_assembly_items ai ON ai.assembly_id = a.assembly_id
        WHERE a.assembly_project_id = :project_id
        GROUP BY a.assembly_id
        ORDER BY a.assembly_sort_order, a.assembly_name
    ', ['project_id' => $project_id]);
}

function fetchAssembly($assembly_id)
{
    return dbRow('
        SELECT a.*, p.project_id, p.project_name
        FROM inv_project_assemblies a
        INNER JOIN inv_projects p ON p.project_id = a.assembly_project_id
        WHERE a.assembly_id = :assembly_id
        LIMIT 1
    ', ['assembly_id' => $assembly_id]);
}

function fetchAssemblyItems($assembly_id): array
{
    return dbAll('
        SELECT ai.*, i.item_name, i.item_quantity, mu.unit_symbol
        FROM inv_assembly_items ai
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE ai.assembly_id = :assembly_id
        ORDER BY ai.assembly_item_sort_order, i.item_name
    ', ['assembly_id' => $assembly_id]);
}

function fetchAssemblyItem($assembly_item_id)
{
    return dbRow('
        SELECT ai.*, a.assembly_name, a.assembly_project_id, i.item_name, i.item_quantity, mu.unit_symbol
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE ai.assembly_item_id = :id
        LIMIT 1
    ', ['id' => $assembly_item_id]);
}

/**
 * Items an assembly is holding stock of. Read before deleting one, so the
 * reservations it gives up can be shared out again.
 */
function fetchAssemblyItemIds($assembly_id): array
{
    return array_column(dbAll(
        'SELECT DISTINCT item_id FROM inv_assembly_items WHERE assembly_id = :assembly_id',
        ['assembly_id' => $assembly_id]
    ), 'item_id');
}

/** The same, for every assembly on a project. */
function fetchProjectItemIds($project_id): array
{
    return array_column(dbAll(
        'SELECT DISTINCT ai.item_id
         FROM inv_assembly_items ai
         INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
         WHERE a.assembly_project_id = :project_id',
        ['project_id' => $project_id]
    ), 'item_id');
}

function fetchAvailableItemsForAssembly($assembly_id): array
{
    return dbAll('
        SELECT i.item_id, i.item_name, i.item_quantity, mu.unit_symbol,
               i.item_quantity
                   - COALESCE((SELECT SUM(dep_quantity) FROM inv_deployments
                               WHERE dep_item_id = i.item_id), 0)
                   - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items
                               WHERE item_id = i.item_id), 0)
                   AS item_free_count
        FROM inv_items i
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE NOT EXISTS (
            SELECT 1 FROM inv_assembly_items ai
            WHERE ai.assembly_id = :assembly_id AND ai.item_id = i.item_id
        )
        ORDER BY i.item_name
    ', ['assembly_id' => $assembly_id]);
}

/**
 * Every assembly holding stock of an item, for the item page. Shows where the
 * reserved and installed quantities on an item have gone.
 */
function fetchItemAssemblyUsage($item_id): array
{
    return dbAll('
        SELECT ai.*, a.assembly_id, a.assembly_name, p.project_id, p.project_name
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_projects p ON p.project_id = a.assembly_project_id
        WHERE ai.item_id = :item_id
        ORDER BY p.project_name, a.assembly_sort_order, a.assembly_name
    ', ['item_id' => $item_id]);
}

<?php

/*
 * Items
 */

const ITEM_LIST_JOINS = '
    FROM inv_items i
    LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
    LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
    LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
    LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
    LEFT JOIN inv_statuses s ON s.status_id = i.item_status
    LEFT JOIN categories_items ci ON i.item_id = ci.item_id
    LEFT JOIN inv_categories c ON ci.cat_id = c.cat_id
';

const ITEM_DEPLOYED_JOIN = '
    LEFT JOIN (
        SELECT dep_item_id, SUM(dep_quantity) AS item_deployed_count
        FROM inv_deployments
        GROUP BY dep_item_id
    ) d ON i.item_id = d.dep_item_id
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

        if ($value === false) {
            continue;
        }

        $clauses[] = $tax['itemFilter'] . ' = :' . $tax['param'];
        $params[$tax['param']] = $value;
        $applied[] = $key;
    }

    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params, $applied];
}

/**
 * Items for the listing page, honouring the query string filters.
 */
function fetchItems(string $where, array $params): array
{
    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_quantity, mu.unit_symbol, b.brand_name, sp.sup_name,
                c.cat_name, l.loc_name, s.status_name, d.item_deployed_count'
        . ITEM_LIST_JOINS . ITEM_DEPLOYED_JOIN . $where . ' ORDER BY item_name asc',
        $params
    );
}

/**
 * Items for the CSV export. The column order matches the export headings.
 */
function fetchItemsForExport(string $where, array $params): array
{
    return dbAll(
        'SELECT i.item_id, i.item_name, b.brand_name, c.cat_name, l.loc_name, s.status_name,
                i.item_quantity, d.item_deployed_count, i.item_notes'
        . ITEM_LIST_JOINS . ITEM_DEPLOYED_JOIN . $where . ' ORDER BY item_name asc',
        $params
    );
}

function fetchSingleItem($item_id)
{
    return dbRow(
        'SELECT i.*, mu.unit_symbol, b.brand_id, b.brand_name, sp.sup_id, sp.sup_name, sp.sup_website,
                c.cat_id, c.cat_name, l.loc_id, l.loc_name, s.status_id, s.status_name'
        . ITEM_LIST_JOINS . ' WHERE i.item_id = :item_id LIMIT 1',
        ['item_id' => $item_id]
    );
}

/**
 * Dropdown options for the item add/edit form, keyed by form field name.
 */
function fetchItemFormOptions(): array
{
    $units = [];

    foreach (dbAll('SELECT unit_id, unit_label, unit_symbol FROM inv_measurement_units ORDER BY unit_id asc') as $unit) {
        $units[$unit['unit_id']] = $unit['unit_label'] . ' (' . $unit['unit_symbol'] . ')';
    }

    $options = ['item_measurement_unit' => $units];

    foreach (array_keys(taxonomies()) as $key) {
        $options['item_' . $key] = taxonomyOptions($key);
    }

    return $options;
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
    $row = dbRow(
        'SELECT SUM(d.dep_quantity) AS total_deployments
         FROM inv_deployments d
         WHERE d.dep_item_id = :item_id
         GROUP BY d.dep_item_id',
        ['item_id' => $item_id]
    );

    return $row ? $row['total_deployments'] : 0;
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
        SELECT ai.*, i.item_name, i.item_quantity
        FROM inv_assembly_items ai
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        WHERE ai.assembly_id = :assembly_id
        ORDER BY ai.assembly_item_sort_order, i.item_name
    ', ['assembly_id' => $assembly_id]);
}

function fetchAssemblyItem($assembly_item_id)
{
    return dbRow('
        SELECT ai.*, a.assembly_name, a.assembly_project_id, i.item_name
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        WHERE ai.assembly_item_id = :id
        LIMIT 1
    ', ['id' => $assembly_item_id]);
}

function fetchAvailableItemsForAssembly($assembly_id): array
{
    return dbAll('
        SELECT i.item_id, i.item_name, i.item_quantity
        FROM inv_items i
        WHERE NOT EXISTS (
            SELECT 1 FROM inv_assembly_items ai
            WHERE ai.assembly_id = :assembly_id AND ai.item_id = i.item_id
        )
        ORDER BY i.item_name
    ', ['assembly_id' => $assembly_id]);
}

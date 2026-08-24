<?php

function fetchItemDeployments($item_id)
{
    global $db;

    try {
        $sql = '
            SELECT d.*, i.item_name
            FROM inv_deployments d
            LEFT JOIN inv_items i ON i.item_id = d.dep_item_id
            WHERE d.dep_item_id = :item_id
            ORDER BY d.dep_timestamp DESC
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'item_id' => $item_id
        ]);

        $deployments = $stmt->fetchAll();
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    return ($deployments) ? $deployments : [];
}

function countItemDeployments($item_id)
{
    global $db;

    try {
        $sql = '
            SELECT SUM(d.dep_quantity) as total_deployments
            FROM inv_deployments d
            WHERE d.dep_item_id = :item_id
            GROUP BY d.dep_item_id
        ';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'item_id' => $item_id
        ]);

        $deploymentCount = $stmt->fetch();
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    return ($deploymentCount) ? $deploymentCount['total_deployments'] : 0;
}

function fetchSingleItem($item_id)
{
    global $db;

    try {
        $sql = '
            SELECT i.*, mu.unit_symbol, b.brand_id, b.brand_name, sp.sup_id, sp.sup_name, sp.sup_website, c.cat_id, c.cat_name, l.loc_id, l.loc_name, s.status_id, s.status_name
            FROM inv_items i
            LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
            LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
            LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
            LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
            LEFT JOIN inv_statuses s ON s.status_id  = i.item_status
            LEFT JOIN categories_items ci ON i.item_id = ci.item_id
            LEFT JOIN inv_categories c ON ci.cat_id = c.cat_id
            WHERE i.item_id = :item_id
            LIMIT 1
        ';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'item_id' => $item_id
        ]);

        $item = $stmt->fetch();
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

    return $item;
}

function fetchProjectStatuses()
{
    global $db;

    $stmt = $db->prepare("
        SELECT *
        FROM inv_project_statuses
        ORDER BY project_status_id
    ");

    $stmt->execute();

    return $stmt->fetchAll();
}


function fetchProjects()
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            p.*,
            ps.project_status_name,
            COUNT(DISTINCT a.assembly_id) AS assembly_count
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps
            ON ps.project_status_id = p.project_status_id
        LEFT JOIN inv_project_assemblies a
            ON a.assembly_project_id = p.project_id
        GROUP BY p.project_id
        ORDER BY p.project_name
    ");

    $stmt->execute();

    return $stmt->fetchAll();
}


function fetchProject($project_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            p.*,
            ps.project_status_name
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps
            ON ps.project_status_id = p.project_status_id
        WHERE p.project_id = :project_id
        LIMIT 1
    ");

    $stmt->execute([
        'project_id' => $project_id
    ]);

    return $stmt->fetch();
}


function fetchProjectAssemblies($project_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            a.*,
            COUNT(ai.assembly_item_id) AS item_count,
            COALESCE(SUM(ai.quantity_required), 0) AS quantity_required,
            COALESCE(SUM(ai.quantity_installed), 0) AS quantity_installed
        FROM inv_project_assemblies a
        LEFT JOIN inv_assembly_items ai
            ON ai.assembly_id = a.assembly_id
        WHERE a.assembly_project_id = :project_id
        GROUP BY a.assembly_id
        ORDER BY
            a.assembly_sort_order,
            a.assembly_name
    ");

    $stmt->execute([
        'project_id' => $project_id
    ]);

    return $stmt->fetchAll();
}


function fetchAssembly($assembly_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            a.*,
            p.project_id,
            p.project_name
        FROM inv_project_assemblies a
        INNER JOIN inv_projects p
            ON p.project_id = a.assembly_project_id
        WHERE a.assembly_id = :assembly_id
        LIMIT 1
    ");

    $stmt->execute([
        'assembly_id' => $assembly_id
    ]);

    return $stmt->fetch();
}


function fetchAssemblyItems($assembly_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            ai.*,
            i.item_name,
            i.item_quantity
        FROM inv_assembly_items ai
        INNER JOIN inv_items i
            ON i.item_id = ai.item_id
        WHERE ai.assembly_id = :assembly_id
        ORDER BY
            ai.assembly_item_sort_order,
            i.item_name
    ");

    $stmt->execute([
        'assembly_id' => $assembly_id
    ]);

    return $stmt->fetchAll();
}


function fetchAssemblyItem($assembly_item_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            ai.*,
            a.assembly_name,
            a.assembly_project_id,
            i.item_name
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a
            ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i
            ON i.item_id = ai.item_id
        WHERE ai.assembly_item_id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $assembly_item_id
    ]);

    return $stmt->fetch();
}


function fetchAvailableItemsForAssembly($assembly_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            i.item_id,
            i.item_name,
            i.item_quantity
        FROM inv_items i
        WHERE NOT EXISTS (
            SELECT 1
            FROM inv_assembly_items ai
            WHERE ai.assembly_id = :assembly_id
              AND ai.item_id = i.item_id
        )
        ORDER BY i.item_name
    ");

    $stmt->execute([
        'assembly_id' => $assembly_id
    ]);

    return $stmt->fetchAll();
}


function getProjectSummary($project_id)
{
    global $db;

    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(ai.quantity_required), 0) AS required_quantity,
            COALESCE(SUM(ai.quantity_allocated), 0) AS allocated_quantity,
            COALESCE(SUM(ai.quantity_installed), 0) AS installed_quantity
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a
            ON a.assembly_id = ai.assembly_id
        WHERE a.assembly_project_id = :project_id
    ");

    $stmt->execute([
        'project_id' => $project_id
    ]);

    return $stmt->fetch();
}
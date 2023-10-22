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
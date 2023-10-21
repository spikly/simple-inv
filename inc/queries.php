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
            SELECT i.*, ci.cat_id 
            FROM inv_items i 
            INNER JOIN categories_items ci 
            ON i.item_id = ci.item_id 
            WHERE i.item_id = :item_id
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
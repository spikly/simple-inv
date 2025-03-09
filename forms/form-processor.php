<?php

function addNewBrand($db, $postData)
{
    if(!empty($postData['brand_name'])) {

        $formData = [
            'brand_name' => trim($postData['brand_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_brands (brand_name) VALUES (:brand_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            return [
                'success' => true,
                'newId' => $db->lastInsertId(),
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

    }else{
        return [
            'success' => false,
            'error' => 'Brand name cannot be empty',
        ];
    }
}

function addNewSupplier($db, $postData)
{
    if(!empty($postData['sup_name'])) {

        $formData = [
            'sup_name' => trim($postData['sup_name']),
            'sup_website' => $postData['sup_website'],
        ];

        try {
            $sql = 'INSERT INTO inv_suppliers (sup_name, sup_website) VALUES (:sup_name, :sup_website)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            return [
                'success' => true,
                'newId' => $db->lastInsertId(),
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

    }else{
        return [
            'success' => false,
            'error' => 'Supplier name cannot be empty',
        ];
    }
}

function addNewCategory($db, $postData)
{
    if(!empty($postData['cat_name'])) {

        $formData = [
            'cat_name' => trim($postData['cat_name']),
            'cat_slug' => slugify($postData['cat_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_categories (cat_name, cat_slug) VALUES (:cat_name, :cat_slug)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            return [
                'success' => true,
                'newId' => $db->lastInsertId(),
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

    }else{
        return [
            'success' => false,
            'error' => 'Category name cannot be empty',
        ];
    }
}

function addNewLocation($db, $postData)
{
    if(!empty($postData['loc_name'])) {

        $formData = [
            'loc_name' => trim($postData['loc_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_locations (loc_name) VALUES (:loc_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            return [
                'success' => true,
                'newId' => $db->lastInsertId(),
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

    }else{
        return [
            'success' => false,
            'error' => 'Location name cannot be empty',
        ];
    }
}

function addNewStatus($db, $postData)
{
    if(!empty($postData['status_name'])) {

        $formData = [
            'status_name' => trim($postData['status_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_statuses (status_name) VALUES (:status_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            return [
                'success' => true,
                'newId' => $db->lastInsertId(),
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

    }else{
        return [
            'success' => false,
            'error' => 'Status name cannot be empty',
        ];
    }
}
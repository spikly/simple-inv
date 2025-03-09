<?php

$formData = [];
$formMessage = false;

if(isset($_POST['add_item_submit'])) {
    if(empty($_POST['item_name'])) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Item name cannot be empty',
        ];
    }elseif($_POST['item_measurement_unit'] < 1) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Item measurement unit cannot be empty',
        ];
    }elseif($_POST['item_brand'] < 1) {
        $formMessage = [
            'status' => 'error',
            'message' => 'You must select a category',
        ];
    }elseif($_POST['item_category'] < 1) {
        $formMessage = [
            'status' => 'error',
            'message' => 'You must select a category',
        ];
    }elseif($_POST['item_location'] < 1) {
        $formMessage = [
            'status' => 'error',
            'message' => 'You must select a location',
        ];
    }elseif($_POST['item_status'] < 1) {
        $formMessage = [
            'status' => 'error',
            'message' => 'You must select a status',
        ];
    }else{
        $formData = [
            'item_name' => trim($_POST['item_name']),
            'item_quantity' => trim($_POST['item_quantity']),
            'item_measurement_unit' => trim($_POST['item_measurement_unit']),
            'item_brand' => $_POST['item_brand'],
            'item_supplier' => ($_POST['item_supplier'] >= 1) ? $_POST['item_supplier'] : null,
            'item_location' => $_POST['item_location'],
            'item_status' => slugify($_POST['item_status']),
            'item_notes' => trim($_POST['item_notes']),
        ];

        try {
            $sql = 'INSERT INTO inv_items (item_name, item_quantity, item_measurement_unit, item_brand_id, item_sup_id, item_loc_id, item_status, item_notes) VALUES (:item_name, :item_quantity, :item_measurement_unit, :item_brand, :item_supplier, :item_location, :item_status, :item_notes)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);
            $lastId = $db->lastInsertId();

            $sql = 'INSERT INTO categories_items (cat_id, item_id) VALUES (:cat_id, :item_id)';
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'cat_id' => $_POST['item_category'],
                'item_id' => $lastId,
            ]);

            $formMessage = [
                'status' => 'success',
                'message' => 'Item added! <a href="index.php?page=view-item&item_id=' . $lastId . '">View Item</a>',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}

$munits = [];
$brands = [];
$suppliers = [];
$categories = [];
$locations = [];
$statuses = [];

$sql = 'SELECT unit_id, unit_label, unit_symbol FROM inv_measurement_units ORDER BY unit_id asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$munits = $stmt->fetchAll();

$sql = 'SELECT brand_id, brand_name FROM inv_brands ORDER BY brand_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$brands = $stmt->fetchAll();

$sql = 'SELECT sup_id, sup_name FROM inv_suppliers ORDER BY sup_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$suppliers = $stmt->fetchAll();

$sql = 'SELECT cat_id, cat_name FROM inv_categories ORDER BY cat_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll();

$sql = 'SELECT loc_id, loc_name FROM inv_locations ORDER BY loc_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$locations = $stmt->fetchAll();

$sql = 'SELECT status_id, status_name FROM inv_statuses ORDER BY status_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$statuses = $stmt->fetchAll();

?>

<div class="flex-nav">
    <h2>
        Add Item
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="item_name">Item Name</label>
        <input type="text" name="item_name" />
    </p>
    <p>
        <label for="item_quantity">Item Quantity</label>
        <input type="number" name="item_quantity" value="1" />
    </p>
    <p>
        <label for="item_measurement_unit">Measurement Unit</label>
        <select name="item_measurement_unit">
            <option value="0">Select</option>
            <?php foreach($munits as $munit): ?>
                <option value="<?php echo $munit['unit_id']; ?>"><?php echo escapeHtml($munit['unit_label']); ?> (<?php echo escapeHtml($munit['unit_symbol']); ?>)</option>
            <?php endforeach ?>
        </select>
    </p>
    <p>
        <label for="item_brand">Brand</label>
        <select name="item_brand" id="item_brand">
            <option value="0">Select</option>
            <?php foreach($brands as $brand): ?>
                <option value="<?php echo $brand['brand_id']; ?>"><?php echo escapeHtml($brand['brand_name']); ?></option>
            <?php endforeach ?>
        </select>
        <button class="add-new-attribute-value" id="add_new_brand" title="Add new Brand">+</button>
    </p>
    <p>
        <label for="item_supplier">Supplier</label>
        <select name="item_supplier" id="item_supplier">
            <option value="0">Select</option>
            <?php foreach($suppliers as $supplier): ?>
                <option value="<?php echo $supplier['sup_id']; ?>"><?php echo escapeHtml($supplier['sup_name']); ?></option>
            <?php endforeach ?>
        </select>
        <button class="add-new-attribute-value" id="add_new_supplier" title="Add new Supplier">+</button>
    </p>
    <p>
        <label for="item_category">Category</label>
        <select name="item_category" id="item_category">
            <option value="0">Select</option>
            <?php foreach($categories as $category): ?>
                <option value="<?php echo $category['cat_id']; ?>"><?php echo escapeHtml($category['cat_name']); ?></option>
            <?php endforeach ?>
        </select>
        <button class="add-new-attribute-value" id="add_new_category" title="Add new Category">+</button>
    </p>
    <p>
        <label for="item_location">Location</label>
        <select name="item_location" id="item_location">
            <option value="0">Select</option>
            <?php foreach($locations as $location): ?>
                <option value="<?php echo $location['loc_id']; ?>"><?php echo escapeHtml($location['loc_name']); ?></option>
            <?php endforeach ?>
        </select>
        <button class="add-new-attribute-value" id="add_new_location" title="Add new Location">+</button>
    </p>
    <p>
        <label for="item_status">Status</label>
        <select name="item_status" id="item_status">
            <option value="0">Select</option>
            <?php foreach($statuses as $status): ?>
                <option value="<?php echo $status['status_id']; ?>"><?php echo escapeHtml($status['status_name']); ?></option>
            <?php endforeach ?>
        </select>
        <button class="add-new-attribute-value" id="add_new_status" title="Add new Status">+</button>
    </p>
    <p>
        <label for="item_notes">Notes (optional)</label>
        <textarea name="item_notes"></textarea>
    </p>
    <p>
        <input type="submit" name="add_item_submit" value="Save">
    </p>
</form>
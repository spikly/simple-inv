<?php

$formData = [];
$formMessage = false;

if(isset($_POST['add_item_submit'])) {
    if(empty($_POST['item_name'])) {
        $formMessage = [
            'status' => 'error',
            'message' => 'Item name cannot be empty',
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
            'item_name' => $_POST['item_name'],
            'item_brand' => $_POST['item_brand'],
            'item_location' => $_POST['item_location'],
            'item_status' => slugify($_POST['item_status']),
            'item_deployed_loc' => $_POST['item_deployed_loc'],
            'item_notes' => $_POST['item_notes'],
        ];

        try {
            $sql = 'INSERT INTO inv_items (item_name, item_brand_id, item_loc_id, item_status, item_deployed_loc, item_notes) VALUES (:item_name, :item_brand, :item_location, :item_status, :item_deployed_loc, :item_notes)';
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
                'message' => 'Item added!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}

$brands = [];
$categories = [];
$locations = [];
$statuses = [];

$sql = 'SELECT brand_id, brand_name FROM inv_brands ORDER BY brand_name asc';
$stmt = $db->prepare($sql);
$stmt->execute();
$brands = $stmt->fetchAll();

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
        <label for="item_brand">Brand</label>
        <select name="item_brand">
            <option value="0">Select</option>
            <?php foreach($brands as $brand): ?>
                <option value="<?php echo $brand['brand_id']; ?>"><?php echo $brand['brand_name']; ?></option>
            <?php endforeach ?>
        </select>
    </p>
    <p>
        <label for="item_category">Category</label>
        <select name="item_category">
            <option value="0">Select</option>
            <?php foreach($categories as $category): ?>
                <option value="<?php echo $category['cat_id']; ?>"><?php echo $category['cat_name']; ?></option>
            <?php endforeach ?>
        </select>
    </p>
    <p>
        <label for="item_location">Location</label>
        <select name="item_location">
            <option value="0">Select</option>
            <?php foreach($locations as $location): ?>
                <option value="<?php echo $location['loc_id']; ?>"><?php echo $location['loc_name']; ?></option>
            <?php endforeach ?>
        </select>
    </p>
    <p>
        <label for="item_status">Status</label>
        <select name="item_status">
            <option value="0">Select</option>
            <?php foreach($statuses as $status): ?>
                <option value="<?php echo $status['status_id']; ?>"><?php echo $status['status_name']; ?></option>
            <?php endforeach ?>
        </select>
    </p>
    <p>
        <label for="item_deployed_loc">Deployed Location (optional)</label>
        <input type="text" name="item_deployed_loc" />
    </p>
    <p>
        <label for="item_notes">Notes (optional)</label>
        <textarea name="item_notes"></textarea>
    </p>
    <p>
        <input type="submit" name="add_item_submit" value="Save">
    </p>
</form>
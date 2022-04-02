<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['brand_id'])) ? $_GET['brand_id'] : false;

if(isset($_POST['edit_brand_submit'])) {
    if(!empty($_POST['brand_name'])) {

        $formData = [
            'edit_id' => $edit_id,
            'brand_name' => $_POST['brand_name'],
        ];

        try {
            $sql = 'UPDATE inv_brands SET brand_name = :brand_name WHERE brand_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Brand updated!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }else{
        $formMessage = [
            'status' => 'error',
            'message' => 'Brand name cannot be empty',
        ];
    }
}elseif(isset($_POST['delete_brand_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_brands WHERE brand_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Brand deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_brands WHERE brand_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $brand = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Brand
    </h2>
</div>

<?php if($brand): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="brand_name">Brand Name</label>
        <input type="text" name="brand_name" value="<?php echo escapeHtml($brand['brand_name']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_brand_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Brand
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_brand_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No brand found
    </p>
<?php endif; ?>
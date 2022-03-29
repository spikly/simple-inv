<?php

$formData = [];
$formMessage = false;

if(isset($_POST['brand_name'])) {
    if(!empty($_POST['brand_name'])) {

        $formData = [
            'brand_name' => $_POST['brand_name'],
        ];

        try {
            $sql = 'INSERT INTO inv_brands (brand_name) VALUES (:brand_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Brand added!',
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
}

?>

<div class="flex-nav">
    <h2>
        Add Brand
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="brand_name">Brand Name</label>
        <input type="text" name="brand_name" />
    </p>
    <p>
        <input type="submit" name="new_brand_submit" value="Save">
    </p>
</form>
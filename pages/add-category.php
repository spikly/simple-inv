<?php

$formData = [];
$formMessage = false;

if(isset($_POST['cat_name'])) {
    if(!empty($_POST['cat_name'])) {

        $formData = [
            'cat_name' => $_POST['cat_name'],
            'cat_slug' => slugify($_POST['cat_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_categories (cat_name, cat_slug) VALUES (:cat_name, :cat_slug)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Category added!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }else{
        $formMessage = [
            'status' => 'error',
            'message' => 'Category name cannot be empty',
        ];
    }
}

?>

<div class="flex-nav">
    <h2>
        Add Category
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="cat_name">Category Name</label>
        <input type="text" name="cat_name" />
    </p>
    <p>
        <input type="submit" name="new_cat_submit" value="Save">
    </p>
</form>
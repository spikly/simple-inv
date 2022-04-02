<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['category_id'])) ? $_GET['category_id'] : false;

if(isset($_POST['edit_cat_submit'])) {
    if(!empty($_POST['cat_name'])) {

        $formData = [
            'edit_id' => $edit_id,
            'cat_name' => $_POST['cat_name'],
            'cat_slug' => slugify($_POST['cat_name']),
        ];

        try {
            $sql = 'UPDATE inv_categories SET cat_name = :cat_name, cat_slug = :cat_slug WHERE cat_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Category updated!',
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
}elseif(isset($_POST['delete_cat_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_categories WHERE cat_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Category deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_categories WHERE cat_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $category = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Category
    </h2>
</div>

<?php if($category): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="cat_name">Category Name</label>
        <input type="text" name="cat_name" value="<?php echo escapeHtml($category['cat_name']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_cat_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Category
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_cat_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No category found
    </p>
<?php endif; ?>
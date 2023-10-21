<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['supplier_id'])) ? $_GET['supplier_id'] : false;

if(isset($_POST['edit_sup_submit'])) {
    if(!empty($_POST['sup_name'])) {

        $formData = [
            'edit_id' => $edit_id,
            'sup_name' => trim($_POST['sup_name']),
            'sup_website' => slugify($_POST['sup_website']),
        ];

        try {
            $sql = 'UPDATE inv_suppliers SET sup_name = :sup_name, sup_website = :sup_website WHERE sup_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Supplier updated!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }else{
        $formMessage = [
            'status' => 'error',
            'message' => 'Supplier name cannot be empty',
        ];
    }
}elseif(isset($_POST['delete_sup_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_suppliers WHERE sup_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Supplier deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_suppliers WHERE sup_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $supplier = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Supplier
    </h2>
</div>

<?php if($supplier): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="sup_name">Supplier Name</label>
        <input type="text" name="sup_name" value="<?php echo escapeHtml($supplier['sup_name']); ?>" />
    </p>
    <p>
        <label for="sup_website">Supplier Website (optional)</label>
        <input type="text" name="sup_website" value="<?php echo escapeHtml($supplier['sup_website']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_sup_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Supplier
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_sup_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No supplier found
    </p>
<?php endif; ?>
<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['location_id'])) ? $_GET['location_id'] : false;

if(isset($_POST['edit_loc_submit'])) {
    if(!empty($_POST['loc_name'])) {

        $formData = [
            'edit_id' => $edit_id,
            'loc_name' => trim($_POST['loc_name']),
        ];

        try {
            $sql = 'UPDATE inv_locations SET loc_name = :loc_name WHERE loc_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Location updated!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }else{
        $formMessage = [
            'status' => 'error',
            'message' => 'Location name cannot be empty',
        ];
    }
}elseif(isset($_POST['delete_loc_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_locations WHERE loc_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Location deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_locations WHERE loc_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $location = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Location
    </h2>
</div>

<?php if($location): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="loc_name">Location Name</label>
        <input type="text" name="loc_name" value="<?php echo escapeHtml($location['loc_name']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_loc_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Location
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_loc_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No location found
    </p>
<?php endif; ?>
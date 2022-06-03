<?php

$formData = [];
$formMessage = false;
$deleted = false;

$edit_id = (isset($_GET['status_id'])) ? $_GET['status_id'] : false;

if(isset($_POST['edit_status_submit'])) {
    if(!empty($_POST['status_name'])) {

        $formData = [
            'edit_id' => $edit_id,
            'status_name' => trim($_POST['status_name']),
        ];

        try {
            $sql = 'UPDATE inv_statuses SET status_name = :status_name WHERE status_id = :edit_id';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Status updated!',
            ];
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

    }else{
        $formMessage = [
            'status' => 'error',
            'message' => 'Status name cannot be empty',
        ];
    }
}elseif(isset($_POST['delete_status_submit'])) {
    $formData = [
        'edit_id' => $edit_id,
    ];

    try {
        $sql = 'DELETE FROM inv_statuses WHERE status_id = :edit_id LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($formData);

        $formMessage = [
            'status' => 'success',
            'message' => 'Status deleted!',
        ];

        $deleted = true;

    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }

}

try {
    $sql = 'SELECT * FROM inv_statuses WHERE status_id = :edit_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'edit_id' => $edit_id
    ]);

    $status = $stmt->fetch();
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

?>

<div class="flex-nav">
    <h2>
        Edit Status
    </h2>
</div>

<?php if($status): ?>
<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="status_name">Status Name</label>
        <input type="text" name="status_name" value="<?php echo escapeHtml($status['status_name']); ?>" />
    </p>
    <p>
        <input type="submit" name="edit_status_submit" value="Save">
    </p>
</form>
<div class="flex-nav extra-padding">
    <h2>
        Delete Status
    </h2>
</div>
<form method="post">
    <p>
        This action cannot be undone.
    </p>

    <input type="submit" name="delete_status_submit" class="delete" value="Delete">
</form>
<?php elseif($deleted): ?>
    <?php echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>'; ?>
<?php else: ?>
    <p>
        No status found
    </p>
<?php endif; ?>
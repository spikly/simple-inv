<?php

$formData = [];
$formMessage = false;

if(isset($_POST['add_status_submit'])) {
    if(!empty($_POST['status_name'])) {

        $formData = [
            'status_name' => trim($_POST['status_name']),
        ];

        try {
            $sql = 'INSERT INTO inv_statuses (status_name) VALUES (:status_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Status added!',
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
}

?>

<div class="flex-nav">
    <h2>
        Add Status
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="status_name">Status Name</label>
        <input type="text" name="status_name" />
    </p>
    <p>
        <input type="submit" name="add_status_submit" value="Save">
    </p>
</form>
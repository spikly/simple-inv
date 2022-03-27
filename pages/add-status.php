<?php

$formData = [];
$formMessage = false;

if(isset($_POST['status_name'])) {
    if(!empty($_POST['status_name'])) {

        $formData = [
            'status_name' => $_POST['status_name'],
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

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <label for="status_name">Status Name</label>
    <input type="text" name="status_name" />
    <input type="submit" name="new_status_submit" value="Save">
</form>
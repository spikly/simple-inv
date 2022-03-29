<?php

$formData = [];
$formMessage = false;

if(isset($_POST['loc_name'])) {
    if(!empty($_POST['loc_name'])) {

        $formData = [
            'loc_name' => $_POST['loc_name'],
        ];

        try {
            $sql = 'INSERT INTO inv_locations (loc_name) VALUES (:loc_name)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Location added!',
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
}

?>

<div class="flex-nav">
    <h2>
        Add Location
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="loc_name">Location Name</label>
        <input type="text" name="loc_name" />
    </p>
    <p>
        <input type="submit" name="new_loc_submit" value="Save">
    </p>
</form>
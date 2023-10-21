<?php

$formData = [];
$formMessage = false;

if(isset($_POST['add_sup_submit'])) {
    if(!empty($_POST['sup_name'])) {

        $formData = [
            'sup_name' => trim($_POST['sup_name']),
            'sup_website' => $_POST['sup_website'],
        ];

        try {
            $sql = 'INSERT INTO inv_suppliers (sup_name, sup_website) VALUES (:sup_name, :sup_website)';
            $stmt = $db->prepare($sql);
            $stmt->execute($formData);

            $formMessage = [
                'status' => 'success',
                'message' => 'Supplier added!',
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
}

?>

<div class="flex-nav">
    <h2>
        Add Supplier
    </h2>
</div>

<form method="post">
    <?php echo ($formMessage) ? '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>' : ''; ?>
    <p>
        <label for="sup_name">Supplier Name</label>
        <input type="text" name="sup_name" />
    </p>
    <p>
        <label for="sup_website">Supplier Website</label>
        <input type="url" name="sup_website" />
    </p>
    <p>
        <input type="submit" name="add_sup_submit" value="Save">
    </p>
</form>
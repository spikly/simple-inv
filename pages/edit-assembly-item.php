<?php

$assembly_item_id =
    (int)($_GET['assembly_item_id'] ?? 0);

$assemblyItem =
    fetchAssemblyItem($assembly_item_id);

if(!$assemblyItem) {

    echo '<p>Assembly item not found.</p>';
    return;

}


$formData = [
    'quantity_required' =>
        $assemblyItem['quantity_required'],

    'quantity_allocated' =>
        $assemblyItem['quantity_allocated'],

    'quantity_installed' =>
        $assemblyItem['quantity_installed'],

    'assembly_item_notes' =>
        $assemblyItem['assembly_item_notes'],
];

$formMessage = false;


if(isset($_POST['edit_assembly_item_submit'])) {

    $formData = [
        'quantity_required' =>
            (float)$_POST['quantity_required'],

        'quantity_allocated' =>
            (float)$_POST['quantity_allocated'],

        'quantity_installed' =>
            (float)$_POST['quantity_installed'],

        'assembly_item_notes' =>
            trim($_POST['assembly_item_notes']),
    ];


    if($formData['quantity_required'] <= 0) {

        $formMessage = [
            'status' => 'error',
            'message' =>
                'Quantity required must be greater than zero.',
        ];

    } elseif(
        $formData['quantity_installed'] >
        $formData['quantity_allocated']
    ) {

        $formMessage = [
            'status' => 'error',
            'message' =>
                'Installed quantity cannot exceed allocated quantity.',
        ];

    } else {

        try {

            $sql = "
                UPDATE inv_assembly_items

                SET
                    quantity_required = :quantity_required,
                    quantity_allocated = :quantity_allocated,
                    quantity_installed = :quantity_installed,
                    assembly_item_notes = :assembly_item_notes

                WHERE assembly_item_id = :assembly_item_id
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([

                'quantity_required' =>
                    $formData['quantity_required'],

                'quantity_allocated' =>
                    $formData['quantity_allocated'],

                'quantity_installed' =>
                    $formData['quantity_installed'],

                'assembly_item_notes' =>
                    $formData['assembly_item_notes']
                        ?: null,

                'assembly_item_id' =>
                    $assembly_item_id,
            ]);


            $formMessage = [
                'status' => 'success',
                'message' =>
                    'Assembly part updated!',
            ];


            $assemblyItem =
                fetchAssemblyItem(
                    $assembly_item_id
                );

        } catch(\PDOException $e) {

            throw new \PDOException(
                $e->getMessage(),
                (int)$e->getCode()
            );
        }
    }
}


if(isset($_POST['delete_assembly_item_submit'])) {

    try {

        $sql = "
            DELETE FROM inv_assembly_items

            WHERE assembly_item_id = :assembly_item_id
        ";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            'assembly_item_id' =>
                $assembly_item_id,
        ]);


        $formMessage = [
            'status' => 'success',
            'message' => 'Part removed from assembly!',
        ];

    } catch(\PDOException $e) {

        throw new \PDOException(
            $e->getMessage(),
            (int)$e->getCode()
        );
    }
}

?>

<div class="flex-nav">

    <h2>
        Edit Assembly Part
    </h2>

    <nav class="onpage-nav">

    <a href="index.php?page=view-assembly&assembly_id=<?php
        echo (int)$assemblyItem['assembly_id'];
    ?>">
    Back to Assembly
    </a>

    </nav>

</div>


<p>

    Item:

    <strong>
        <?php
        echo escapeHtml(
            $assemblyItem['item_name']
        );
        ?>
    </strong>

</p>


<p>

    Assembly:

    <strong>
        <?php
        echo escapeHtml(
            $assemblyItem['assembly_name']
        );
        ?>
    </strong>

</p>


<form method="post">

    <?php

    echo ($formMessage)
        ? '<p class="form-message form-' .
            $formMessage['status'] .
            '">' .
            $formMessage['message'] .
            '</p>'
        : '';

    ?>


    <p>

        <label for="quantity_required">
            Quantity Required
        </label>

        <input
            type="number"
            step="1"
            min="0"
            name="quantity_required"
            id="quantity_required"
            value="<?php
                echo $formData['quantity_required'];
            ?>"
            required
        >

    </p>


    <p>

        <label for="quantity_allocated">
            Quantity Allocated
        </label>

        <input
            type="number"
            step="1"
            min="0"
            name="quantity_allocated"
            id="quantity_allocated"
            value="<?php
                echo $formData['quantity_allocated'];
            ?>"
        >

    </p>


    <p>

        <label for="quantity_installed">
            Quantity Installed
        </label>

        <input
            type="number"
            step="1"
            min="0"
            name="quantity_installed"
            id="quantity_installed"
            value="<?php
                echo $formData['quantity_installed'];
            ?>"
        >

    </p>


    <p>

        <label for="assembly_item_notes">
            Notes
        </label>

        <textarea
            name="assembly_item_notes"
            id="assembly_item_notes"
        ><?php
            echo escapeHtml(
                $formData['assembly_item_notes']
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="edit_assembly_item_submit"
            value="Save"
        >

    </p>

</form>


<hr>


<form
    method="post"
    onsubmit="return confirm(
        'Remove this part from the assembly?'
    );"
>

    <p>

        <input
            type="submit"
            name="delete_assembly_item_submit"
            value="Remove Part"
            class="delete"
        >

    </p>

</form>
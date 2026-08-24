<?php

$formData = [];
$formMessage = false;

$assembly_id = (int)($_GET['assembly_id'] ?? 0);

$assembly = fetchAssembly($assembly_id);

if(!$assembly) {
    echo '<p>Assembly not found.</p>';
    return;
}

$items = fetchAvailableItemsForAssembly($assembly_id);


if(isset($_POST['add_assembly_item_submit'])) {

    if(!empty($_POST['item_id'])) {

        $formData = [
            'assembly_id' => $assembly_id,
            'item_id' => (int)$_POST['item_id'],
            'quantity_required' => (float)$_POST['quantity_required'],
            'quantity_allocated' => (float)$_POST['quantity_allocated'],
            'quantity_installed' => (float)$_POST['quantity_installed'],
            'assembly_item_notes' => trim($_POST['assembly_item_notes']),
        ];


        if($formData['quantity_required'] <= 0) {

            $formMessage = [
                'status' => 'error',
                'message' => 'Quantity required must be greater than zero.',
            ];

        } elseif(
            $formData['quantity_installed'] >
            $formData['quantity_allocated']
        ) {

            $formMessage = [
                'status' => 'error',
                'message' => 'Installed quantity cannot exceed allocated quantity.',
            ];

        } else {

            try {

                $sql = "
                    INSERT INTO inv_assembly_items
                    (
                        assembly_id,
                        item_id,
                        quantity_required,
                        quantity_allocated,
                        quantity_installed,
                        assembly_item_notes
                    )
                    VALUES
                    (
                        :assembly_id,
                        :item_id,
                        :quantity_required,
                        :quantity_allocated,
                        :quantity_installed,
                        :assembly_item_notes
                    )
                ";

                $stmt = $db->prepare($sql);
                $stmt->execute($formData);

                $formMessage = [
                    'status' => 'success',
                    'message' => 'Part added to assembly!',
                ];

                $formData = [];

                $items =
                    fetchAvailableItemsForAssembly(
                        $assembly_id
                    );

            } catch(\PDOException $e) {

                throw new \PDOException(
                    $e->getMessage(),
                    (int)$e->getCode()
                );
            }
        }

    } else {

        $formMessage = [
            'status' => 'error',
            'message' => 'Please select an item.',
        ];
    }
}

?>

<div class="flex-nav">

    <h2>
        Add Part
    </h2>

    <nav class="onpage-nav">

    <a href="index.php?page=view-assembly&assembly_id=<?php
        echo (int)$assembly['assembly_id'];
    ?>">
    Back to Assembly
    </a>

    </nav>

</div>


<p>
    Assembly:
    <strong>
        <?php echo escapeHtml($assembly['assembly_name']); ?>
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

        <label for="item_id">
            Item
        </label>

        <select
            name="item_id"
            id="item_id"
            required
        >

            <option value="">
                Select an item
            </option>

            <?php foreach($items as $item): ?>

                <option
                    value="<?php
                        echo (int)$item['item_id'];
                    ?>"
                >

                    <?php
                    echo escapeHtml(
                        $item['item_name']
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </p>


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
                echo $formData['quantity_required'] ?? 1;
            ?>"
        />

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
                echo $formData['quantity_allocated'] ?? 0;
            ?>"
        />

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
                echo $formData['quantity_installed'] ?? 0;
            ?>"
        />

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
                $formData['assembly_item_notes'] ?? ''
            );
        ?></textarea>

    </p>


    <p>

        <input
            type="submit"
            name="add_assembly_item_submit"
            value="Save"
        >

    </p>

</form>
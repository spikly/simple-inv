<?php

function projectColumns(array $post): array
{
    return [
        'project_name'        => trim($post['project_name']),
        'project_reference'   => textOrNull($post, 'project_reference'),
        'project_description' => textOrNull($post, 'project_description'),
        'project_status_id'   => (int)$post['project_status_id'],
        'project_notes'       => textOrNull($post, 'project_notes'),
    ];
}

function renderProjectForm(array $values, string $submitName, $formMessage = false): void
{
    template('project/form', [
        'values'      => $values,
        'submitName'  => $submitName,
        'formMessage' => $formMessage,
        'statuses'    => array_column(fetchProjectStatuses(), 'project_status_name', 'project_status_id'),
    ]);
}

function assemblyColumns(array $post): array
{
    return [
        'assembly_name'        => trim($post['assembly_name']),
        'assembly_description' => textOrNull($post, 'assembly_description'),
        'assembly_notes'       => textOrNull($post, 'assembly_notes'),
        'assembly_sort_order'  => (int)$post['assembly_sort_order'],
    ];
}

function renderAssemblyForm(array $values, string $submitName, $formMessage = false): void
{
    template('project/assembly-form', compact('values', 'submitName', 'formMessage'));
}

/** The allocated quantity is not among them; see inc/allocation.php. */
function assemblyItemColumns(array $post): array
{
    return [
        'quantity_required'   => (float)$post['quantity_required'],
        'quantity_installed'  => (float)$post['quantity_installed'],
        'assembly_item_notes' => textOrNull($post, 'assembly_item_notes'),
    ];
}

function validateAssemblyItem(array $columns): array
{
    $errors = [];

    if ($columns['quantity_required'] <= 0) {
        $errors['quantity_required'] = 'Quantity required must be greater than zero.';
    }

    if ($columns['quantity_installed'] < 0) {
        $errors['quantity_installed'] = 'Installed quantity cannot be negative.';
    } elseif (!$errors && $columns['quantity_installed'] > $columns['quantity_required']) {
        // Only once the required quantity is valid.
        $errors['quantity_installed'] = 'Installed quantity cannot exceed the quantity required.';
    }

    return $errors;
}

/** Installing consumes stock, so any increase since the last save must be free. */
function validateAssemblyInstall($item_id, $assembly_item_id, float $installed, float $installedBefore): array
{
    $delta = $installed - $installedBefore;

    if ($delta <= 0) {
        return [];
    }

    $available = itemStockAvailable($item_id, $assembly_item_id);

    if ($delta > $available) {
        return ['quantity_installed' => 'Only ' . formatQuantity($available)
            . ' of this item is free to install.'];
    }

    return [];
}

/** Appended to a part's success message. */
function assemblyStockMessage(array $stock): string
{
    $message = ' Reserved ' . formatQuantity($stock['allocated']) . ' from stock';

    return $stock['short'] > 0
        ? $message . ', ' . formatQuantity($stock['short']) . ' short.'
        : $message . '.';
}

/** $stockNote is shown against the required field. */
function renderAssemblyItemFields(array $values, string $stockNote = ''): void
{
    template('project/assembly-item-fields', compact('values', 'stockNote'));
}

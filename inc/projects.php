<?php

/**
 * Form handling shared by the project, assembly and assembly part pages.
 */

/**
 * Submitted project data as the columns to write.
 */
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
    echo '<form method="post">' . "\n";
    formMessage($formMessage);

    textField('project_name', 'Project Name', $values['project_name'] ?? '', 'text', ' required');
    textField('project_reference', 'Reference', $values['project_reference'] ?? '');

    selectField(
        'project_status_id',
        'Status',
        array_column(fetchProjectStatuses(), 'project_status_name', 'project_status_id'),
        $values['project_status_id'] ?? null
    );

    textareaField('project_description', 'Description', $values['project_description'] ?? '');
    textareaField('project_notes', 'Notes', $values['project_notes'] ?? '');

    submitButton($submitName);
    echo '</form>' . "\n";
}

/**
 * Submitted assembly data as the columns to write.
 */
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
    echo '<form method="post">' . "\n";
    formMessage($formMessage);

    textField('assembly_name', 'Assembly Name', $values['assembly_name'] ?? '', 'text', ' required');
    textField('assembly_sort_order', 'Sort Order', (int)($values['assembly_sort_order'] ?? 0), 'number');
    textareaField('assembly_description', 'Description', $values['assembly_description'] ?? '');
    textareaField('assembly_notes', 'Notes', $values['assembly_notes'] ?? '');

    submitButton($submitName);
    echo '</form>' . "\n";
}

/**
 * Submitted assembly part data as the columns to write.
 */
function assemblyItemColumns(array $post): array
{
    return [
        'quantity_required'   => (float)$post['quantity_required'],
        'quantity_allocated'  => (float)$post['quantity_allocated'],
        'quantity_installed'  => (float)$post['quantity_installed'],
        'assembly_item_notes' => textOrNull($post, 'assembly_item_notes'),
    ];
}

function validateAssemblyItem(array $columns): ?string
{
    if ($columns['quantity_required'] <= 0) {
        return 'Quantity required must be greater than zero.';
    }

    if ($columns['quantity_installed'] > $columns['quantity_allocated']) {
        return 'Installed quantity cannot exceed allocated quantity.';
    }

    return null;
}

/**
 * The quantity and notes fields shared by the add and edit part forms.
 */
function renderAssemblyItemFields(array $values): void
{
    $quantities = [
        'quantity_required'  => 'Quantity Required',
        'quantity_allocated' => 'Quantity Allocated',
        'quantity_installed' => 'Quantity Installed',
    ];

    foreach ($quantities as $name => $label) {
        $required = ($name === 'quantity_required');

        textField(
            $name,
            $label,
            $values[$name] ?? ($required ? 1 : 0),
            'number',
            ' step="1" min="0"' . ($required ? ' required' : '')
        );
    }

    textareaField('assembly_item_notes', 'Notes', $values['assembly_item_notes'] ?? '');
}

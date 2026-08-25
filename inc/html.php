<?php

/**
 * Heading row with an optional set of nav links.
 *
 * $title accepts HTML so callers can add count badges; escape any user data.
 * $links is a map of label => href, $extraHtml is appended inside the nav.
 */
function headerRow(string $class, string $title, array $links = [], string $extraHtml = ''): void
{
    echo '<div class="' . $class . '">' . "\n";
    echo '    <h2>' . $title . '</h2>' . "\n";

    if ($links || $extraHtml) {
        echo '    <nav class="onpage-nav">' . "\n";

        foreach ($links as $label => $href) {
            echo '        <a href="' . $href . '">' . $label . '</a>' . "\n";
        }

        echo $extraHtml;
        echo '    </nav>' . "\n";
    }

    echo '</div>' . "\n";
}

/** Heading at the top of a page. */
function pageHeader(string $title, array $links = [], string $extraHtml = ''): void
{
    headerRow('flex-nav', $title, $links, $extraHtml);
}

/** Heading that breaks a page into sections. */
function sectionHeader(string $title, array $links = [], string $extraHtml = ''): void
{
    headerRow('flex-nav extra-padding', $title, $links, $extraHtml);
}

/**
 * Count badge shown alongside a listing heading.
 */
function countBadge(int $count, string $suffix = ''): string
{
    return ' <span><span>' . $count . '</span>' . $suffix . '</span>';
}

/**
 * Render a form status message created by successMessage()/errorMessage().
 */
function formMessage($formMessage): void
{
    if ($formMessage) {
        echo '<p class="form-message form-' . $formMessage['status'] . '">' . $formMessage['message'] . '</p>';
    }
}

/**
 * Client side filter box for a table rendered with renderTable($searchable = true).
 */
function searchBox(string $placeholder): void
{
    echo '<div class="search-box">' . "\n";
    echo '    <input type="search" id="tableSearchInput" onkeyup="searchTable()" placeholder="' . $placeholder . '">' . "\n";
    echo '</div>' . "\n";
}

/**
 * Table with a header row, wrapped in its scrolling container.
 *
 * $rows receives each item and returns the cells for one row as an array of
 * HTML strings. Pass $searchable to hook the table up to searchBox().
 */
function renderTable(array $headings, array $items, callable $rows, bool $searchable = false): void
{
    echo '<div class="table-container">' . "\n";
    echo '    <table' . ($searchable ? ' id="searchableTable"' : '') . '>' . "\n";
    echo '        <tr><th>' . implode('</th><th>', $headings) . '</th></tr>' . "\n";

    foreach ($items as $item) {
        echo '        <tr><td>' . implode('</td><td>', $rows($item)) . '</td></tr>' . "\n";
    }

    echo '    </table>' . "\n";
    echo '</div>' . "\n";
}

/**
 * Heading plus a plain "this cannot be undone" delete form.
 */
function deleteSection(string $heading, string $submitName, string $buttonLabel = 'Delete'): void
{
    sectionHeader('Delete ' . $heading);

    echo '<form method="post">' . "\n";
    echo '    <p>This action cannot be undone.</p>' . "\n";
    echo '    <input type="submit" name="' . $submitName . '" class="delete" value="' . $buttonLabel . '">' . "\n";
    echo '</form>' . "\n";
}

/**
 * Delete form guarded by a browser confirmation dialog.
 */
function confirmDeleteForm(string $submitName, string $buttonLabel, string $confirmText): void
{
    echo '<hr>' . "\n";
    echo '<form method="post" onsubmit="return confirm(' . escapeHtml("'" . $confirmText . "'") . ');">' . "\n";
    echo '    <p><input type="submit" name="' . $submitName . '" value="' . $buttonLabel . '" class="delete"></p>' . "\n";
    echo '</form>' . "\n";
}

/**
 * <option> markup for a map of value => label.
 */
function selectOptions(array $options, $selected): string
{
    $html = '';

    foreach ($options as $value => $label) {
        $isSelected = ($selected !== null && $selected !== '' && $selected == $value) ? ' selected' : '';
        $html .= '<option value="' . $value . '"' . $isSelected . '>' . escapeHtml($label) . '</option>';
    }

    return $html;
}

/**
 * Deployment listing shared by the item and deployment pages.
 */
function renderDeployments(array $deployments, array $item): void
{
    if (!$deployments) {
        echo '<p>No current deployments</p>' . "\n";
        return;
    }

    renderTable(
        ['Description', 'Quantity', 'Utilisation', 'Date', 'Edit'],
        $deployments,
        function ($deployment) use ($item) {
            return [
                escapeHtml($deployment['dep_description']),
                escapeHtml($deployment['dep_quantity']) . escapeHtml($item['unit_symbol']),
                calculatePercentage($item['item_quantity'], $deployment['dep_quantity']) . '&percnt;',
                escapeHtml($deployment['dep_timestamp']),
                '<a href="index.php?page=edit-deployment&deployment_id=' . $deployment['dep_id']
                    . '&item_id=' . $deployment['dep_item_id'] . '">Edit</a>',
            ];
        }
    );
}

/**
 * Notes/description block with links made clickable.
 */
function notesBox($text): void
{
    echo '<div class="notes-box">' . nl2p(text2link(escapeHtml($text))) . '</div>' . "\n";
}

/**
 * One tile inside an .item-property-container. $body is raw HTML.
 */
function itemProperty(string $heading, string $body, string $class = ''): void
{
    echo '<div class="item-property' . ($class ? ' ' . $class : '') . '">' . "\n";
    echo '    <h3>' . $heading . '</h3>' . "\n";
    echo '    ' . $body . "\n";
    echo '</div>' . "\n";
}

/**
 * Labelled form control. $control is raw HTML.
 */
function formRow(string $name, string $label, string $control): void
{
    echo '    <p>' . "\n";
    echo '        <label for="' . $name . '">' . $label . '</label>' . "\n";
    echo '        ' . $control . "\n";
    echo '    </p>' . "\n";
}

/**
 * <input> row. $attributes is raw HTML appended to the tag, eg ' required'.
 */
function textField(string $name, string $label, $value = '', string $type = 'text', string $attributes = ''): void
{
    formRow($name, $label, '<input type="' . $type . '" name="' . $name . '" id="' . $name . '"'
        . ' value="' . escapeHtml($value) . '"' . $attributes . ' />');
}

function textareaField(string $name, string $label, $value = ''): void
{
    formRow($name, $label, '<textarea name="' . $name . '" id="' . $name . '">' . escapeHtml($value) . '</textarea>');
}

/**
 * <select> row. $firstOption is raw HTML for an optional leading placeholder.
 */
function selectField(
    string $name,
    string $label,
    array $options,
    $selected,
    string $firstOption = '',
    string $attributes = ''
): void {
    formRow($name, $label, '<select name="' . $name . '" id="' . $name . '"' . $attributes . '>'
        . $firstOption . selectOptions($options, $selected) . '</select>');
}

function submitButton(string $name, string $label = 'Save'): void
{
    echo '    <p><input type="submit" name="' . $name . '" value="' . $label . '"></p>' . "\n";
}

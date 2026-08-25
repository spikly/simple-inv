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
 * One control in a filter bar. The label is kept for screen readers; sighted
 * users get the same wording from the control's own placeholder.
 */
function filterField(string $id, string $label, string $control, string $class = ''): void
{
    echo '    <p class="filter-field' . ($class ? ' ' . $class : '') . '">' . "\n";
    echo '        <label for="' . $id . '" class="visually-hidden">' . $label . '</label>' . "\n";
    echo '        ' . $control . "\n";
    echo '    </p>' . "\n";
}

/** Submit and clear buttons that close a filter bar. */
function filterActions(string $page, bool $hasFilters, string $label = 'Filter'): void
{
    echo '    <p class="filter-actions">' . "\n";
    echo '        <input type="submit" value="' . $label . '">' . "\n";

    if ($hasFilters) {
        echo '        <a href="index.php?page=' . $page . '" class="filter-clear">Clear</a>' . "\n";
    }

    echo '    </p>' . "\n";
}

/**
 * Search box on its own, for listings that have nothing else to filter by.
 */
function renderSearchBar(string $page, string $placeholder): void
{
    $search = (string)queryParam('q');

    echo '<form method="get" class="filter-bar">' . "\n";
    echo '    <input type="hidden" name="page" value="' . $page . '">' . "\n";

    filterField(
        'q',
        'Search',
        '<input type="search" name="q" id="q" value="' . escapeHtml($search)
            . '" placeholder="' . escapeHtml($placeholder) . '">',
        'filter-search'
    );

    filterActions($page, $search !== '', 'Search');

    echo '</form>' . "\n";
}

/**
 * Table with a header row, wrapped in its scrolling container.
 *
 * $rows receives each item and returns the cells for one row as an array of
 * HTML strings. Headings are clickable to sort, see assets/js/app.js.
 *
 * $columnClasses maps a column's position to a class put on both its heading
 * and its cells, which is how a column gets sized differently from the rest.
 */
function renderTable(array $headings, array $items, callable $rows, array $columnClasses = []): void
{
    $cells = function (array $values, string $tag) use ($columnClasses) {
        $html = '';

        foreach (array_values($values) as $index => $value) {
            $class = isset($columnClasses[$index]) ? ' class="' . $columnClasses[$index] . '"' : '';
            $html .= '<' . $tag . $class . '>' . $value . '</' . $tag . '>';
        }

        return $html;
    };

    echo '<div class="table-container">' . "\n";
    echo '    <table class="sortable">' . "\n";
    echo '        <tr>' . $cells($headings, 'th') . '</tr>' . "\n";

    foreach ($items as $item) {
        echo '        <tr>' . $cells($rows($item), 'td') . '</tr>' . "\n";
    }

    echo '    </table>' . "\n";
    echo '</div>' . "\n";
}

/**
 * Heading plus a plain "this cannot be undone" delete form.
 */
function deleteSection(
    string $heading,
    string $submitName,
    string $buttonLabel = 'Delete',
    string $confirmText = ''
): void {
    $confirmText = $confirmText ?: 'Delete this ' . strtolower($heading) . '? This cannot be undone.';

    sectionHeader('Delete ' . $heading);

    echo '<form method="post" onsubmit="return confirm(' . jsString($confirmText) . ');">' . "\n";
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
    echo '<form method="post" onsubmit="return confirm(' . jsString($confirmText) . ');">' . "\n";
    echo '    <p><input type="submit" name="' . $submitName . '" value="' . $buttonLabel . '" class="delete"></p>' . "\n";
    echo '</form>' . "\n";
}

/**
 * <option> markup for a map of value => label.
 */
function selectOptions(array $options, $selected): string
{
    $chosen = array_map('strval', is_array($selected) ? $selected : [$selected]);
    $html = '';

    foreach ($options as $value => $label) {
        // A nested array is a group of options rather than one option.
        if (is_array($label)) {
            $html .= '<optgroup label="' . escapeHtml($value) . '">'
                . selectOptions($label, $selected) . '</optgroup>';
            continue;
        }

        $isSelected = in_array((string)$value, $chosen, true) ? ' selected' : '';
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

/**
 * Quantities are stored as DECIMAL(12,3); show them without trailing zeros.
 */
function formatQuantity($value): string
{
    $number = (float)$value;

    return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.') ?: '0';
}

/**
 * Item photo thumbnail, or nothing when the item has no photo.
 */
function itemThumb(?string $image, string $alt = '', string $class = 'item-thumb'): string
{
    $url = itemImageUrl($image);

    return $url
        ? '<img src="' . $url . '" alt="' . escapeHtml($alt) . '" class="' . $class . '" loading="lazy">'
        : '';
}

/**
 * Free stock shown with a colour that reflects how tight it is.
 */
function stockCell(array $item): string
{
    $free = (float)$item['item_free_count'];
    $minimum = (float)($item['item_min_quantity'] ?? 0);

    if ($free < 0) {
        $class = 'stock-over';
    } elseif ($minimum > 0 && $free <= $minimum) {
        $class = 'stock-low';
    } else {
        $class = 'stock-ok';
    }

    return '<span class="stock ' . $class . '">' . formatQuantity($free)
        . escapeHtml($item['unit_symbol'] ?? '') . '</span>';
}

/**
 * Filter bar for the items listing: one dropdown per taxonomy plus a text
 * search, all combined with AND. Current selections come from the query string.
 */
function renderItemFilters(array $applied): void
{
    $search = (string)queryParam('q');

    echo '<form method="get" class="filter-bar">' . "\n";
    echo '    <input type="hidden" name="page" value="items">' . "\n";

    filterField(
        'q',
        'Search',
        '<input type="search" name="q" id="q" value="' . escapeHtml($search)
            . '" placeholder="Search name, part number or notes">',
        'filter-search'
    );

    foreach (taxonomies() as $key => $tax) {
        $id = 'filter_' . $tax['param'];

        // The "Any ..." option doubles as the dropdown's resting label, so the
        // bar reads clearly without a row of headings above it.
        filterField(
            $id,
            $tax['label'],
            '<select name="' . $tax['param'] . '" id="' . $id . '">'
                . '<option value="">Any ' . $tax['label'] . '</option>'
                . selectOptions(taxonomyOptions($key), queryParam($tax['param']))
                . '</select>'
        );
    }

    filterActions('items', $applied || $search !== '');

    echo '</form>' . "\n";
}

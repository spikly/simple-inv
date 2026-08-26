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
 * The messages waiting against each named form field, so the controls can be
 * marked up as wrong as they are drawn.
 *
 * formMessage() fills this in from the errors it renders, which is why it is
 * called at the top of a form rather than below the fields it applies to.
 * Passing an array replaces what is held; passing nothing reads it back.
 */
function formFieldErrors(?array $errors = null): array
{
    static $fields = [];

    if ($errors !== null) {
        $fields = $errors;
    }

    return $fields;
}

/** The message against one field, or '' when nothing is wrong with it. */
function fieldError(string $name): string
{
    return formFieldErrors()[$name] ?? '';
}

/**
 * Attributes marking a control as the one at fault, so the highlight formRow()
 * draws is announced rather than only seen. The id is the one formRow() gives
 * the message it puts below the control.
 */
function invalidAttributes(string $name): string
{
    return fieldError($name)
        ? ' aria-invalid="true" aria-describedby="' . $name . '_error"'
        : '';
}

/**
 * Render a form status message created by successMessage()/errorMessage().
 *
 * Every error is listed, not just the first, and the ones naming a field are
 * handed to formFieldErrors() so the field itself is marked too.
 */
function formMessage($formMessage): void
{
    $messages = is_array($formMessage) ? ($formMessage['messages'] ?? []) : [];
    $status = (is_array($formMessage) ? ($formMessage['status'] ?? '') : '') ?: 'error';

    // Only an error belongs to a field; a success message is about the page.
    formFieldErrors($status === 'error'
        ? array_filter($messages, 'is_string', ARRAY_FILTER_USE_KEY)
        : []);

    if (!$messages) {
        return;
    }

    if (count($messages) === 1) {
        echo '<p class="form-message form-' . $status . '">' . reset($messages) . '</p>' . "\n";
        return;
    }

    echo '<div class="form-message form-' . $status . '">' . "\n";
    echo '    <p>There are ' . count($messages) . ' things to put right:</p>' . "\n";
    echo '    <ul>' . "\n";

    foreach ($messages as $message) {
        echo '        <li>' . $message . '</li>' . "\n";
    }

    echo '    </ul>' . "\n";
    echo '</div>' . "\n";
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
 * A name read through a LEFT JOIN. Rows can outlive the manufacturer or
 * location they point at, so a missing one is said rather than left blank.
 */
function nameOrDeleted($name): string
{
    return isset($name) ? escapeHtml($name) : '<i>Deleted</i>';
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
 *
 * A field the last submission was rejected over is marked, and the reason is
 * repeated below the control so it is answered where it is fixed rather than
 * only in the summary at the top of the form.
 *
 * The row is a <div> rather than a <p> because some controls are built from
 * block elements, and a browser closes a paragraph as soon as it meets one:
 * the row would end where its control began, taking the mark with it.
 *
 * $hasControl says whether there is something focusable for the label to point
 * at. A row that only states a value the page has already settled has not, and
 * a label naming an id that does not exist is worse than one naming nothing.
 */
function formRow(string $name, string $label, string $control, bool $hasControl = true): void
{
    $error = fieldError($name);

    echo '    <div class="form-row' . ($error ? ' field-invalid' : '') . '">' . "\n";
    echo '        <label' . ($hasControl ? ' for="' . $name . '"' : '') . '>' . $label . '</label>' . "\n";
    echo '        ' . $control . "\n";

    if ($error) {
        echo '        <span class="field-error" id="' . $name . '_error">' . $error . '</span>' . "\n";
    }

    echo '    </div>' . "\n";
}

/**
 * <input> row. $attributes is raw HTML appended to the tag, eg ' required'.
 */
function textField(string $name, string $label, $value = '', string $type = 'text', string $attributes = ''): void
{
    formRow($name, $label, '<input type="' . $type . '" name="' . $name . '" id="' . $name . '"'
        . ' value="' . escapeHtml($value) . '"' . $attributes . invalidAttributes($name) . ' />');
}

function textareaField(string $name, string $label, $value = ''): void
{
    formRow($name, $label, '<textarea name="' . $name . '" id="' . $name . '"'
        . invalidAttributes($name) . '>' . escapeHtml($value) . '</textarea>');
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
    formRow($name, $label, '<select name="' . $name . '" id="' . $name . '"' . $attributes
        . invalidAttributes($name) . '>'
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
function renderItemFilters(array $applied, ?string $kind = null, string $page = 'items'): void
{
    $search = (string)queryParam('q');

    echo '<form method="get" class="filter-bar">' . "\n";
    echo '    <input type="hidden" name="page" value="' . $page . '">' . "\n";

    filterField(
        'q',
        'Search',
        '<input type="search" name="q" id="q" value="' . escapeHtml($search)
            . '" placeholder="Search name, part number or notes">',
        'filter-search'
    );

    // Only the mixed listing has both kinds in it to choose between; the Parts
    // and Tools pages are already narrowed to one.
    if ($kind === null) {
        filterField(
            'filter_kind',
            'Type',
            '<select name="kind" id="filter_kind">'
                . '<option value="">Any Type</option>'
                . selectOptions(ITEM_TYPES, queryParam('kind'))
                . '</select>'
        );
    }

    foreach (taxonomies() as $key => $tax) {
        $id = 'filter_' . $tax['param'];

        // On a listing narrowed to one kind, the categories filing the other
        // kind would only ever filter it down to nothing.
        $options = ($key === 'category' && $kind !== null)
            ? categoryOptions($kind)
            : taxonomyOptions($key);

        // The "Any ..." option doubles as the dropdown's resting label, so the
        // bar reads clearly without a row of headings above it.
        filterField(
            $id,
            $tax['label'],
            '<select name="' . $tax['param'] . '" id="' . $id . '">'
                . '<option value="">Any ' . $tax['label'] . '</option>'
                . selectOptions($options, queryParam($tax['param']))
                . '</select>'
        );
    }

    filterActions($page, $applied || $search !== '' || (string)queryParam('kind') !== '');

    echo '</form>' . "\n";
}

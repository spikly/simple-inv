<?php

/**
 * The shared markup kit.
 *
 * Nothing here writes HTML itself: each function works out what a template
 * needs and hands it over. The markup is in templates/, see inc/template.php.
 */

/**
 * Heading row with an optional set of nav links.
 *
 * $title accepts HTML so callers can add count badges; escape any user data.
 * $links is a map of label => href, $extraHtml is appended inside the nav.
 */
function headerRow(string $class, string $title, array $links = [], string $extraHtml = ''): void
{
    template('header-row', compact('class', 'title', 'links', 'extraHtml'));
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
 * Attributes marking a control as the one at fault, so the highlight the form
 * row draws is announced rather than only seen. The id is the one the row
 * gives the message it puts below the control.
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

    template('form/message', compact('messages', 'status'));
}

/**
 * One control in a filter bar.
 */
function filterField(string $id, string $label, string $control, string $class = ''): void
{
    template('filter/field', compact('id', 'label', 'control', 'class'));
}

/** Submit and clear buttons that close a filter bar. */
function filterActions(string $page, bool $hasFilters, string $label = 'Filter'): void
{
    template('filter/actions', compact('page', 'hasFilters', 'label'));
}

/**
 * Search box on its own, for listings that have nothing else to filter by.
 */
function renderSearchBar(string $page, string $placeholder): void
{
    template('filter/search-bar', [
        'page'        => $page,
        'placeholder' => $placeholder,
        'search'      => (string)queryParam('q'),
    ]);
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
    template('table', compact('headings', 'items', 'rows', 'columnClasses'));
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
    sectionHeader('Delete ' . $heading);

    template('form/delete-section', [
        'submitName'  => $submitName,
        'buttonLabel' => $buttonLabel,
        'confirmText' => $confirmText ?: 'Delete this ' . strtolower($heading) . '? This cannot be undone.',
    ]);
}

/**
 * Delete form guarded by a browser confirmation dialog.
 */
function confirmDeleteForm(string $submitName, string $buttonLabel, string $confirmText): void
{
    template('form/confirm-delete', compact('submitName', 'buttonLabel', 'confirmText'));
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
    template('notes-box', ['text' => nl2p(text2link(escapeHtml($text)))]);
}

/**
 * One tile inside an .item-property-container. $body is raw HTML.
 */
function itemProperty(string $heading, string $body, string $class = ''): void
{
    template('item-property', compact('heading', 'body', 'class'));
}

/**
 * Labelled form control. $control is raw HTML.
 *
 * $hasControl says whether there is something focusable for the label to point
 * at; see templates/form/row.phtml for why that matters.
 */
function formRow(string $name, string $label, string $control, bool $hasControl = true): void
{
    template('form/row', [
        'name'       => $name,
        'label'      => $label,
        'control'    => $control,
        'hasControl' => $hasControl,
        'error'      => fieldError($name),
    ]);
}

/**
 * <input> row. $attributes is raw HTML appended to the tag, eg ' required'.
 */
function textField(string $name, string $label, $value = '', string $type = 'text', string $attributes = ''): void
{
    formRow($name, $label, templateHtml('form/input', compact('name', 'type', 'value', 'attributes')));
}

function textareaField(string $name, string $label, $value = ''): void
{
    formRow($name, $label, templateHtml('form/textarea', compact('name', 'value')));
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
    formRow($name, $label, templateHtml(
        'form/select',
        compact('name', 'options', 'selected', 'firstOption', 'attributes')
    ));
}

function submitButton(string $name, string $label = 'Save'): void
{
    template('form/submit', compact('name', 'label'));
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
 * Filter bar for the items listing.
 */
function renderItemFilters(array $applied, ?string $kind = null, string $page = 'items'): void
{
    $search = (string)queryParam('q');

    template('filter/item-filters', [
        'page'       => $page,
        'search'     => $search,
        'kind'       => $kind,
        'hasFilters' => (bool)$applied || $search !== '' || (string)queryParam('kind') !== '',
    ]);
}

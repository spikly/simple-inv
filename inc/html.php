<?php

/**
 * The shared markup kit. Nothing here writes HTML itself: each function works
 * out what a template needs and hands it over.
 */

/** $title and $extraHtml are raw HTML; escape any user data first. */
function headerRow(string $class, string $title, array $links = [], string $extraHtml = ''): void
{
    template('header-row', compact('class', 'title', 'links', 'extraHtml'));
}

function pageHeader(string $title, array $links = [], string $extraHtml = ''): void
{
    headerRow('flex-nav', $title, $links, $extraHtml);
}

function sectionHeader(string $title, array $links = [], string $extraHtml = ''): void
{
    headerRow('flex-nav extra-padding', $title, $links, $extraHtml);
}

function countBadge(int $count, string $suffix = ''): string
{
    return ' <span><span>' . $count . '</span>' . $suffix . '</span>';
}

/**
 * The messages waiting against each named field. formMessage() fills this in
 * from the errors it renders, which is why it is called at the top of a form.
 * An array replaces what is held; nothing reads it back.
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

/** Marks a control as at fault, so the highlight is announced, not only seen. */
function invalidAttributes(string $name): string
{
    return fieldError($name)
        ? ' aria-invalid="true" aria-describedby="' . $name . '_error"'
        : '';
}

/**
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

function filterField(string $id, string $label, string $control, string $class = ''): void
{
    template('filter/field', compact('id', 'label', 'control', 'class'));
}

function filterActions(string $page, bool $hasFilters, string $label = 'Filter'): void
{
    template('filter/actions', compact('page', 'hasFilters', 'label'));
}

/** Search box on its own, for listings that have nothing else to filter by. */
function renderSearchBar(string $page, string $placeholder): void
{
    template('filter/search-bar', [
        'page'        => $page,
        'placeholder' => $placeholder,
        'search'      => (string)queryParam('q'),
    ]);
}

/**
 * $rows takes one item and returns its cells as HTML strings. $columnClasses
 * maps a column position to a class put on both its heading and its cells.
 */
function renderTable(array $headings, array $items, callable $rows, array $columnClasses = []): void
{
    template('table', compact('headings', 'items', 'rows', 'columnClasses'));
}

/** Heading plus a plain "this cannot be undone" delete form. */
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

/** Delete form guarded by a browser confirmation dialog. */
function confirmDeleteForm(string $submitName, string $buttonLabel, string $confirmText): void
{
    template('form/confirm-delete', compact('submitName', 'buttonLabel', 'confirmText'));
}

/** <option> markup for a map of value => label. */
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
        $html .= '<option value="' . escapeHtml((string)$value) . '"' . $isSelected . '>'
            . escapeHtml($label) . '</option>';
    }

    return $html;
}

/** Rows outlive what they point at, so a missing name is said, not left blank. */
function nameOrDeleted($name): string
{
    return isset($name) ? escapeHtml($name) : '<i>Deleted</i>';
}

/** Reads loc_name and loc_parent_name, so a drawer names its chest. */
function locationCell(array $row): string
{
    return nameOrDeleted($row['loc_name'] ?? null)
        . (isset($row['loc_parent_name'])
            ? '<small class="row-note">in ' . escapeHtml($row['loc_parent_name']) . '</small>'
            : '');
}

/** Notes/description block with links made clickable. */
function notesBox($text): void
{
    template('notes-box', ['text' => nl2p(text2link(escapeHtml($text)))]);
}

/** One tile inside an .item-property-container. $body is raw HTML. */
function itemProperty(string $heading, string $body, string $class = ''): void
{
    template('item-property', compact('heading', 'body', 'class'));
}

/**
 * $control is raw HTML. $hasControl says whether there is something focusable
 * for the label to point at; see templates/form/row.phtml.
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

/** <input> row. $attributes is raw HTML appended to the tag, eg ' required'. */
function textField(string $name, string $label, $value = '', string $type = 'text', string $attributes = ''): void
{
    formRow($name, $label, templateHtml('form/input', compact('name', 'type', 'value', 'attributes')));
}

function textareaField(string $name, string $label, $value = ''): void
{
    formRow($name, $label, templateHtml('form/textarea', compact('name', 'value')));
}

/** <select> row. $firstOption is raw HTML for an optional leading placeholder. */
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

/** Quantities are stored as DECIMAL(12,3); show them without trailing zeros. */
function formatQuantity($value): string
{
    $number = (float)$value;

    return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.') ?: '0';
}

function formatFileSize($bytes): string
{
    $bytes = max(0, (int)$bytes);

    if ($bytes < 1024) {
        return $bytes . 'B';
    }

    $units = ['KB', 'MB', 'GB'];
    $size = $bytes / 1024;
    $unit = 0;

    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    // One decimal below 10, so 1.4MB keeps its detail and 240KB is not padded.
    return round($size, $size < 10 ? 1 : 0) . $units[$unit];
}

/** The documents kept against an item, with the form for adding more. */
function renderItemFiles(array $files, $itemId): void
{
    template('item/files', ['files' => $files, 'itemId' => $itemId]);
}

/** Item photo thumbnail, or nothing when the item has no photo. */
function itemThumb(?string $image, string $alt = '', string $class = 'item-thumb'): string
{
    $url = itemImageUrl($image);

    return $url
        ? '<img src="' . $url . '" alt="' . escapeHtml($alt) . '" class="' . $class . '" loading="lazy">'
        : '';
}

/** Free stock shown with a colour that reflects how tight it is. */
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

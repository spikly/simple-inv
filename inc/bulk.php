<?php

/** Bulk actions on the ticked items, or on everything the filters match. */

/** Action => label. Delete stays last so it is not picked by accident. */
const BULK_ACTIONS = [
    'location'        => 'Move to location',
    'status'          => 'Set status',
    'brand'           => 'Set manufacturer',
    'supplier'        => 'Set supplier',
    'add_category'    => 'Add to category',
    'remove_category' => 'Remove from category',
    'labels'          => 'Print labels',
    'export'          => 'Export CSV',
    'delete'          => 'Delete',
];

/** Action => [taxonomy key, column]. */
const BULK_COLUMNS = [
    'location' => ['location', 'item_loc_id'],
    'status'   => ['status', 'item_status'],
    'brand'    => ['brand', 'item_brand_id'],
    'supplier' => ['supplier', 'item_sup_id'],
];

/** "Set supplier" value that clears it. */
const BULK_NO_SUPPLIER = 'none';

/** Action => [placeholder, options] for the actions that need a value. */
function bulkActionFields(?string $type): array
{
    $categories = ($type === null)
        ? ['Part categories' => categoryOptions('part'), 'Tool categories' => categoryOptions('tool')]
        : categoryOptions($type);

    return [
        'location'        => ['Choose a location', locationOptions()],
        'status'          => ['Choose a status', taxonomyOptions('status')],
        'brand'           => ['Choose a manufacturer', taxonomyOptions('brand')],
        'supplier'        => ['Choose a supplier', [BULK_NO_SUPPLIER => 'No supplier'] + taxonomyOptions('supplier')],
        'add_category'    => ['Choose a category', $categories],
        'remove_category' => ['Choose a category', $categories],
    ];
}

/**
 * Returns the message to show. Labels and export redirect instead, using
 * $filterQuery (from itemFilterSummary()) when acting on everything matching.
 */
function runBulkAction(string $action, array $itemIds, array $post, string $noun, ?string $filterQuery): array
{
    $errors = [];

    if (!isset(BULK_ACTIONS[$action])) {
        $errors['bulk_action'] = 'Choose what to do with them.';
    }

    if (!$itemIds) {
        $errors['item_ids'] = 'Tick the ' . $noun . ' you want to change.';
    }

    if ($errors) {
        return errorMessage($errors);
    }

    if ($action === 'labels' || $action === 'export') {
        $scope = ($filterQuery !== null)
            ? html_entity_decode($filterQuery)
            : '&ids=' . implode(',', $itemIds);

        redirect('index.php?page=' . ($action === 'labels' ? 'labels&type=item' : 'export-items') . $scope);
    }

    if ($action === 'delete') {
        $deleted = deleteItems($itemIds);

        return successMessage('Deleted ' . countOf($deleted, 'item') . '.');
    }

    $field = 'bulk_' . $action;
    $value = trim((string)($post[$field] ?? ''));

    if (isset(BULK_COLUMNS[$action])) {
        return bulkSetColumn($action, $itemIds, $value, $field);
    }

    return bulkCategory($action === 'add_category', $itemIds, (int)$value, $field);
}

function countOf(int $count, string $noun): string
{
    return $count . ' ' . $noun . ($count === 1 ? '' : 's');
}

function bulkSetColumn(string $action, array $itemIds, string $value, string $field): array
{
    [$key, $column] = BULK_COLUMNS[$action];
    $label = strtolower(taxonomy($key)['label']);

    if ($action === 'supplier' && $value === BULK_NO_SUPPLIER) {
        $id = null;
        $name = 'no supplier';
    } else {
        $id = (int)$value;
        $name = ($id > 0) ? taxonomyName($key, $id) : null;

        if ($name === null) {
            return errorMessage([$field => 'Choose the ' . $label . ' to set.']);
        }

        $name = escapeHtml($name);
    }

    $changed = updateItemsColumn($itemIds, $column, $id);
    $unchanged = count($itemIds) - $changed;

    if ($changed === 0) {
        return successMessage('Nothing to change: ' . ($unchanged === 1 ? 'it' : 'they')
            . ' already had ' . $name . '.');
    }

    $message = ($action === 'location')
        ? 'Moved ' . countOf($changed, 'item') . ' to ' . $name . '.'
        : 'Set ' . countOf($changed, 'item') . ' to ' . $name . '.';

    if ($unchanged > 0) {
        $message .= ' ' . $unchanged . ' ' . ($unchanged === 1 ? 'was' : 'were') . ' already set.';
    }

    return successMessage($message);
}

/**
 * Never changes an item's kind: a category is only added to items of its kind,
 * and only removed from items that keep another. Others are skipped, not refused.
 */
function bulkCategory(bool $add, array $itemIds, int $categoryId, string $field): array
{
    $category = ($categoryId > 0)
        ? dbRow('SELECT cat_name, cat_type FROM inv_categories WHERE cat_id = :id', ['id' => $categoryId])
        : false;

    if (!$category) {
        return errorMessage([$field => 'Choose the category.']);
    }

    $name = escapeHtml($category['cat_name']);

    if ($add) {
        $eligible = itemIdsOfKind($itemIds, $category['cat_type']);
        $changed = addItemsToCategory($eligible, $categoryId);
        $message = 'Added ' . countOf($changed, 'item') . ' to ' . $name . '.';
        $skipped = count($itemIds) - count($eligible);
        $why = ' left alone, since ' . $name . ' files '
            . strtolower(ITEM_TYPE_PLURALS[$category['cat_type']]) . '.';
    } else {
        [$changed, $skipped] = removeItemsFromCategory($itemIds, $categoryId);
        $message = 'Removed ' . countOf($changed, 'item') . ' from ' . $name . '.';
        $why = ' left in it, since it is ' . ($skipped === 1 ? 'its' : 'their') . ' only category.';
    }

    if ($changed === 0 && $skipped === 0) {
        return successMessage('Nothing to change: ' . (count($itemIds) === 1 ? 'it was' : 'they were')
            . ($add ? ' already in ' : ' not in ') . $name . '.');
    }

    if ($skipped > 0) {
        $message .= ' ' . countOf($skipped, 'item') . $why;
    }

    return successMessage($message);
}

/* Id lists are cast to integers and inlined; a bound parameter cannot hold one. */

function itemIdList(array $itemIds): string
{
    return implode(',', array_unique(array_filter(array_map('intval', $itemIds)))) ?: '0';
}

/** Returns how many changed. */
function updateItemsColumn(array $itemIds, string $column, ?int $value): int
{
    return dbRun(
        'UPDATE inv_items SET ' . $column . ' = :value WHERE item_id IN (' . itemIdList($itemIds) . ')',
        ['value' => $value]
    )->rowCount();
}

function itemIdsOfKind(array $itemIds, string $kind): array
{
    return array_map('intval', array_column(dbAll(
        'SELECT i.item_id FROM inv_items i
         WHERE i.item_id IN (' . itemIdList($itemIds) . ') AND '
        . ($kind === 'tool' ? ITEM_IS_TOOL : ITEM_IS_PART)
    ), 'item_id'));
}

/** Returns how many were added. */
function addItemsToCategory(array $itemIds, int $categoryId): int
{
    $added = 0;

    dbTransaction(function () use ($itemIds, $categoryId, &$added) {
        foreach ($itemIds as $itemId) {
            $added += dbRun(
                'INSERT IGNORE INTO categories_items (cat_id, item_id) VALUES (:cat_id, :item_id)',
                ['cat_id' => $categoryId, 'item_id' => $itemId]
            )->rowCount();
        }
    });

    return $added;
}

/** Returns [removed, skipped as their only category]. */
function removeItemsFromCategory(array $itemIds, int $categoryId): array
{
    $rows = dbAll(
        'SELECT item_id, COUNT(*) AS categories
         FROM categories_items
         WHERE item_id IN (' . itemIdList($itemIds) . ')
           AND item_id IN (SELECT item_id FROM categories_items WHERE cat_id = :cat_id)
         GROUP BY item_id',
        ['cat_id' => $categoryId]
    );

    $removable = [];
    $only = 0;

    foreach ($rows as $row) {
        if ((int)$row['categories'] > 1) {
            $removable[] = (int)$row['item_id'];
        } else {
            $only++;
        }
    }

    if (!$removable) {
        return [0, $only];
    }

    $removed = dbRun(
        'DELETE FROM categories_items WHERE cat_id = :cat_id AND item_id IN (' . itemIdList($removable) . ')',
        ['cat_id' => $categoryId]
    )->rowCount();

    return [$removed, $only];
}

/**
 * Related rows go by foreign key. Files are removed only after the rows, so a
 * failed delete leaves nothing pointing at a missing file. Returns how many went.
 */
function deleteItems(array $itemIds): int
{
    $list = itemIdList($itemIds);

    $images = array_column(dbAll('SELECT item_image FROM inv_items WHERE item_id IN (' . $list . ')'), 'item_image');
    $files = array_column(
        dbAll('SELECT file_stored_name FROM inv_item_files WHERE file_item_id IN (' . $list . ')'),
        'file_stored_name'
    );

    $deleted = dbRun('DELETE FROM inv_items WHERE item_id IN (' . $list . ')')->rowCount();

    foreach ($images as $image) {
        deleteItemImage($image);
    }

    foreach ($files as $file) {
        deleteItemFile($file);
    }

    return $deleted;
}

<?php

[$where, $params, $appliedFilters] = itemFilters();

$items = fetchItems($where, $params);
$itemCount = count($items);

$badges = [];
$exportQuery = '';

foreach ($appliedFilters as $key) {
    $tax = taxonomy($key);

    if ($itemCount > 0) {
        $badges[] = '<span>' . $tax['label'] . ': ' . escapeHtml($items[0][taxonomyNameField($tax)]) . '</span>';
    }

    $exportQuery .= '&amp;' . $tax['param'] . '=' . escapeHtml($params[$tax['param']]);
}

$links = [];

if ($itemCount > 0) {
    $links['Export'] = 'index.php?page=export-items' . $exportQuery;
}

$links['Add New Item'] = 'index.php?page=add-item';

pageHeader('Items' . countBadge($itemCount, $badges ? ' ' . implode(' ', $badges) : ''), $links);

if ($itemCount === 0) {
    echo 'No items';
    return;
}

searchBox('Search for items...');

renderTable(
    ['Name', 'Location', 'Status', 'Deployed', 'Edit'],
    $items,
    function ($item) {
        return [
            '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                . escapeHtml($item['item_name']) . '</a>',
            isset($item['loc_name']) ? escapeHtml($item['loc_name']) : '<i>Deleted</i>',
            isset($item['status_name']) ? escapeHtml($item['status_name']) : '<i>Deleted</i>',
            escapeHtml($item['item_deployed_count'] ?? '0') . ' of '
                . escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']),
            '<a href="index.php?page=edit-item&item_id=' . $item['item_id'] . '">Edit</a>'
                . ' / <a href="index.php?page=add-item&duplicate=' . $item['item_id'] . '">Duplicate</a>',
        ];
    },
    true
);

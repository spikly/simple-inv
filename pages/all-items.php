<?php

[$where, $params, $appliedFilters] = itemFilters();

$items = fetchItems($where, $params);
$itemCount = count($items);

$badges = [];
$exportQuery = '';

foreach ($appliedFilters as $key) {
    $tax = taxonomy($key);
    $name = taxonomyName($key, $params[$tax['param']]);

    $badges[] = '<span>' . $tax['label'] . ': ' . escapeHtml($name ?? 'unknown') . '</span>';
    $exportQuery .= '&amp;' . $tax['param'] . '=' . escapeHtml($params[$tax['param']]);
}

if (trim((string)queryParam('q')) !== '') {
    $badges[] = '<span>Search: ' . escapeHtml(queryParam('q')) . '</span>';
    $exportQuery .= '&amp;q=' . urlencode((string)queryParam('q'));
}

$links = [];

if ($itemCount > 0) {
    $links['Export'] = 'index.php?page=export-items' . $exportQuery;
    $links['Labels'] = 'index.php?page=labels&amp;type=item' . $exportQuery;
}

$links['Import'] = 'index.php?page=import-items';
$links['Add New Item'] = 'index.php?page=add-item';

pageHeader('Items' . countBadge($itemCount, $badges ? ' ' . implode(' ', $badges) : ''), $links);

formMessage(takeFlash());
renderItemFilters($appliedFilters);

if ($itemCount === 0) {
    echo '<p>No items match.</p>';
    return;
}

renderTable(
    ['', 'Name', 'Location', 'Status', 'Deployed', 'Allocated', 'Free', 'Edit'],
    $items,
    function ($item) {
        return [
            itemThumb($item['item_image'], $item['item_name']),
            '<a href="index.php?page=view-item&item_id=' . $item['item_id'] . '">'
                . escapeHtml($item['item_name']) . '</a>'
                . ($item['cat_names'] ? '<small class="row-note">' . escapeHtml($item['cat_names']) . '</small>' : ''),
            isset($item['loc_name']) ? escapeHtml($item['loc_name']) : '<i>Deleted</i>',
            isset($item['status_name']) ? escapeHtml($item['status_name']) : '<i>Deleted</i>',
            formatQuantity($item['item_deployed_count']) . ' of ' . escapeHtml($item['item_quantity'])
                . escapeHtml($item['unit_symbol']),
            formatQuantity($item['item_allocated_count']),
            stockCell($item),
            '<a href="index.php?page=edit-item&item_id=' . $item['item_id'] . '">Edit</a>'
                . ' / <a href="index.php?page=add-item&duplicate=' . $item['item_id'] . '">Duplicate</a>',
        ];
    },
    [0 => 'col-thumb']
);

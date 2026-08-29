<?php

/**
 * Which labels to make: every location, one item, or whatever the items
 * listing was filtered down to.
 */

$type = (queryParam('type') === 'item') ? 'item' : 'location';
$labels = [];

if ($type === 'location') {
    foreach (taxonomyRows(taxonomy('location')) as $row) {
        $labels[] = [
            'title' => $row['loc_name'],
            'url'   => baseUrl() . 'index.php?page=items&location_id=' . $row['loc_id'],
        ];
    }
} elseif (queryParam('item_id')) {
    $item = fetchSingleItem(queryParam('item_id'));

    if ($item) {
        $labels[] = [
            'title' => $item['item_name'],
            'url'   => baseUrl() . 'index.php?page=view-item&item_id=' . $item['item_id'],
        ];
    }
} else {
    [$where, $params] = itemFilters();

    foreach (fetchItems($where, $params) as $item) {
        $labels[] = [
            'title' => $item['item_name'],
            'url'   => baseUrl() . 'index.php?page=view-item&item_id=' . $item['item_id'],
        ];
    }
}

template('page/labels', compact('type', 'labels'));

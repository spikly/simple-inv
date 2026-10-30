<?php

const LABELS_MAX = 500;

$type = (queryParam('type') === 'item') ? 'item' : 'location';
$labels = [];

if ($type === 'location') {
    foreach (taxonomyRows(taxonomy('location'), trim((string)queryParam('q'))) as $row) {
        $labels[] = [
            'title' => locationPath($row),
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
    [$where, $params, , , $rank] = itemFilters();

    foreach (fetchItems($where, $params, null, $rank) as $item) {
        $labels[] = [
            'title' => $item['item_name'],
            'url'   => baseUrl() . 'index.php?page=view-item&item_id=' . $item['item_id'],
        ];
    }
}

// Each code is drawn in PHP, so an unfiltered sheet of a big inventory could
// run into the time limit.
$total = count($labels);
$labels = array_slice($labels, 0, LABELS_MAX);

template('page/labels', compact('type', 'labels', 'total'));

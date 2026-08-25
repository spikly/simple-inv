<?php

/**
 * Printable QR labels. Stick a location label on the drawer it names and
 * scanning it opens that location's items; an item label opens the item.
 */

$type = (queryParam('type') === 'item') ? 'item' : 'location';
$labels = [];

if ($type === 'location') {
    foreach (taxonomyRows(taxonomy('location')) as $row) {
        $labels[] = [
            'title' => $row['loc_name'],
            'note'  => taxonomyUsageCount(taxonomy('location'), $row['loc_id']) . ' items',
            'url'   => baseUrl() . 'index.php?page=items&location_id=' . $row['loc_id'],
        ];
    }
} elseif (queryParam('item_id')) {
    $item = fetchSingleItem(queryParam('item_id'));

    if ($item) {
        $labels[] = [
            'title' => $item['item_name'],
            'note'  => trim(($item['item_part_no'] ?? '') . ' ' . ($item['loc_name'] ?? '')),
            'url'   => baseUrl() . 'index.php?page=view-item&item_id=' . $item['item_id'],
        ];
    }
} else {
    [$where, $params] = itemFilters();

    foreach (fetchItems($where, $params) as $item) {
        $labels[] = [
            'title' => $item['item_name'],
            'note'  => trim(($item['item_part_no'] ?? '') . ' ' . ($item['loc_name'] ?? '')),
            'url'   => baseUrl() . 'index.php?page=view-item&item_id=' . $item['item_id'],
        ];
    }
}

pageHeader('Labels' . countBadge(count($labels)), [
    'Location Labels' => 'index.php?page=labels&amp;type=location',
    'Item Labels'     => 'index.php?page=labels&amp;type=item',
    'Print'           => '#print',
]);

?>
<p class="no-print">
    Scanning a label opens this app at that <?php echo $type === 'location' ? 'location' : 'item'; ?>.
    The address encoded is <code><?php echo escapeHtml(baseUrl()); ?></code> -
    set <code>'site' =&gt; ['url' =&gt; '...']</code> in <code>config/user.config.php</code> if that is
    not how you reach this machine.
</p>

<?php if (!$labels): ?>
    <p>Nothing to make labels for.</p>
<?php else: ?>
    <div class="label-sheet">
        <?php foreach ($labels as $label): ?>
            <div class="label">
                <?php echo qrSvg($label['url'], 150); ?>
                <span class="label-title"><?php echo escapeHtml($label['title']); ?></span>
                <?php if ($label['note']): ?>
                    <span class="label-note"><?php echo escapeHtml($label['note']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

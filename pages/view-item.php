<?php

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;

pageHeader('Item Info', $item ? [
    'Deploy Item'    => 'index.php?page=add-deployment&item_id=' . $item['item_id'],
    'Edit Item'      => 'index.php?page=edit-item&item_id=' . $item['item_id'],
    'Duplicate Item' => 'index.php?page=add-item&duplicate=' . $item['item_id'],
    'Label'          => 'index.php?page=labels&type=item&item_id=' . $item['item_id'],
] : []);

formMessage(takeFlash());

if (!$item) {
    echo '<p>No item found</p>';
    return;
}

$deployments = fetchItemDeployments($itemId);
$utilisation = calculatePercentage($item['item_quantity'], $item['item_committed_count']);
$categories = fetchItemCategoryIds($itemId);

// Headings for the taxonomies shown as item properties, in display order.
$properties = [
    'brand'    => 'Brand',
    'supplier' => 'Supplier',
    'location' => 'Storage Location',
    'status'   => 'Status',
];

?>
<h3>Name</h3>
<p class="item-name">
    <?php echo escapeHtml($item['item_name']); ?>
</p>
<?php if ($item['item_image']): ?>
    <p class="item-photo">
        <a href="<?php echo itemImageUrl($item['item_image']); ?>" target="_blank">
            <?php echo itemThumb($item['item_image'], $item['item_name'], 'item-photo-large'); ?>
        </a>
    </p>
<?php endif; ?>
<div class="item-property-container">
    <?php

    if (isset($item['item_part_no'])) {
        itemProperty('Manufacturers Part No', '<p>' . escapeHtml($item['item_part_no']) . '</p>');
    }

    itemProperty('Quantity', '<p>' . escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']) . '</p>');
    itemProperty('Deployed', '<p>' . formatQuantity($item['item_deployed_count'])
        . escapeHtml($item['unit_symbol']) . '</p>');
    itemProperty('Allocated to Projects', '<p>' . formatQuantity($item['item_allocated_count'])
        . escapeHtml($item['unit_symbol']) . '</p>');
    itemProperty('Free', '<p>' . stockCell($item) . '</p>');
    itemProperty('Utilisation', $utilisation . '&percnt;', utilisationBg($utilisation));

    if ((int)$item['item_min_quantity'] > 0) {
        itemProperty('Reorder Level', '<p>' . escapeHtml($item['item_min_quantity'])
            . escapeHtml($item['unit_symbol']) . '</p>');
    }

    foreach ($properties as $key => $heading) {
        $tax = taxonomy($key);

        // Suppliers are optional.
        if ($key === 'supplier' && !isset($item['sup_id'])) {
            continue;
        }

        $link = '<a href="index.php?page=items&' . $tax['param'] . '=' . $item[$tax['id']] . '">'
            . escapeHtml($item[taxonomyNameField($tax)]) . '</a>';

        if ($key === 'supplier' && $item['sup_website']) {
            $link .= ' | <a href="' . escapeHtml($item['sup_website']) . '" target="_blank">Website</a>';
        }

        itemProperty($heading, '<p>' . $link . '</p>');
    }

    if ($categories) {
        $links = [];

        foreach ($categories as $categoryId) {
            $links[] = '<a href="index.php?page=items&category_id=' . $categoryId . '">'
                . escapeHtml(taxonomyName('category', $categoryId) ?? 'Deleted') . '</a>';
        }

        itemProperty('Categories', '<p>' . implode(', ', $links) . '</p>');
    }

    ?>
</div>
<?php

if ((float)$item['item_free_count'] < 0) {
    echo '<p class="form-message form-error">More of this item is deployed and allocated than you hold.</p>';
}

sectionHeader('Current Deployments', [
    'Deploy Item' => 'index.php?page=add-deployment&item_id=' . $item['item_id'],
]);

renderDeployments($deployments, $item);

sectionHeader('Notes');
notesBox(strlen((string)$item['item_notes']) > 0 ? $item['item_notes'] : '-');

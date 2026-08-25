<?php

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;

pageHeader('Item Info', $item ? [
    'Deploy Item'    => 'index.php?page=add-deployment&item_id=' . $item['item_id'],
    'Edit Item'      => 'index.php?page=edit-item&item_id=' . $item['item_id'],
    'Duplicate Item' => 'index.php?page=add-item&duplicate=' . $item['item_id'],
] : []);

if (!$item) {
    echo '<p>No item found</p>';
    return;
}

$deployments = fetchItemDeployments($itemId);
$deployedCount = countItemDeployments($itemId);
$utilisation = calculatePercentage($item['item_quantity'], $deployedCount);

// Headings for the taxonomies shown as item properties, in display order.
$properties = [
    'brand'    => 'Brand',
    'supplier' => 'Supplier',
    'category' => 'Category',
    'location' => 'Storage Location',
    'status'   => 'Status',
];

?>
<h3>Name</h3>
<p class="item-name">
    <?php echo escapeHtml($item['item_name']); ?>
</p>
<div class="item-property-container">
    <?php

    if (isset($item['item_part_no'])) {
        itemProperty('Manufacturers Part No', '<p>' . escapeHtml($item['item_part_no']) . '</p>');
    }

    itemProperty('Quantity', '<p>' . escapeHtml($item['item_quantity']) . escapeHtml($item['unit_symbol']) . '</p>');
    itemProperty('Deployed', '<p>' . $deployedCount . escapeHtml($item['unit_symbol']) . '</p>');
    itemProperty('Utilisation', $utilisation . '&percnt;', utilisationBg($utilisation));

    foreach ($properties as $key => $heading) {
        $tax = taxonomy($key);

        // Suppliers are optional.
        if ($key === 'supplier' && !isset($item['sup_id'])) {
            continue;
        }

        $link = '<a href="index.php?page=items&' . $tax['param'] . '=' . $item[$tax['id']] . '">'
            . escapeHtml($item[taxonomyNameField($tax)]) . '</a>';

        if ($key === 'supplier') {
            $link .= ' | <a href="' . escapeHtml($item['sup_website']) . '" target="_blank">Website</a>';
        }

        itemProperty($heading, '<p>' . $link . '</p>');
    }

    ?>
</div>
<?php

sectionHeader('Current Deployments');
renderDeployments($deployments, $item);

sectionHeader('Notes');
notesBox(strlen((string)$item['item_notes']) > 0 ? $item['item_notes'] : '-');

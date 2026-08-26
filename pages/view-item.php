<?php

$itemId = queryParam('item_id');
$item = $itemId ? fetchSingleItem($itemId) : false;
$type = $item ? itemTypeOf($item) : 'part';
$loan = ($item && $type === 'tool') ? fetchOpenToolLoan($itemId) : null;

$links = [];

if ($item) {
    if ($type === 'tool') {
        $links[$loan ? 'Sign In' : 'Sign Out'] = 'index.php?page=loan-tool&item_id=' . $item['item_id'];
    }

    $links['Edit ' . ITEM_TYPES[$type]] = 'index.php?page=edit-item&item_id=' . $item['item_id'];
    $links['Duplicate'] = 'index.php?page=add-item&duplicate=' . $item['item_id'];
    $links['Label'] = 'index.php?page=labels&type=item&item_id=' . $item['item_id'];
}

pageHeader(ITEM_TYPES[$type] . ' Info', $links);

formMessage(takeFlash());

if (!$item) {
    echo '<p>No item found</p>';
    return;
}

$assemblyUsage = fetchItemAssemblyUsage($itemId);
$utilisation = calculatePercentage($item['item_quantity'], $item['item_allocated_count']);
$categories = fetchItemCategoryIds($itemId);

// Headings for the taxonomies shown as item properties, in display order.
$properties = [
    'brand'    => 'Manufacturer',
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

    if ($type === 'tool') {
        // A tool is one object, so the only quantity worth showing is whether
        // it is here or with somebody.
        $overdue = $loan && loanIsOverdue($loan['loan_due_at']);

        itemProperty(
            'Signed Out To',
            '<p>' . ($loan ? escapeHtml($loan['loan_to']) : 'Nobody, it is here') . '</p>',
            $loan ? ($overdue ? 'red' : 'amber') : 'green'
        );

        if ($loan) {
            itemProperty('Out Since', '<p>' . escapeHtml(formatDate($loan['loan_out_at'])) . '</p>');
            itemProperty(
                'Due Back',
                '<p>' . ($loan['loan_due_at'] ? escapeHtml(formatDate($loan['loan_due_at'])) : 'No date set')
                    . ($overdue ? ' (overdue)' : '') . '</p>',
                $overdue ? 'red' : ''
            );
        }
    } else {
        itemProperty('Quantity', '<p>' . escapeHtml($item['item_quantity'])
            . escapeHtml($item['unit_symbol']) . '</p>');
        itemProperty('Reserved for Projects', '<p>' . formatQuantity($item['item_allocated_count'])
            . escapeHtml($item['unit_symbol']) . '</p>');
        itemProperty('Free', '<p>' . stockCell($item) . '</p>');
        itemProperty('Utilisation', $utilisation . '&percnt;', utilisationBg($utilisation));

        if ((int)$item['item_min_quantity'] > 0) {
            itemProperty('Reorder Level', '<p>' . escapeHtml($item['item_min_quantity'])
                . escapeHtml($item['unit_symbol']) . '</p>');
        }
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

if ($type === 'tool') {
    sectionHeader('Sign-Out History', [
        ($loan ? 'Sign In' : 'Sign Out') => 'index.php?page=loan-tool&item_id=' . $item['item_id'],
    ]);

    renderToolLoans(fetchToolLoans($itemId));

    sectionHeader('Notes');
    notesBox(strlen((string)$item['item_notes']) > 0 ? $item['item_notes'] : '-');

    return;
}

if ((float)$item['item_free_count'] < 0) {
    echo '<p class="form-message form-error">More of this part is reserved for projects than you hold.</p>';
}

sectionHeader('Reserved for Assemblies');

if ($assemblyUsage) {
    echo '<p>Stock set aside for these assemblies is held back from the free quantity above.'
        . ' Installed quantities have already left stock.</p>' . "\n";

    renderTable(
        ['Project', 'Assembly', 'Required', 'Reserved', 'Installed'],
        $assemblyUsage,
        function ($part) use ($item) {
            $unit = escapeHtml($item['unit_symbol']);
            $outstanding = max(0, (float)$part['quantity_required'] - (float)$part['quantity_installed']);
            $short = $outstanding - (float)$part['quantity_allocated'];

            return [
                '<a href="index.php?page=view-project&project_id=' . (int)$part['project_id'] . '">'
                    . escapeHtml($part['project_name']) . '</a>',
                '<a href="index.php?page=view-assembly&assembly_id=' . (int)$part['assembly_id'] . '">'
                    . escapeHtml($part['assembly_name']) . '</a>',
                formatQuantity($part['quantity_required']) . $unit,
                formatQuantity($part['quantity_allocated']) . $unit
                    . ($short > 0 ? '<small class="row-note">' . formatQuantity($short)
                        . $unit . ' short of stock</small>' : ''),
                formatQuantity($part['quantity_installed']) . $unit,
            ];
        }
    );
} else {
    echo '<p>This part is not on any assembly.</p>' . "\n";
}

sectionHeader('Notes');
notesBox(strlen((string)$item['item_notes']) > 0 ? $item['item_notes'] : '-');

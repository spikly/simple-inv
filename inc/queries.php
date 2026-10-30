<?php

/*
 * Items
 */

/** Derived tables give per item totals, so the category join cannot multiply them. */
const ITEM_JOINS = '
    FROM inv_items i
    LEFT JOIN inv_brands b ON b.brand_id = i.item_brand_id
    LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
    LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
    LEFT JOIN inv_locations lp ON lp.loc_id = l.loc_parent_id
    LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
    LEFT JOIN inv_statuses s ON s.status_id = i.item_status
    LEFT JOIN categories_items ci ON ci.item_id = i.item_id
    LEFT JOIN inv_categories c ON c.cat_id = ci.cat_id
    LEFT JOIN (
        SELECT item_id, SUM(quantity_allocated) AS total
        FROM inv_assembly_items
        GROUP BY item_id
    ) alloc ON alloc.item_id = i.item_id
    LEFT JOIN inv_tool_loans loan
        ON loan.loan_item_id = i.item_id AND loan.loan_in_at IS NULL
';

/**
 * is_tool    whether its categories file tools
 * allocated  quantity reserved for project assemblies
 * free       item_quantity less what is allocated
 * loan_*     the tool's open sign-out, null while it is here
 *
 * A tool has at most one open loan, so MAX picks it out despite the category join.
 */
const ITEM_STOCK_COLUMNS = '
    COALESCE(MAX(c.cat_type = \'tool\'), 0) AS item_is_tool,
    COALESCE(MAX(alloc.total), 0) AS item_allocated_count,
    i.item_quantity - COALESCE(MAX(alloc.total), 0) AS item_free_count,
    MAX(loan.loan_id) AS loan_id,
    MAX(loan.loan_to) AS loan_to,
    MAX(loan.loan_out_at) AS loan_out_at,
    MAX(loan.loan_due_at) AS loan_due_at,
    GROUP_CONCAT(DISTINCT c.cat_name ORDER BY c.cat_name SEPARATOR \', \') AS cat_names
';

/** An item's kind is kept only on its categories. */
const ITEM_IS_TOOL = "EXISTS (SELECT 1 FROM categories_items tci
    INNER JOIN inv_categories tc ON tc.cat_id = tci.cat_id
    WHERE tci.item_id = i.item_id AND tc.cat_type = 'tool')";

const ITEM_IS_PART = 'NOT ' . ITEM_IS_TOOL;

const ITEM_KIND_FILTER = 'EXISTS (SELECT 1 FROM categories_items kci
    INNER JOIN inv_categories kc ON kc.cat_id = kci.cat_id
    WHERE kci.item_id = i.item_id AND kc.cat_type = :item_kind)';

/**
 * Each search word is scored on its own and the scores summed, so "Drill press"
 * (whole word, at the start) outscores "Assorted drill bits" (whole word), which
 * outscores "Drilling motor" (start of a word only). exact_* and phrase compare
 * the whole search. A match only on colour or notes scores 0.
 */
const ITEM_SEARCH_WEIGHTS = [
    'exact_name' => 100,
    'exact_part' => 100,
    'phrase'     => 20,
    'whole_word' => 40,
    'name_start' => 20,
    'word_start' => 10,
    'in_name'    => 5,
];

/** Keeps the query a sensible size; further words are dropped. */
const ITEM_SEARCH_MAX_WORDS = 10;

function searchWords(string $search): array
{
    $words = [];

    foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        $words[mb_strtolower($word)] = $word;
    }

    return array_slice(array_values($words), 0, ITEM_SEARCH_MAX_WORDS);
}

function likeEscape(string $text): string
{
    return addcslashes($text, '%_\\');
}

/**
 * Returns [ORDER BY fragment, params], or ['', []] with no search. Kept apart from
 * the filter's params because countItems() does not use them.
 */
function itemSearchRank(string $search): array
{
    $words = searchWords($search);

    if (!$words) {
        return ['', []];
    }

    $w = ITEM_SEARCH_WEIGHTS;

    $terms = [
        '(i.item_name = :rank_exact_name) * ' . $w['exact_name'],
        'COALESCE(i.item_part_no = :rank_exact_part, 0) * ' . $w['exact_part'],
    ];
    $params = [
        'rank_exact_name' => $search,
        'rank_exact_part' => $search,
    ];

    if (count($words) > 1) {
        $terms[] = '(i.item_name LIKE :rank_phrase) * ' . $w['phrase'];
        $params['rank_phrase'] = '%' . likeEscape($search) . '%';
    }

    foreach ($words as $n => $word) {
        $like = likeEscape($word);

        // [:alnum:] rather than \b, which older MySQL regex engines lack.
        $regex = preg_quote($word);

        $terms[] = '(i.item_name REGEXP :rank_whole_word_' . $n . ') * ' . $w['whole_word'];
        $terms[] = '(i.item_name LIKE :rank_name_start_' . $n . ') * ' . $w['name_start'];
        $terms[] = '(i.item_name REGEXP :rank_word_start_' . $n . ') * ' . $w['word_start'];
        $terms[] = '(i.item_name LIKE :rank_in_name_' . $n . ') * ' . $w['in_name'];

        $params['rank_whole_word_' . $n] = '(^|[^[:alnum:]])' . $regex . '([^[:alnum:]]|$)';
        $params['rank_name_start_' . $n] = $like . '%';
        $params['rank_word_start_' . $n] = '(^|[^[:alnum:]])' . $regex;
        $params['rank_in_name_' . $n] = '%' . $like . '%';
    }

    return ['(' . implode(' + ', $terms) . ') DESC, CHAR_LENGTH(i.item_name) asc, ', $params];
}

/**
 * Returns [where, params, applied taxonomy keys, kind or null, search rank].
 * $pinnedKind overrides any kind in the query string.
 */
function itemFilters(?string $pinnedKind = null): array
{
    $clauses = [];
    $params = [];
    $applied = [];

    foreach (taxonomies() as $key => $tax) {
        $value = queryParam($tax['param']);

        if ($value === false || $value === '') {
            continue;
        }

        $clauses[] = $tax['itemFilter'];
        $params[$tax['param']] = $value;
        $applied[] = $key;
    }

    $requested = (string)queryParam('kind');

    // A category implies the kind; the bar drops its Type control to match.
    if (in_array('category', $applied, true)) {
        $requested = '';
    }

    $kind = $pinnedKind ?? (isset(ITEM_TYPES[$requested]) ? $requested : null);

    if ($kind !== null) {
        $clauses[] = ITEM_KIND_FILTER;
        $params['item_kind'] = $kind;
    }

    // Cast to integers, so safe to inline.
    $ids = array_unique(array_filter(array_map('intval', explode(',', (string)queryParam('ids')))));

    if ($ids) {
        $clauses[] = 'i.item_id IN (' . implode(',', $ids) . ')';
    }

    $search = trim((string)queryParam('q'));

    // One placeholder per word: named parameters cannot be reused with emulated
    // prepares off.
    foreach (searchWords($search) as $n => $word) {
        $clauses[] = "CONCAT_WS(' ', i.item_name, i.item_part_no, i.item_colour, i.item_notes)"
            . ' LIKE :search_' . $n;
        $params['search_' . $n] = '%' . likeEscape($word) . '%';
    }

    return [
        $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '',
        $params,
        $applied,
        $kind,
        itemSearchRank($search),
    ];
}

/** $rank comes from itemSearchRank(). */
function fetchItems(string $where, array $params, ?array $slice = null, array $rank = ['', []]): array
{
    [$rankOrder, $rankParams] = $rank;

    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_part_no, i.item_quantity, i.item_min_quantity, i.item_image,
                mu.unit_symbol, b.brand_name, sp.sup_name,
                l.loc_name, lp.loc_name AS loc_parent_name, s.status_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS . $where
        . ' GROUP BY i.item_id ORDER BY ' . $rankOrder . 'i.item_name asc'
        . ($slice ? paginationLimit($slice) : ''),
        $params + $rankParams
    );
}

/** DISTINCT, as the category join repeats an item in several categories. */
function countItems(string $where, array $params): int
{
    return (int)dbValue('SELECT COUNT(DISTINCT i.item_id)' . ITEM_JOINS . $where, $params, 0);
}

function fetchItemIds(string $where, array $params): array
{
    return array_map('intval', array_column(
        dbAll('SELECT DISTINCT i.item_id' . ITEM_JOINS . $where, $params),
        'item_id'
    ));
}

/** Column order is the export's heading order. */
function fetchItemsForExport(string $where, array $params): array
{
    return dbAll(
        'SELECT i.item_name AS `Name`,
                i.item_part_no AS `Part No`,
                i.item_colour AS `Colour`,
                i.item_product_url AS `Product URL`,
                b.brand_name AS `Manufacturer`,
                sp.sup_name AS `Supplier`,
                GROUP_CONCAT(DISTINCT c.cat_name ORDER BY c.cat_name SEPARATOR \'|\') AS `Categories`,
                CONCAT_WS(\'' . LOCATION_PATH_SEPARATOR . '\', lp.loc_name, l.loc_name) AS `Location`,
                s.status_name AS `Status`,
                i.item_quantity AS `Quantity`,
                i.item_min_quantity AS `Min Quantity`,
                mu.unit_symbol AS `Unit`,
                IF(MAX(c.cat_type = \'tool\'), \'Tool\', \'Part\') AS `Type`,
                COALESCE(MAX(alloc.total), 0) AS `Allocated`,
                i.item_notes AS `Notes`'
        . ITEM_JOINS . $where
        . ' GROUP BY i.item_id ORDER BY i.item_name asc',
        $params
    );
}

function fetchSingleItem($item_id)
{
    return dbRow(
        'SELECT i.*, mu.unit_symbol, mu.unit_label, b.brand_id, b.brand_name,
                sp.sup_id, sp.sup_name, sp.sup_website,
                l.loc_id, l.loc_name, l.loc_parent_id, lp.loc_name AS loc_parent_name,
                s.status_id, s.status_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS
        . ' WHERE i.item_id = :item_id GROUP BY i.item_id LIMIT 1',
        ['item_id' => $item_id]
    );
}

function fetchItemCategoryIds($item_id): array
{
    return array_map('intval', array_column(dbAll(
        'SELECT ci.cat_id
         FROM categories_items ci
         INNER JOIN inv_categories c ON c.cat_id = ci.cat_id
         WHERE ci.item_id = :item_id
         ORDER BY c.cat_name',
        ['item_id' => $item_id]
    ), 'cat_id'));
}

function saveItemCategories($item_id, array $categoryIds): void
{
    dbRun('DELETE FROM categories_items WHERE item_id = :item_id', ['item_id' => $item_id]);

    foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
        if ($categoryId > 0) {
            dbRun('INSERT IGNORE INTO categories_items (cat_id, item_id) VALUES (:cat_id, :item_id)', [
                'cat_id'  => $categoryId,
                'item_id' => $item_id,
            ]);
        }
    }
}

/** Keyed by form field name; units grouped by unit type. */
function fetchItemFormOptions(string $type = 'part'): array
{
    $units = [];

    foreach (dbAll('SELECT unit_id, unit_label, unit_symbol, unit_type
                    FROM inv_measurement_units ORDER BY unit_type, unit_id asc') as $unit) {
        $units[$unit['unit_type']][$unit['unit_id']] = $unit['unit_label'] . ' (' . $unit['unit_symbol'] . ')';
    }

    $options = ['item_measurement_unit' => $units];

    foreach (array_keys(taxonomies()) as $key) {
        // Only categories of this kind are offered.
        $options['item_' . $key] = ($key === 'category')
            ? categoryOptions($type)
            : taxonomyOptions($key);
    }

    return $options;
}

/*
 * Item attachments
 */

function fetchItemFiles($item_id): array
{
    return dbAll(
        'SELECT * FROM inv_item_files WHERE file_item_id = :item_id
         ORDER BY file_uploaded_at asc, file_id asc',
        ['item_id' => $item_id]
    );
}

/** The caller checks file_item_id against the item it expects. */
function fetchItemFile($file_id)
{
    return dbRow(
        'SELECT * FROM inv_item_files WHERE file_id = :file_id LIMIT 1',
        ['file_id' => $file_id]
    );
}

function insertItemFile($item_id, string $stored, string $original, int $size, ?string $description = null): void
{
    dbRun(
        'INSERT INTO inv_item_files
            (file_item_id, file_stored_name, file_name, file_description, file_size)
         VALUES (:item_id, :stored, :original, :description, :size)',
        [
            'item_id'     => $item_id,
            'stored'      => $stored,
            'original'    => $original,
            'description' => $description,
            'size'        => $size,
        ]
    );
}

function updateItemFileDescription($file_id, ?string $description): void
{
    dbRun(
        'UPDATE inv_item_files SET file_description = :description WHERE file_id = :file_id LIMIT 1',
        ['file_id' => $file_id, 'description' => $description]
    );
}

function deleteItemFileRow($file_id): void
{
    dbRun('DELETE FROM inv_item_files WHERE file_id = :file_id LIMIT 1', ['file_id' => $file_id]);
}

/*
 * Dashboard
 */

function fetchDashboardTotals(): array
{
    return dbRow('
        SELECT
            (SELECT COUNT(*) FROM inv_items i WHERE ' . ITEM_IS_PART . ') AS part_count,
            (SELECT COUNT(*) FROM inv_items i WHERE ' . ITEM_IS_TOOL . ') AS tool_count,
            (SELECT COALESCE(SUM(i.item_quantity), 0) FROM inv_items i
                WHERE ' . ITEM_IS_PART . ') AS total_quantity,
            (SELECT COUNT(*) FROM inv_projects p
                INNER JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
                WHERE ps.project_status_name IN (' . openProjectStatusSql() . ')) AS open_project_count
    ');
}

/** $mode "low": at or under the reorder level; "over": committed beyond stock. */
function fetchStockWarnings(string $mode): array
{
    $having = ($mode === 'over')
        ? 'item_free_count < 0'
        : 'i.item_min_quantity > 0 AND item_free_count <= i.item_min_quantity';

    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_quantity, i.item_min_quantity,
                mu.unit_symbol, l.loc_name, lp.loc_name AS loc_parent_name, sp.sup_name,'
        . ITEM_STOCK_COLUMNS
        . ITEM_JOINS
        . ' WHERE ' . ITEM_IS_PART
        . ' GROUP BY i.item_id HAVING ' . $having
        . ' ORDER BY item_free_count asc, i.item_name asc'
    );
}

function fetchRecentItems(string $column, int $limit = 6): array
{
    $order = ($column === 'created') ? 'i.item_created_at' : 'i.item_updated_at';

    return dbAll(
        'SELECT i.item_id, i.item_name, i.item_image, i.item_created_at, i.item_updated_at,
                l.loc_name, lp.loc_name AS loc_parent_name, s.status_name,
                ' . ITEM_IS_TOOL . ' AS item_is_tool
         FROM inv_items i
         LEFT JOIN inv_locations l ON l.loc_id = i.item_loc_id
         LEFT JOIN inv_locations lp ON lp.loc_id = l.loc_parent_id
         LEFT JOIN inv_statuses s ON s.status_id = i.item_status
         ORDER BY ' . $order . ' DESC, i.item_id DESC
         LIMIT ' . (int)$limit
    );
}

/*
 * Projects
 */

function fetchProjectStatuses(): array
{
    return dbAll('SELECT * FROM inv_project_statuses ORDER BY project_status_id');
}

function countProjects(): int
{
    return (int)dbValue('SELECT COUNT(*) FROM inv_projects', [], 0);
}

function fetchProjects(?array $slice = null): array
{
    return dbAll('
        SELECT
            p.*,
            ps.project_status_name,
            COUNT(DISTINCT a.assembly_id) AS assembly_count,
            COALESCE(SUM(ai.quantity_required), 0) AS required_quantity,
            COALESCE(SUM(ai.quantity_installed), 0) AS installed_quantity
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
        LEFT JOIN inv_project_assemblies a ON a.assembly_project_id = p.project_id
        LEFT JOIN inv_assembly_items ai ON ai.assembly_id = a.assembly_id
        GROUP BY p.project_id
        ORDER BY p.project_name
    ' . ($slice ? paginationLimit($slice) : ''));
}

function fetchProject($project_id)
{
    return dbRow('
        SELECT p.*, ps.project_status_name
        FROM inv_projects p
        LEFT JOIN inv_project_statuses ps ON ps.project_status_id = p.project_status_id
        WHERE p.project_id = :project_id
        LIMIT 1
    ', ['project_id' => $project_id]);
}

function getProjectSummary($project_id)
{
    return dbRow('
        SELECT
            COALESCE(SUM(ai.quantity_required), 0) AS required_quantity,
            COALESCE(SUM(ai.quantity_allocated), 0) AS allocated_quantity,
            COALESCE(SUM(ai.quantity_installed), 0) AS installed_quantity
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        WHERE a.assembly_project_id = :project_id
    ', ['project_id' => $project_id]);
}

/** One row per item across every assembly. free_elsewhere is stock not yet committed. */
function fetchProjectRequirements($project_id): array
{
    return dbAll('
        SELECT
            i.item_id,
            i.item_name,
            i.item_part_no,
            i.item_quantity,
            mu.unit_symbol,
            sp.sup_id,
            sp.sup_name,
            sp.sup_website,
            SUM(ai.quantity_required) AS required_quantity,
            SUM(ai.quantity_allocated) AS allocated_quantity,
            SUM(ai.quantity_installed) AS installed_quantity,
            i.item_quantity
                - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items WHERE item_id = i.item_id), 0)
                AS free_elsewhere
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_suppliers sp ON sp.sup_id = i.item_sup_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE a.assembly_project_id = :project_id
        GROUP BY i.item_id
        ORDER BY sp.sup_name, i.item_name
    ', ['project_id' => $project_id]);
}

/*
 * Assemblies
 */

function countProjectAssemblies($project_id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_project_assemblies WHERE assembly_project_id = :project_id',
        ['project_id' => $project_id],
        0
    );
}

function fetchProjectAssemblies($project_id, ?array $slice = null): array
{
    return dbAll('
        SELECT
            a.*,
            COUNT(ai.assembly_item_id) AS item_count,
            COALESCE(SUM(ai.quantity_required), 0) AS quantity_required,
            COALESCE(SUM(ai.quantity_installed), 0) AS quantity_installed
        FROM inv_project_assemblies a
        LEFT JOIN inv_assembly_items ai ON ai.assembly_id = a.assembly_id
        WHERE a.assembly_project_id = :project_id
        GROUP BY a.assembly_id
        ORDER BY a.assembly_sort_order, a.assembly_name
    ' . ($slice ? paginationLimit($slice) : ''), ['project_id' => $project_id]);
}

function fetchAssembly($assembly_id)
{
    return dbRow('
        SELECT a.*, p.project_id, p.project_name
        FROM inv_project_assemblies a
        INNER JOIN inv_projects p ON p.project_id = a.assembly_project_id
        WHERE a.assembly_id = :assembly_id
        LIMIT 1
    ', ['assembly_id' => $assembly_id]);
}

function countAssemblyItems($assembly_id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_assembly_items WHERE assembly_id = :assembly_id',
        ['assembly_id' => $assembly_id],
        0
    );
}

function fetchAssemblyItems($assembly_id, ?array $slice = null): array
{
    return dbAll('
        SELECT ai.*, i.item_name, i.item_quantity, mu.unit_symbol
        FROM inv_assembly_items ai
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE ai.assembly_id = :assembly_id
        ORDER BY ai.assembly_item_sort_order, i.item_name
    ' . ($slice ? paginationLimit($slice) : ''), ['assembly_id' => $assembly_id]);
}

function fetchAssemblyItem($assembly_item_id)
{
    return dbRow('
        SELECT ai.*, a.assembly_name, a.assembly_project_id, i.item_name, i.item_quantity, mu.unit_symbol
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_items i ON i.item_id = ai.item_id
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE ai.assembly_item_id = :id
        LIMIT 1
    ', ['id' => $assembly_item_id]);
}

/** Read before deleting, so the reservations it frees can be shared out. */
function fetchAssemblyItemIds($assembly_id): array
{
    return array_column(dbAll(
        'SELECT DISTINCT item_id FROM inv_assembly_items WHERE assembly_id = :assembly_id',
        ['assembly_id' => $assembly_id]
    ), 'item_id');
}

function fetchProjectItemIds($project_id): array
{
    return array_column(dbAll(
        'SELECT DISTINCT ai.item_id
         FROM inv_assembly_items ai
         INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
         WHERE a.assembly_project_id = :project_id',
        ['project_id' => $project_id]
    ), 'item_id');
}

function fetchAvailableItemsForAssembly($assembly_id): array
{
    return dbAll('
        SELECT i.item_id, i.item_name, i.item_quantity, mu.unit_symbol,
               i.item_quantity
                   - COALESCE((SELECT SUM(quantity_allocated) FROM inv_assembly_items
                               WHERE item_id = i.item_id), 0)
                   AS item_free_count
        FROM inv_items i
        LEFT JOIN inv_measurement_units mu ON mu.unit_id = i.item_measurement_unit
        WHERE ' . ITEM_IS_PART . '
          AND NOT EXISTS (
            SELECT 1 FROM inv_assembly_items ai
            WHERE ai.assembly_id = :assembly_id AND ai.item_id = i.item_id
        )
        ORDER BY i.item_name
    ', ['assembly_id' => $assembly_id]);
}

function fetchItemAssemblyUsage($item_id): array
{
    return dbAll('
        SELECT ai.*, a.assembly_id, a.assembly_name, p.project_id, p.project_name
        FROM inv_assembly_items ai
        INNER JOIN inv_project_assemblies a ON a.assembly_id = ai.assembly_id
        INNER JOIN inv_projects p ON p.project_id = a.assembly_project_id
        WHERE ai.item_id = :item_id
        ORDER BY p.project_name, a.assembly_sort_order, a.assembly_name
    ', ['item_id' => $item_id]);
}

<?php

/**
 * Splitting long listings across pages.
 *
 * A listing asks how many rows there are in total, works out which slice the
 * query string is asking for, and passes that slice to its own query so only
 * those rows are read. Nothing here knows what is being listed. The heading
 * goes on showing the total rather than however much fitted on this page.
 */

/** Rows per page. Set 'site' => ['per_page' => 25] in user.config.php. */
function perPage(): int
{
    $configured = (int)config('site.per_page', 50);

    // A page of everything defeats the point, and a page of nothing would
    // divide by zero below.
    return ($configured > 0) ? min($configured, 500) : 50;
}

/**
 * Which slice of $total rows to show, read from the query string.
 *
 * $param names the value holding the page number, so a page with two long
 * tables on it can move through them independently.
 */
function paginate(int $total, string $param = 'p'): array
{
    $perPage = perPage();
    $pages = max(1, (int)ceil($total / $perPage));

    // Asking for page 9 of 3, by editing the address or by deleting rows out
    // from under a bookmark, lands on the last page rather than on nothing.
    $current = min(max(1, (int)queryParam($param)), $pages);
    $offset = ($current - 1) * $perPage;

    return [
        'param'   => $param,
        'total'   => $total,
        'perPage' => $perPage,
        'pages'   => $pages,
        'current' => $current,
        'offset'  => $offset,
        'from'    => $total ? $offset + 1 : 0,
        'to'      => min($offset + $perPage, $total),
    ];
}

/**
 * The LIMIT clause for a slice, for appending to a query.
 *
 * The two figures are worked out in paginate() and cast again here, so this
 * never carries anything from the query string into the SQL.
 */
function paginationLimit(array $slice): string
{
    return ' LIMIT ' . (int)$slice['perPage'] . ' OFFSET ' . (int)$slice['offset'];
}

/**
 * The current address with one query string value changed, so moving through
 * the pages keeps whatever filters and search are in force.
 */
function urlWithParam(string $name, $value): string
{
    $params = $_GET;
    $params[$name] = $value;

    return escapeHtml('index.php?' . http_build_query($params));
}

/**
 * The page numbers worth offering: both ends, and a window around where you
 * are. Null stands in for a stretch left out.
 */
function paginationNumbers(int $current, int $pages, int $window = 2): array
{
    $numbers = [];
    $previous = 0;

    for ($number = 1; $number <= $pages; $number++) {
        if ($number !== 1 && $number !== $pages && abs($number - $current) > $window) {
            continue;
        }

        if ($previous && $number - $previous > 1) {
            $numbers[] = null;
        }

        $numbers[] = $number;
        $previous = $number;
    }

    return $numbers;
}

/**
 * The bar under a listing: what you are looking at, and the way to the rest.
 *
 * $noun names the rows, so it reads "of 312 parts" rather than "of 312".
 */
function renderPagination(array $slice, string $noun = 'rows'): void
{
    if ($slice['total'] === 0) {
        return;
    }

    echo '<nav class="pagination" aria-label="Pagination">' . "\n";
    echo '    <p class="pagination-count">Showing ' . $slice['from'] . '&ndash;' . $slice['to']
        . ' of ' . $slice['total'] . ' ' . escapeHtml($noun) . '</p>' . "\n";

    if ($slice['pages'] > 1) {
        echo '    <p class="pagination-pages">' . "\n";
        paginationStep($slice, $slice['current'] - 1, 'Previous', $slice['current'] > 1);

        foreach (paginationNumbers($slice['current'], $slice['pages']) as $number) {
            if ($number === null) {
                echo '        <span class="pagination-gap">&hellip;</span>' . "\n";
                continue;
            }

            echo '        ' . ($number === $slice['current']
                ? '<span class="pagination-current" aria-current="page">' . $number . '</span>'
                : '<a href="' . urlWithParam($slice['param'], $number) . '">' . $number . '</a>') . "\n";
        }

        paginationStep($slice, $slice['current'] + 1, 'Next', $slice['current'] < $slice['pages']);
        echo '    </p>' . "\n";
    }

    echo '</nav>' . "\n";
}

/**
 * A previous/next control, shown greyed out at the ends so the bar keeps its
 * shape rather than shifting as you move through it.
 */
function paginationStep(array $slice, int $number, string $label, bool $enabled): void
{
    echo '        ' . ($enabled
        ? '<a href="' . urlWithParam($slice['param'], $number) . '" class="pagination-step">' . $label . '</a>'
        : '<span class="pagination-step is-disabled">' . $label . '</span>') . "\n";
}

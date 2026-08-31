<?php

/**
 * Splitting long listings across pages. Nothing here knows what is being
 * listed.
 */

/** Rows per page. Set site.per_page in user.config.php. */
function perPage(): int
{
    $configured = (int)config('site.per_page', 50);

    return ($configured > 0) ? min($configured, 500) : 50;
}

/** $param lets two tables on one page move through their rows independently. */
function paginate(int $total, string $param = 'p'): array
{
    $perPage = perPage();
    $pages = max(1, (int)ceil($total / $perPage));

    // Page 9 of 3 lands on the last page rather than on nothing.
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

/** Cast again here, so nothing from the query string reaches the SQL. */
function paginationLimit(array $slice): string
{
    return ' LIMIT ' . (int)$slice['perPage'] . ' OFFSET ' . (int)$slice['offset'];
}

/** The current address with one value changed, so filters survive paging. */
function urlWithParam(string $name, $value): string
{
    $params = $_GET;
    $params[$name] = $value;

    return escapeHtml('index.php?' . http_build_query($params));
}

/** Both ends plus a window around $current. Null stands in for a gap. */
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

/** $noun names the rows, so it reads "of 312 parts" rather than "of 312". */
function renderPagination(array $slice, string $noun = 'rows'): void
{
    if ($slice['total'] === 0) {
        return;
    }

    template('pagination', compact('slice', 'noun'));
}

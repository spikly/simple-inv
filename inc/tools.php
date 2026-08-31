<?php

/**
 * Tools going in and out of the workshop. Signing one out opens a row in
 * inv_tool_loans; signing it in stamps loan_in_at rather than deleting the
 * row, so the history is kept. A tool is out while one row has loan_in_at
 * null, and it can only have one at a time.
 */

/** Tools have no quantity, but the column is not nullable, so: whole pieces. */
function pieceUnitId(): int
{
    static $unitId = null;

    if ($unitId === null) {
        $id = dbValue("SELECT unit_id FROM inv_measurement_units WHERE unit_symbol = 'pcs' LIMIT 1");
        $unitId = (int)($id ?? dbValue('SELECT unit_id FROM inv_measurement_units ORDER BY unit_id LIMIT 1', [], 1));
    }

    return $unitId;
}

/** The loan a tool is currently out on, or null when it is here. */
function fetchOpenToolLoan($item_id): ?array
{
    return dbRow(
        'SELECT * FROM inv_tool_loans
         WHERE loan_item_id = :item_id AND loan_in_at IS NULL
         ORDER BY loan_out_at DESC LIMIT 1',
        ['item_id' => (int)$item_id]
    ) ?: null;
}

function countToolLoans($item_id): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_tool_loans WHERE loan_item_id = :item_id',
        ['item_id' => (int)$item_id],
        0
    );
}

/** Everywhere a tool has been, newest first. */
function fetchToolLoans($item_id, ?array $slice = null): array
{
    return dbAll(
        'SELECT * FROM inv_tool_loans WHERE loan_item_id = :item_id
         ORDER BY loan_out_at DESC, loan_id DESC'
        . ($slice ? paginationLimit($slice) : ''),
        ['item_id' => (int)$item_id]
    );
}

/**
 * Out right now, overdue first. Only loans against something that is actually
 * a tool: upgrading deployments can leave rows against items since filed as
 * parts, and a part is not out with anybody.
 */
function fetchOpenToolLoans(): array
{
    return dbAll(
        'SELECT l.*, i.item_name, loc.loc_name, locp.loc_name AS loc_parent_name
         FROM inv_tool_loans l
         INNER JOIN inv_items i ON i.item_id = l.loan_item_id
         LEFT JOIN inv_locations loc ON loc.loc_id = i.item_loc_id
         LEFT JOIN inv_locations locp ON locp.loc_id = loc.loc_parent_id
         WHERE l.loan_in_at IS NULL AND ' . ITEM_IS_TOOL . '
         ORDER BY (l.loan_due_at IS NOT NULL AND l.loan_due_at < CURDATE()) DESC,
                  l.loan_due_at IS NULL, l.loan_due_at ASC, l.loan_out_at ASC'
    );
}

function countOverdueLoans(array $loans): int
{
    $overdue = array_filter($loans, function (array $loan) {
        return loanIsOverdue($loan['loan_due_at'], $loan['loan_in_at']);
    });

    return count($overdue);
}

/** True when a loan is still open and its due date has gone by. */
function loanIsOverdue(?string $dueAt, ?string $inAt = null): bool
{
    return $inAt === null && $dueAt !== null && $dueAt !== '' && $dueAt < date('Y-m-d');
}

/** The columns to write, or ['errors' => [...]] keyed by field. Checks both fields. */
function toolLoanValues(array $post): array
{
    $errors = [];
    $to = trim((string)($post['loan_to'] ?? ''));

    if ($to === '') {
        $errors['loan_to'] = 'Say who or where the tool is going to.';
    }

    $due = trim((string)($post['loan_due_at'] ?? ''));

    if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        $errors['loan_due_at'] = 'The due date must be a date.';
    }

    if ($errors) {
        return ['errors' => $errors];
    }

    return ['values' => [
        'loan_to'     => $to,
        'loan_due_at' => $due !== '' ? $due : null,
        'loan_notes'  => textOrNull($post, 'loan_notes'),
    ]];
}

/** Returns an error when it is already with somebody; one tool, one place. */
function signToolOut($item_id, array $values): ?string
{
    if (fetchOpenToolLoan($item_id)) {
        return 'That tool is already signed out. Sign it back in first.';
    }

    dbRun(
        'INSERT INTO inv_tool_loans (loan_item_id, loan_to, loan_due_at, loan_notes)
         VALUES (:loan_item_id, :loan_to, :loan_due_at, :loan_notes)',
        $values + ['loan_item_id' => (int)$item_id]
    );

    return null;
}

/** Sign a tool back in, keeping the row as history. */
function signToolIn($loan_id): void
{
    dbRun(
        'UPDATE inv_tool_loans SET loan_in_at = NOW()
         WHERE loan_id = :loan_id AND loan_in_at IS NULL',
        ['loan_id' => (int)$loan_id]
    );
}

/**
 * The returned-at value the database will take, or ['errors' => [...]].
 * Clearing it reopens the loan, which only works while nothing else has it.
 */
function toolReturnValue(array $loan, array $post): array
{
    $returned = str_replace('T', ' ', trim((string)($post['loan_in_at'] ?? '')));

    if ($returned === '') {
        $open = fetchOpenToolLoan($loan['loan_item_id']);

        if ($open && (int)$open['loan_id'] !== (int)$loan['loan_id']) {
            return ['errors' => ['loan_in_at' => 'That tool is already signed out to '
                . escapeHtml($open['loan_to']) . ', so this record cannot be reopened.']];
        }

        return ['value' => null];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $returned)) {
        return ['errors' => ['loan_in_at' => 'The returned date must be a date and time.']];
    }

    if ($returned < $loan['loan_out_at']) {
        return ['errors' => ['loan_in_at' => 'A tool cannot come back before it went out.']];
    }

    return ['value' => $returned];
}

/*
 * Presentation
 */

function toolBorrowerCell(array $item): string
{
    if (empty($item['loan_to'])) {
        return '<span class="stock stock-ok">In</span>';
    }

    $class = loanIsOverdue($item['loan_due_at'] ?? null) ? 'stock-over' : 'stock-low';

    return '<span class="stock ' . $class . '">' . escapeHtml($item['loan_to']) . '</span>'
        . '<small class="row-note">out since ' . escapeHtml(formatDate($item['loan_out_at'])) . '</small>';
}

function toolDueCell(array $item): string
{
    if (empty($item['loan_to'])) {
        return '-';
    }

    if (empty($item['loan_due_at'])) {
        return 'No date set';
    }

    return escapeHtml(formatDate($item['loan_due_at']))
        . (loanIsOverdue($item['loan_due_at']) ? '<small class="row-note">overdue</small>' : '');
}

function renderToolLoans(array $loans): void
{
    template('tool/loans', compact('loans'));
}

<?php

/**
 * Tools going in and out of the workshop.
 *
 * A tool is one physical object rather than a quantity, so it is either here
 * or it is with somebody. Signing it out opens a row in inv_tool_loans and
 * signing it back in stamps loan_in_at on that row instead of deleting it, so
 * every tool keeps a history of where it has been.
 *
 * A tool is out while it has a row with loan_in_at still null, and it can only
 * have one of those at a time.
 */

/**
 * The measurement unit tools are stored against. They have no quantity, but
 * the column is not nullable, so they are counted in whole pieces.
 */
function pieceUnitId(): int
{
    $id = dbValue("SELECT unit_id FROM inv_measurement_units WHERE unit_symbol = 'pcs' LIMIT 1");

    return (int)($id ?? dbValue('SELECT unit_id FROM inv_measurement_units ORDER BY unit_id LIMIT 1', [], 1));
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

/** Everywhere a tool has been, newest first. */
function fetchToolLoans($item_id): array
{
    return dbAll(
        'SELECT * FROM inv_tool_loans WHERE loan_item_id = :item_id
         ORDER BY loan_out_at DESC, loan_id DESC',
        ['item_id' => (int)$item_id]
    );
}

/**
 * Only loans against something that is actually a tool count.
 *
 * An install upgraded from deployments can hold rows against items that were
 * then filed as parts. They are kept as history, but a part is not out with
 * anybody, so nothing that counts tools should see them.
 */
const LOAN_ITEM_IS_TOOL = 'NOT (' . ITEM_IS_PART . ')';

/** Every tool that is out right now, overdue ones first. */
function fetchOpenToolLoans(int $limit = 0): array
{
    return dbAll(
        'SELECT l.*, i.item_name, loc.loc_name
         FROM inv_tool_loans l
         INNER JOIN inv_items i ON i.item_id = l.loan_item_id
         LEFT JOIN inv_locations loc ON loc.loc_id = i.item_loc_id
         WHERE l.loan_in_at IS NULL AND ' . LOAN_ITEM_IS_TOOL . '
         ORDER BY (l.loan_due_at IS NOT NULL AND l.loan_due_at < CURDATE()) DESC,
                  l.loan_due_at IS NULL, l.loan_due_at ASC, l.loan_out_at ASC'
        . ($limit > 0 ? ' LIMIT ' . $limit : '')
    );
}

function countOpenToolLoans(): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_tool_loans l
          INNER JOIN inv_items i ON i.item_id = l.loan_item_id
          WHERE l.loan_in_at IS NULL AND ' . LOAN_ITEM_IS_TOOL,
        [],
        0
    );
}

function countOverdueToolLoans(): int
{
    return (int)dbValue(
        'SELECT COUNT(*) FROM inv_tool_loans l
          INNER JOIN inv_items i ON i.item_id = l.loan_item_id
          WHERE l.loan_in_at IS NULL AND l.loan_due_at IS NOT NULL
            AND l.loan_due_at < CURDATE() AND ' . LOAN_ITEM_IS_TOOL,
        [],
        0
    );
}

/** True when a loan is still open and its due date has gone by. */
function loanIsOverdue(?string $dueAt, ?string $inAt = null): bool
{
    return $inAt === null && $dueAt !== null && $dueAt !== '' && $dueAt < date('Y-m-d');
}

/**
 * The submitted sign-out data as the columns to write, or an error message.
 */
function toolLoanValues(array $post): array
{
    $to = trim((string)($post['loan_to'] ?? ''));

    if ($to === '') {
        return ['error' => 'Say who or where the tool is going to.'];
    }

    $due = trim((string)($post['loan_due_at'] ?? ''));

    if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        return ['error' => 'The due date must be a date.'];
    }

    return ['values' => [
        'loan_to'     => $to,
        'loan_due_at' => $due !== '' ? $due : null,
        'loan_notes'  => textOrNull($post, 'loan_notes'),
    ]];
}

/**
 * Sign a tool out. Returns an error message when it is already with somebody,
 * since one tool cannot be in two places.
 */
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
 * The returned-at value submitted on the edit form, as something the database
 * will take, or ['error' => message].
 *
 * Clearing it puts the tool back out with whoever had it, which only works
 * while nothing else has it.
 */
function toolReturnValue(array $loan, array $post): array
{
    $returned = str_replace('T', ' ', trim((string)($post['loan_in_at'] ?? '')));

    if ($returned === '') {
        $open = fetchOpenToolLoan($loan['loan_item_id']);

        if ($open && (int)$open['loan_id'] !== (int)$loan['loan_id']) {
            return ['error' => 'That tool is already signed out to '
                . $open['loan_to'] . ', so this record cannot be reopened.'];
        }

        return ['value' => null];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $returned)) {
        return ['error' => 'The returned date must be a date and time.'];
    }

    if ($returned < $loan['loan_out_at']) {
        return ['error' => 'A tool cannot come back before it went out.'];
    }

    return ['value' => $returned];
}

/*
 * Presentation
 */

/** Who has a tool right now, for a listing cell. */
function toolBorrowerCell(array $item): string
{
    if (empty($item['loan_to'])) {
        return '<span class="stock stock-ok">In</span>';
    }

    $class = loanIsOverdue($item['loan_due_at'] ?? null) ? 'stock-over' : 'stock-low';

    return '<span class="stock ' . $class . '">' . escapeHtml($item['loan_to']) . '</span>'
        . '<small class="row-note">out since ' . escapeHtml(formatDate($item['loan_out_at'])) . '</small>';
}

/** When a tool is due back, for a listing cell. */
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

/**
 * A tool's history: who had it, when it went and when it came back.
 */
function renderToolLoans(array $loans): void
{
    if (!$loans) {
        echo '<p>This tool has never been signed out.</p>' . "\n";
        return;
    }

    renderTable(
        ['Signed Out To', 'Out', 'Due Back', 'Returned', 'Notes', 'Edit'],
        $loans,
        function ($loan) {
            $overdue = loanIsOverdue($loan['loan_due_at'], $loan['loan_in_at']);

            return [
                escapeHtml($loan['loan_to']),
                escapeHtml(formatDate($loan['loan_out_at'])),
                $loan['loan_due_at']
                    ? escapeHtml(formatDate($loan['loan_due_at']))
                        . ($overdue ? '<small class="row-note">overdue</small>' : '')
                    : '-',
                $loan['loan_in_at']
                    ? escapeHtml(formatDate($loan['loan_in_at']))
                    : '<span class="stock ' . ($overdue ? 'stock-over' : 'stock-low') . '">Still out</span>',
                escapeHtml((string)$loan['loan_notes']),
                '<a href="index.php?page=edit-loan&loan_id=' . (int)$loan['loan_id']
                    . '&item_id=' . (int)$loan['loan_item_id'] . '">Edit</a>',
            ];
        }
    );
}

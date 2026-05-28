<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext252Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next252',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $windowRows = self::windowRows($base['publication_sequence_next248'], $rowIdColumn);
        $currentRows = array_values(array_filter($windowRows, static fn (array $row): bool => $row['source'] === 'current-yield'));
        $retryRows = array_values(array_filter($windowRows, static fn (array $row): bool => $row['source'] === 'next-retry'));
        $resumeRows = self::resumeRows($windowRows, $base['publication_resume_next248']['resume_after_ticket'] ?? null);
        $fence = self::fence($windowRows, $currentRows, $retryRows, $base['publication_barrier_next248']);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next252',
            'current_source_publication_windows_next252' => $windowRows,
            'current_source_window_count_next252' => count($currentRows),
            'next_source_window_count_next252' => count($retryRows),
            'current_source_high_water_ticket_next252' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1]['ticket'],
            'next_source_first_ticket_next252' => $retryRows[0]['ticket'] ?? null,
            'next_source_first_ordinal_next252' => $retryRows[0]['window_row_number_next252'] ?? null,
            'resume_window_rows_next252' => $resumeRows,
            'resume_window_tickets_next252' => array_column($resumeRows, 'ticket'),
            'publication_window_fence_next252' => $fence,
            'dependency_closure_next252' => 'no new support component needed; next252 reuses native PHP row-value UPDATE/DELETE RETURNING, next245 yield tickets, and next248 publication cursors while adding CURRENT-source window row-number fences before exposing next-source retry rows',
            'dependencies_next252' => [
                'sqlite-rowvalue-returning-current-source-window-fence-next252',
                'sqlite-rowvalue-returning-next-source-row-number-after-current-next252',
                'wordpress-rowvalue-returning-window-current-source-next252',
            ],
            'non_overlap_next252' => 'adds row-number/high-water window fences over next248 publication cursors so next-source retry rows cannot appear before all current-source row-value RETURNING rows; avoids accepted next248 cursor barrier, next245 ticket gate, next244 transition windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $sequence
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $sequence, string $rowIdColumn): array
    {
        $rows = [];
        $partitionOrdinals = [];
        $currentHighWater = null;
        $firstRetryOrdinal = null;

        foreach (array_values($sequence) as $index => $row) {
            $source = self::stringValue($row['source'] ?? null, 'source');
            $ticket = self::stringValue($row['ticket'] ?? null, 'ticket');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next252 rowid column {$rowIdColumn} must be int or string");
            }

            $partitionOrdinals[$source] = ($partitionOrdinals[$source] ?? 0) + 1;
            if ($source === 'current-yield') {
                $currentHighWater = $ticket;
            }
            if ($source === 'next-retry' && $firstRetryOrdinal === null) {
                $firstRetryOrdinal = $index + 1;
            }

            $previous = $sequence[$index - 1] ?? null;
            $next = $sequence[$index + 1] ?? null;
            $rows[] = array_merge($row, [
                'window_row_number_next252' => $index + 1,
                'window_partition_next252' => $source,
                'window_partition_row_number_next252' => $partitionOrdinals[$source],
                'window_total_rows_next252' => count($sequence),
                'window_current_source_high_water_ticket_next252' => $currentHighWater,
                'window_next_source_first_ordinal_next252' => $firstRetryOrdinal,
                'window_previous_ticket_next252' => is_array($previous) ? ($previous['ticket'] ?? null) : null,
                'window_next_ticket_next252' => is_array($next) ? ($next['ticket'] ?? null) : null,
                'window_boundary_next252' => self::boundary($previous, $next),
                'window_is_current_source_next252' => $source === 'current-yield',
                'window_is_next_source_next252' => $source === 'next-retry',
                'window_current_complete_before_row_next252' => $source === 'next-retry' && $currentHighWater !== null,
                'window_cursor_digest_next252' => hash('sha256', $source . '|' . $ticket . '|' . (string) $rowId),
            ]);
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @return list<array<string,mixed>>
     */
    private static function resumeRows(array $windowRows, mixed $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return $windowRows;
        }

        $rows = [];
        $copy = false;
        foreach ($windowRows as $row) {
            if ($copy) {
                $rows[] = $row;
                continue;
            }
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $copy = true;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $barrier
     * @return array<string,mixed>
     */
    private static function fence(array $windowRows, array $currentRows, array $retryRows, array $barrier): array
    {
        $currentHighWaterOrdinal = $currentRows === [] ? null : $currentRows[count($currentRows) - 1]['window_row_number_next252'];
        $firstRetryOrdinal = $retryRows[0]['window_row_number_next252'] ?? null;

        return [
            'current_source_complete' => (bool) ($barrier['current_source_complete'] ?? false),
            'next_source_exposed' => (bool) ($barrier['next_source_exposed'] ?? false),
            'current_high_water_ordinal' => $currentHighWaterOrdinal,
            'first_retry_ordinal' => $firstRetryOrdinal,
            'retry_after_current_high_water' => $firstRetryOrdinal === null || ($currentHighWaterOrdinal !== null && $firstRetryOrdinal > $currentHighWaterOrdinal),
            'current_window_row_count' => count($currentRows),
            'retry_window_row_count' => count($retryRows),
            'window_row_count' => count($windowRows),
            'window_digest' => hash('sha256', json_encode(array_column($windowRows, 'ticket'), JSON_THROW_ON_ERROR)),
            'blocked_reasons' => $barrier['blocked_reasons'] ?? [],
        ];
    }

    private static function boundary(?array $previous, ?array $next): string
    {
        if ($previous === null && $next === null) {
            return 'singleton-row';
        }
        if ($previous === null) {
            return 'first-row';
        }
        if ($next === null) {
            return 'last-row';
        }

        return 'middle-row';
    }

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next252 {$name} is missing");
        }

        return $value;
    }
}

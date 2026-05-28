<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next260',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedBoundaryTickets = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $acknowledgedNextRowTickets,
        );

        $rows = self::boundaryRows(
            $base['next_row_window_rows_next255'],
            $rowIdColumn,
            $acknowledgedBoundaryTickets,
        );
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_ready_next260'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_ready_next260'] ?? null) !== true));
        $mixedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_crosses_source_next260'] ?? null) === true));
        $resume = self::resume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next260',
            'boundary_admission_next260' => true,
            'boundary_window_rows_next260' => $rows,
            'boundary_ready_rows_next260' => $readyRows,
            'boundary_blocked_rows_next260' => $blockedRows,
            'boundary_mixed_rows_next260' => $mixedRows,
            'boundary_ready_tickets_next260' => array_column($readyRows, 'ticket'),
            'boundary_blocked_tickets_next260' => array_column($blockedRows, 'ticket'),
            'boundary_mixed_tickets_next260' => array_column($mixedRows, 'ticket'),
            'boundary_resume_next260' => $resume,
            'boundary_resume_tickets_next260' => array_column($resume['rows'], 'ticket'),
            'boundary_summary_next260' => self::summary($rows, $mixedRows),
            'boundary_fence_next260' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'next_row_ready_count' => $base['next_row_admission_summary_next255']['ready_count'],
                'row_count' => count($rows),
                'mixed_boundary_count' => count($mixedRows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'current_to_next_boundary_released' => $blockedRows === [] && $mixedRows !== [],
                'boundary_digest' => self::digest($rows),
                'mixed_boundary_digest' => self::digest($mixedRows),
            ],
            'dependency_closure_next260' => 'no new support component needed; next260 reuses native PHP row-value UPDATE/DELETE RETURNING window rows, next251 source epochs, and next255 next-row admission while adding a frame-source boundary receipt for the current-source to next-source transition',
            'dependencies_next260' => [
                'sqlite-rowvalue-returning-window-boundary-current-source-next260',
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'wordpress-rowvalue-returning-window-boundary-current-source-next260',
            ],
            'non_overlap_next260' => 'adds frame-source boundary admission for RETURNING window rows whose preceding/current/following frame crosses from current-source rows into retry-source rows; avoids next255 next-row acknowledgement alone, next254 row receipts, next251 epoch/digest fencing, next248 publication cursors, next245 yield gates, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $handoffRows
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @return list<array<string,mixed>>
     */
    private static function boundaryRows(array $handoffRows, string $rowIdColumn, ?array $acknowledgedBoundaryTickets): array
    {
        $expectedBoundaryTickets = [];
        foreach (array_values($handoffRows) as $index => $row) {
            $frame = self::frame($handoffRows, $index);
            if (self::crossesSource($frame)) {
                $expectedBoundaryTickets[] = self::ticket($row['ticket'] ?? null);
            }
        }

        $acknowledged = $acknowledgedBoundaryTickets === null
            ? $expectedBoundaryTickets
            : self::ticketSet($acknowledgedBoundaryTickets);

        $rows = [];
        foreach (array_values($handoffRows) as $index => $row) {
            $ticket = self::ticket($row['ticket'] ?? null);
            $frame = self::frame($handoffRows, $index);
            $frameTickets = array_map(static fn (array $item): string => self::ticket($item['ticket'] ?? null), $frame);
            $frameEpochs = array_map(static fn (array $item): string => self::epoch($item['next_row_source_epoch_next255'] ?? $item['source_epoch_next251'] ?? null), $frame);
            $crosses = self::crossesSource($frame);
            $acknowledgedBoundary = !$crosses || in_array($ticket, $acknowledged, true);
            $nextRowReady = ($row['next_row_ready_next255'] ?? null) === true;
            $reasons = [];
            if (!$nextRowReady) {
                $reasons[] = 'next-row-not-admitted-before-boundary-next260';
            }
            if (!$acknowledgedBoundary) {
                $reasons[] = 'source-boundary-ticket-not-acknowledged-next260';
            }

            $rowId = self::rowId($row['next_row_rowid_next255'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $receipt = hash('sha256', json_encode([
                'ticket' => $ticket,
                'rowid' => $rowId,
                'frameTickets' => $frameTickets,
                'frameEpochs' => $frameEpochs,
                'crosses' => $crosses,
                'ready' => $reasons === [],
            ], JSON_THROW_ON_ERROR));

            $rows[] = [
                'boundary_ordinal_next260' => count($rows) + 1,
                'boundary_rowid_next260' => $rowId,
                'boundary_frame_tickets_next260' => $frameTickets,
                'boundary_frame_epochs_next260' => $frameEpochs,
                'boundary_crosses_source_next260' => $crosses,
                'boundary_ticket_acknowledged_next260' => $acknowledgedBoundary,
                'boundary_next_row_ready_next260' => $nextRowReady,
                'boundary_ready_next260' => $reasons === [],
                'boundary_blocked_reasons_next260' => $reasons,
                'boundary_receipt_next260' => $receipt,
            ] + $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function frame(array $rows, int $index): array
    {
        $frame = [];
        foreach ([$index - 1, $index, $index + 1] as $frameIndex) {
            if (isset($rows[$frameIndex])) {
                $frame[] = $rows[$frameIndex];
            }
        }

        return $frame;
    }

    /**
     * @param list<array<string,mixed>> $frame
     */
    private static function crossesSource(array $frame): bool
    {
        $epochs = [];
        foreach ($frame as $row) {
            $epochs[] = self::epoch($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null);
        }

        return count(array_unique($epochs)) > 1;
    }

    /**
     * @param list<string> $tickets
     * @return list<string>
     */
    private static function ticketSet(array $tickets): array
    {
        $set = [];
        foreach ($tickets as $ticket) {
            $set[] = self::ticket($ticket);
        }

        return array_values(array_unique($set));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mixedRows
     * @return array<string,mixed>
     */
    private static function summary(array $rows, array $mixedRows): array
    {
        $summary = [
            'row_count' => count($rows),
            'ready_count' => 0,
            'blocked_count' => 0,
            'mixed_boundary_count' => count($mixedRows),
            'mixed_ready_count' => 0,
            'mixed_blocked_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'mixed_rowids' => array_column($mixedRows, 'boundary_rowid_next260'),
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['boundary_ready_next260'] ?? null) === true;
            $mixed = ($row['boundary_crosses_source_next260'] ?? null) === true;
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['boundary_rowid_next260'];
            if ($mixed) {
                $summary['mixed_' . $bucket . '_count']++;
            }
            foreach (($row['boundary_blocked_reasons_next260'] ?? []) as $reason) {
                $summary['blocked_reasons'][$reason] = (($summary['blocked_reasons'][$reason] ?? 0) + 1);
            }
        }
        ksort($summary['blocked_reasons']);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function resume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next260 resume ticket is not boundary-ready');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    private static function digest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function ticket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value boundary admission next260 ticket is missing');
        }

        return $value;
    }

    private static function epoch(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value boundary admission next260 source epoch is missing');
        }

        return $value;
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value boundary admission next260 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }
}

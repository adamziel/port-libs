<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext255Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next255',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan::execute(
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

        $rows = self::nextRowRows($base['source_handoff_rows_next251'], $rowIdColumn, $acknowledgedNextRowTickets);
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['next_row_ready_next255'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['next_row_ready_next255'] ?? null) !== true));
        $resume = self::resume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next255',
            'next_row_admission_next255' => true,
            'next_row_window_rows_next255' => $rows,
            'next_row_ready_rows_next255' => $readyRows,
            'next_row_blocked_rows_next255' => $blockedRows,
            'next_row_ready_tickets_next255' => array_column($readyRows, 'ticket'),
            'next_row_blocked_tickets_next255' => array_column($blockedRows, 'ticket'),
            'next_row_resume_next255' => $resume,
            'next_row_resume_tickets_next255' => array_column($resume['rows'], 'ticket'),
            'next_row_admission_summary_next255' => self::summary($rows),
            'next_row_admission_fence_next255' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'source_handoff_token' => $base['source_handoff_barrier_next251']['handoff_token'],
                'window_mode' => 'RETURNING rows next-row admission after current source handoff',
                'row_count' => count($rows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'ready_digest' => self::digest($readyRows),
                'blocked_digest' => self::digest($blockedRows),
                'all_retry_rows_acknowledged' => self::allRetryRowsAcknowledged($rows),
                'all_current_rows_acknowledged' => self::allCurrentRowsAcknowledged($rows),
            ],
            'dependency_closure_next255' => 'no new support component needed; next255 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next251 source handoff rows, and window cursor tickets while adding next-row admission receipts',
            'dependencies_next255' => [
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'sqlite-rowvalue-returning-current-source-handoff-next251',
                'wordpress-rowvalue-returning-window-next-row-current-source-next255',
            ],
            'non_overlap_next255' => 'adds next-row admission receipts after accepted next251 source epoch/digest handoff; avoids next250 EXCLUDE TIES, next248 resumable publication, next245 yield gates, next232-next247 window frame variants, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $handoffRows
     * @param list<string>|null $acknowledgedTickets
     * @return list<array<string,mixed>>
     */
    private static function nextRowRows(array $handoffRows, string $rowIdColumn, ?array $acknowledgedTickets): array
    {
        $acknowledged = $acknowledgedTickets === null
            ? array_column($handoffRows, 'ticket')
            : self::ticketSet($acknowledgedTickets);

        $rows = [];
        foreach (array_values($handoffRows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value next-row admission next255 rows are malformed');
            }
            $ticket = self::ticket($row['ticket'] ?? null);
            $previous = $handoffRows[$index - 1] ?? null;
            $next = $handoffRows[$index + 1] ?? null;
            $currentAcknowledged = in_array($ticket, $acknowledged, true);
            $previousTicket = is_array($previous) ? self::ticket($previous['ticket'] ?? null) : null;
            $previousAcknowledged = $previousTicket === null || in_array($previousTicket, $acknowledged, true);
            $ready = $currentAcknowledged && $previousAcknowledged;
            $blockedReasons = [];
            if (!$currentAcknowledged) {
                $blockedReasons[] = 'current-returning-ticket-not-acknowledged-next255';
            }
            if (!$previousAcknowledged) {
                $blockedReasons[] = 'previous-returning-ticket-not-acknowledged-next255';
            }

            $rows[] = [
                'ticket' => $ticket,
                'next_row_ordinal_next255' => count($rows) + 1,
                'next_row_rowid_next255' => self::rowId($row[$rowIdColumn] ?? $row['option_id'] ?? null, $rowIdColumn),
                'next_row_source_epoch_next255' => self::stringValue($row['source_epoch_next251'] ?? null, 'source epoch'),
                'next_row_previous_ticket_next255' => $previousTicket,
                'next_row_next_ticket_next255' => is_array($next) ? self::ticket($next['ticket'] ?? null) : null,
                'next_row_previous_acknowledged_next255' => $previousAcknowledged,
                'next_row_current_acknowledged_next255' => $currentAcknowledged,
                'next_row_ready_next255' => $ready,
                'next_row_blocked_reasons_next255' => $blockedReasons,
                'next_row_admission_receipt_next255' => hash('sha256', implode('|', [
                    $ticket,
                    (string) ($previousTicket ?? ''),
                    (string) (is_array($next) ? self::ticket($next['ticket'] ?? null) : ''),
                    $ready ? 'ready' : 'blocked',
                ])),
            ] + $row;
        }

        return $rows;
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
     * @return array<string,mixed>
     */
    private static function summary(array $rows): array
    {
        $summary = [
            'row_count' => count($rows),
            'ready_count' => 0,
            'blocked_count' => 0,
            'current_source_ready_count' => 0,
            'next_source_ready_count' => 0,
            'current_source_blocked_count' => 0,
            'next_source_blocked_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['next_row_ready_next255'] ?? null) === true;
            $epoch = self::stringValue($row['next_row_source_epoch_next255'] ?? null, 'summary epoch');
            $source = str_contains($epoch, 'next') ? 'next_source' : 'current_source';
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$source . '_' . $bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['next_row_rowid_next255'];
            foreach (($row['next_row_blocked_reasons_next255'] ?? []) as $reason) {
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
            throw new \InvalidArgumentException('SQLite row-value next-row admission next255 resume ticket is not ready');
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

    private static function allRetryRowsAcknowledged(array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_contains((string) ($row['next_row_source_epoch_next255'] ?? ''), 'next')
                && ($row['next_row_ready_next255'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function allCurrentRowsAcknowledged(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!str_contains((string) ($row['next_row_source_epoch_next255'] ?? ''), 'next')
                && ($row['next_row_ready_next255'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function digest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value next-row admission next255 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function ticket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value next-row admission next255 ticket is missing');
        }

        return $value;
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value next-row admission next255 {$label} is missing");
        }

        return $value;
    }
}

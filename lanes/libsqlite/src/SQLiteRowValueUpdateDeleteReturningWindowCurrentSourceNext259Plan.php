<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext259Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedCurrentFrameTickets
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next259',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedCurrentFrameTickets = null,
        bool $requirePreviousFrameClose = true,
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

        $rows = self::frameRows(
            $base['next_row_window_rows_next255'],
            $rowIdColumn,
            $acknowledgedCurrentFrameTickets,
            $requirePreviousFrameClose,
        );
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['current_frame_ready_next259'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['current_frame_ready_next259'] ?? null) !== true));
        $resume = self::resume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next259',
            'current_row_frame_admission_next259' => true,
            'current_row_frame_rows_next259' => $rows,
            'current_row_frame_ready_rows_next259' => $readyRows,
            'current_row_frame_blocked_rows_next259' => $blockedRows,
            'current_row_frame_ready_tickets_next259' => array_column($readyRows, 'ticket'),
            'current_row_frame_blocked_tickets_next259' => array_column($blockedRows, 'ticket'),
            'current_row_frame_resume_next259' => $resume,
            'current_row_frame_resume_tickets_next259' => array_column($resume['rows'], 'ticket'),
            'current_row_frame_summary_next259' => self::summary($rows),
            'current_row_frame_fence_next259' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'next_row_ready_count' => count($base['next_row_ready_rows_next255']),
                'next_row_blocked_count' => count($base['next_row_blocked_rows_next255']),
                'frame_mode' => 'RETURNING CURRENT ROW frame closes before following row is visible',
                'require_previous_frame_close' => $requirePreviousFrameClose,
                'row_count' => count($rows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'ready_digest' => self::digest($readyRows),
                'blocked_digest' => self::digest($blockedRows),
                'transition_count' => count(array_filter($rows, static fn (array $row): bool => ($row['current_frame_crosses_source_epoch_next259'] ?? null) === true)),
                'all_current_frames_acknowledged' => self::allCurrentFramesAcknowledged($rows),
                'all_next_frames_acknowledged' => self::allNextFramesAcknowledged($rows),
            ],
            'dependency_closure_next259' => 'no new support component needed; next259 reuses native row-value UPDATE/DELETE RETURNING rows, next251 source handoff, and next255 next-row admission while adding CURRENT ROW frame-close gating for copied WordPress option imports',
            'dependencies_next259' => [
                'sqlite-rowvalue-returning-window-current-row-frame-next259',
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'wordpress-rowvalue-returning-current-row-frame-next259',
            ],
            'non_overlap_next259' => 'adds CURRENT ROW frame-close admission after accepted next255 next-row receipts; avoids next255 next-row admission, next254 receipt validation, next251 source digest handoff, next248 publication cursor sequencing, row-value savepoint variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @param list<string>|null $acknowledgedTickets
     * @return list<array<string,mixed>>
     */
    private static function frameRows(array $windowRows, string $rowIdColumn, ?array $acknowledgedTickets, bool $requirePreviousFrameClose): array
    {
        $acknowledged = $acknowledgedTickets === null
            ? array_column($windowRows, 'ticket')
            : self::ticketSet($acknowledgedTickets);

        $rows = [];
        foreach (array_values($windowRows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value current-row frame next259 rows are malformed');
            }
            $ticket = self::ticket($row['ticket'] ?? null);
            $previous = $windowRows[$index - 1] ?? null;
            $next = $windowRows[$index + 1] ?? null;
            $previousTicket = is_array($previous) ? self::ticket($previous['ticket'] ?? null) : null;
            $nextTicket = is_array($next) ? self::ticket($next['ticket'] ?? null) : null;
            $currentAcknowledged = in_array($ticket, $acknowledged, true);
            $previousAcknowledged = !$requirePreviousFrameClose || $previousTicket === null || in_array($previousTicket, $acknowledged, true);
            $nextRowReady = ($row['next_row_ready_next255'] ?? null) === true;
            $currentEpoch = self::stringValue($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null, 'source epoch');
            $nextEpoch = is_array($next)
                ? self::stringValue($next['next_row_source_epoch_next255'] ?? $next['source_epoch_next251'] ?? null, 'next source epoch')
                : null;
            $crossesEpoch = $nextEpoch !== null && $nextEpoch !== $currentEpoch;

            $blockedReasons = [];
            if (!$nextRowReady) {
                $blockedReasons[] = 'next-row-not-ready-next259';
            }
            if (!$currentAcknowledged) {
                $blockedReasons[] = 'current-row-frame-not-acknowledged-next259';
            }
            if (!$previousAcknowledged) {
                $blockedReasons[] = 'previous-row-frame-not-closed-next259';
            }

            $ready = $blockedReasons === [];
            $rowId = self::rowId($row[$rowIdColumn] ?? $row['next_row_rowid_next255'] ?? $row['option_id'] ?? null, $rowIdColumn);
            $rows[] = [
                'ticket' => $ticket,
                'current_frame_ordinal_next259' => count($rows) + 1,
                'current_frame_rowid_next259' => $rowId,
                'current_frame_source_epoch_next259' => $currentEpoch,
                'current_frame_previous_ticket_next259' => $previousTicket,
                'current_frame_next_ticket_next259' => $nextTicket,
                'current_frame_current_acknowledged_next259' => $currentAcknowledged,
                'current_frame_previous_closed_next259' => $previousAcknowledged,
                'current_frame_next_row_ready_next259' => $nextRowReady,
                'current_frame_crosses_source_epoch_next259' => $crossesEpoch,
                'current_frame_ready_next259' => $ready,
                'current_frame_blocked_reasons_next259' => $blockedReasons,
                'current_frame_receipt_next259' => hash('sha256', implode('|', [
                    $ticket,
                    (string) ($previousTicket ?? ''),
                    (string) ($nextTicket ?? ''),
                    $currentEpoch,
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
            'transition_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['current_frame_ready_next259'] ?? null) === true;
            $source = str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next') ? 'next_source' : 'current_source';
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$source . '_' . $bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['current_frame_rowid_next259'];
            if (($row['current_frame_crosses_source_epoch_next259'] ?? null) === true) {
                $summary['transition_count']++;
            }
            foreach (($row['current_frame_blocked_reasons_next259'] ?? []) as $reason) {
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
            throw new \InvalidArgumentException('SQLite row-value current-row frame next259 resume ticket is not ready');
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

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allCurrentFramesAcknowledged(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next')
                && ($row['current_frame_ready_next259'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allNextFramesAcknowledged(array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next')
                && ($row['current_frame_ready_next259'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value current-row frame next259 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function ticket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value current-row frame next259 ticket must be a non-empty string');
        }

        return $value;
    }

    private static function stringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value current-row frame next259 {$label} must be a non-empty string");
        }

        return $value;
    }
}

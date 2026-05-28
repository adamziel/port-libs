<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext248Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next248',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
        );

        $yieldRows = self::publicationRows($base['yield_phase_tickets_next245'], 'current-yield', $rowIdColumn);
        $retryRows = self::publicationRows($base['retry_phase_tickets_next245'], 'next-retry', $rowIdColumn);
        $gate = $base['yield_current_source_gate_next245'];
        $sequence = self::sequence($yieldRows, $retryRows, (bool) $gate['next_source_exposed']);
        $resume = self::resume($sequence, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next248',
            'publication_barrier_next248' => [
                'savepoint' => $savepoint,
                'current_source_complete' => (bool) $gate['current_source_complete'],
                'next_source_exposed' => (bool) $gate['next_source_exposed'],
                'required_yield_count' => count($yieldRows),
                'acknowledged_yield_count' => count($base['acknowledged_yield_tickets_next245']),
                'retry_row_count' => count($retryRows),
                'published_row_count' => count($sequence),
                'resume_after_ticket' => $resumeAfterTicket,
                'current_source_digest' => self::digest($base['current_source_tables']),
                'next_source_digest' => self::digest($base['next_source_tables']),
                'barrier_token' => self::barrierToken($base, $yieldRows, $retryRows, $gate),
                'blocked_reasons' => self::blockedReasons($gate),
            ],
            'current_publication_rows_next248' => $yieldRows,
            'retry_publication_rows_next248' => $retryRows,
            'publication_sequence_next248' => $sequence,
            'publication_sequence_tickets_next248' => array_column($sequence, 'ticket'),
            'publication_resume_next248' => $resume,
            'publication_resume_tickets_next248' => array_column($resume['rows'], 'ticket'),
            'publication_state_next248' => $gate['next_source_exposed']
                ? 'current-source-yield-complete-next-source-resumable-next248'
                : 'current-source-yield-pending-next-source-held-next248',
            'dependency_closure_next248' => 'no new support component needed; next248 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next245 yield tickets, and current-row window receipts while adding a publication cursor barrier for next-source retry rows',
            'dependencies_next248' => [
                'sqlite-rowvalue-update-delete-returning-window-publication-current-source-next248',
                'sqlite-returning-current-source-publication-cursor-next248',
                'wordpress-rowvalue-returning-window-resume-barrier-next248',
            ],
            'non_overlap_next248' => 'adds resumable publication sequencing after next245 yield-ticket admission; avoids accepted next245 ticket gate, next244 transition windows, next241 current-row frames, next236 receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $tickets
     * @return list<array<string,mixed>>
     */
    private static function publicationRows(array $tickets, string $source, string $rowIdColumn): array
    {
        $rows = [];
        foreach (array_values($tickets) as $index => $ticket) {
            if (!array_key_exists($rowIdColumn, $ticket)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next248 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $ticket[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next248 rowid column {$rowIdColumn} must be int or string");
            }
            $ticketId = self::stringValue($ticket['ticket'] ?? null, 'ticket');
            $rows[] = [
                'publication_ordinal_next248' => $index + 1,
                'source' => $source,
                'ticket' => $ticketId,
                $rowIdColumn => $rowId,
                'option_name' => self::stringValue($ticket['option_name'] ?? null, 'option_name'),
                'status' => $ticket['status'] ?? null,
                'frame_token' => self::stringValue($ticket['frame_token'] ?? null, 'frame_token'),
                'running_bytes' => self::intValue($ticket['running_bytes'] ?? null),
                'following_bytes' => self::intValue($ticket['following_bytes'] ?? null),
                'cursor' => hash('sha256', $source . '|' . $ticketId),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $yieldRows
     * @param list<array<string,mixed>> $retryRows
     * @return list<array<string,mixed>>
     */
    private static function sequence(array $yieldRows, array $retryRows, bool $nextSourceExposed): array
    {
        $sequence = [];
        foreach ($yieldRows as $row) {
            $row['publication_phase_next248'] = 'current-source-yield';
            $row['next_source_visible_next248'] = false;
            $sequence[] = $row;
        }

        if ($nextSourceExposed) {
            foreach ($retryRows as $row) {
                $row['publication_phase_next248'] = 'next-source-retry';
                $row['next_source_visible_next248'] = true;
                $sequence[] = $row;
            }
        }

        foreach ($sequence as $index => $row) {
            $sequence[$index]['sequence_ordinal_next248'] = $index + 1;
        }

        return $sequence;
    }

    /**
     * @param list<array<string,mixed>> $sequence
     * @return array<string,mixed>
     */
    private static function resume(array $sequence, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $sequence,
                'remaining_count' => count($sequence),
                'exhausted' => $sequence === [],
            ];
        }

        $offset = null;
        foreach ($sequence as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }

        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next248 resume ticket is not in the publication sequence');
        }

        $rows = array_slice($sequence, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $rows,
            'remaining_count' => count($rows),
            'exhausted' => $rows === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $yieldRows
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $gate
     */
    private static function barrierToken(array $base, array $yieldRows, array $retryRows, array $gate): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $base['savepoint'] ?? '',
            'yield' => array_column($yieldRows, 'ticket'),
            'retry' => array_column($retryRows, 'ticket'),
            'gate' => [
                'missing' => $gate['missing_tickets'] ?? [],
                'unexpected' => $gate['unexpected_tickets'] ?? [],
                'exposed' => $gate['next_source_exposed'] ?? false,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $gate
     * @return list<string>
     */
    private static function blockedReasons(array $gate): array
    {
        $reasons = [];
        if (($gate['missing_tickets'] ?? []) !== []) {
            $reasons[] = 'missing-current-source-yield-ticket-next248';
        }
        if (($gate['unexpected_tickets'] ?? []) !== []) {
            $reasons[] = 'unexpected-current-source-yield-ticket-next248';
        }

        return $reasons;
    }

    /**
     * @param array<string,mixed> $tables
     */
    private static function digest(array $tables): string
    {
        return hash('sha256', json_encode($tables, JSON_THROW_ON_ERROR));
    }

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next248 {$name} is missing");
        }

        return $value;
    }

    private static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}

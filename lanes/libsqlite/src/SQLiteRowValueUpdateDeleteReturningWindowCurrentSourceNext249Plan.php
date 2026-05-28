<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next249',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
    ): array {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('SQLite row-value returning window next249 chunk size must be positive');
        }

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

        $yieldRows = self::windowRows($base['yield_phase_tickets_next245'], $rowIdColumn);
        $retryRows = self::windowRows($base['retry_phase_tickets_next245'], $rowIdColumn);
        $chunks = self::ackChunks($yieldRows, $chunkSize, $rowIdColumn);
        $resume = self::resumeGate($chunks, $retryRows, $base['yield_current_source_gate_next245'], $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next249',
            'yield_window_rows_next249' => $yieldRows,
            'retry_window_rows_next249' => $retryRows,
            'yield_ack_chunks_next249' => $chunks,
            'yield_resume_gate_next249' => $resume,
            'next_source_resume_token_next249' => $resume['next_source_resume_token'],
            'current_source_yield_complete_next249' => $resume['current_source_yield_complete'],
            'retry_window_exposed_next249' => $resume['retry_window_exposed'],
            'window_yield_sequence_next249' => array_column($yieldRows, 'window_sequence_token'),
            'retry_window_sequence_next249' => array_column($retryRows, 'window_sequence_token'),
            'dependency_closure_next249' => 'no new support component needed; next249 reuses native PHP row-value UPDATE/DELETE RETURNING, next245 yield-ticket gates, and current-row window receipts while adding chunked resume admission for yielded current-source windows',
            'dependencies_next249' => [
                'sqlite-rowvalue-returning-window-chunked-yield-next249',
                'sqlite-returning-current-source-resume-token-next249',
                'wordpress-rowvalue-returning-window-resume-next249',
            ],
            'non_overlap_next249' => 'adds chunked resume-token admission for yielded row-value UPDATE/DELETE RETURNING window rows before retry windows are exposed; avoids accepted next245 yield-ticket gate, next236 current-row frame receipts, next242 row-value/window behavior, UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $tickets
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $tickets, string $rowIdColumn): array
    {
        $rows = [];
        $totalRunning = 0;
        foreach (array_values($tickets) as $index => $ticket) {
            if (!array_key_exists($rowIdColumn, $ticket)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next249 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $ticket[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next249 rowid column {$rowIdColumn} must be int or string");
            }

            $running = self::intValue($ticket['running_bytes'] ?? null);
            $following = self::intValue($ticket['following_bytes'] ?? null);
            $totalRunning += $running;
            $ordinal = $index + 1;
            $phase = (string) ($ticket['phase'] ?? '');
            $name = (string) ($ticket['option_name'] ?? '');
            $frameToken = (string) ($ticket['frame_token'] ?? '');

            $rows[] = [
                'ordinal' => $ordinal,
                'phase' => $phase,
                $rowIdColumn => $rowId,
                'option_name' => $name,
                'status' => $ticket['status'] ?? null,
                'ticket' => (string) ($ticket['ticket'] ?? ''),
                'running_bytes' => $running,
                'following_bytes' => $following,
                'cumulative_running_bytes' => $totalRunning,
                'lag_ticket' => $index === 0 ? null : (string) ($tickets[$index - 1]['ticket'] ?? ''),
                'lead_ticket' => array_key_exists($index + 1, $tickets) ? (string) ($tickets[$index + 1]['ticket'] ?? '') : null,
                'window_sequence_token' => implode('|', [$phase, (string) $ordinal, (string) $rowId, $name, $frameToken]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $yieldRows
     * @return list<array<string,mixed>>
     */
    private static function ackChunks(array $yieldRows, int $chunkSize, string $rowIdColumn): array
    {
        $chunks = [];
        foreach (array_chunk($yieldRows, $chunkSize) as $chunkIndex => $rows) {
            $tickets = array_column($rows, 'ticket');
            $sequence = array_column($rows, 'window_sequence_token');
            $chunks[] = [
                'chunk' => $chunkIndex + 1,
                'first_ordinal' => $rows[0]['ordinal'],
                'last_ordinal' => $rows[count($rows) - 1]['ordinal'],
                'tickets' => $tickets,
                'rowids' => array_column($rows, $rowIdColumn),
                'sequence' => $sequence,
                'resume_token' => hash('sha256', implode("\n", $sequence)),
            ];
        }

        return $chunks;
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $gate
     * @return array<string,mixed>
     */
    private static function resumeGate(array $chunks, array $retryRows, array $gate, string $rowIdColumn): array
    {
        $complete = (bool) ($gate['current_source_complete'] ?? false);
        $missing = $gate['missing_tickets'] ?? [];
        $unexpected = $gate['unexpected_tickets'] ?? [];
        if (!is_array($missing) || !is_array($unexpected)) {
            throw new \InvalidArgumentException('SQLite row-value returning window next249 gate is malformed');
        }

        $chunkTokens = array_column($chunks, 'resume_token');
        $resumeToken = $complete ? hash('sha256', implode('|', $chunkTokens)) : null;

        return [
            'chunk_count' => count($chunks),
            'acknowledged_chunk_count' => $complete ? count($chunks) : 0,
            'held_chunk_count' => $complete ? 0 : count($chunks),
            'missing_tickets' => array_values($missing),
            'unexpected_tickets' => array_values($unexpected),
            'current_source_yield_complete' => $complete,
            'retry_window_exposed' => $complete,
            'retry_rowids_if_exposed' => $complete ? array_column($retryRows, $rowIdColumn) : [],
            'next_source_resume_token' => $resumeToken,
            'resume_boundary' => $complete
                ? 'next-source-retry-window-resumes-after-yield-chunks-next249'
                : 'next-source-retry-window-held-for-yield-chunks-next249',
        ];
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

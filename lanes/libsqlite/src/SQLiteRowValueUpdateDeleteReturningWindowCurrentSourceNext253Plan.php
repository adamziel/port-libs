<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedChunkTokens
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next253',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $chunkSize,
        );

        $chunkTokens = array_column($base['yield_ack_chunks_next249'], 'resume_token');
        $acknowledged = $acknowledgedChunkTokens ?? $chunkTokens;
        $gate = self::chunkGate($chunkTokens, $acknowledged);
        $yieldTicketsComplete = (bool) $base['current_source_yield_complete_next249'];
        $retryExposed = $yieldTicketsComplete && (bool) $gate['chunk_source_complete'];
        $gate['yield_tickets_complete'] = $yieldTicketsComplete;
        $gate['next_source_retry_exposed'] = $retryExposed;
        $gate['source_boundary'] = $retryExposed
            ? 'current-source-window-chunks-complete-next253'
            : 'next-source-retry-held-for-current-window-chunks-next253';
        $cursorRows = self::cursorRows(
            $base['yield_ack_chunks_next249'],
            $base['retry_window_rows_next249'],
            $yieldTicketsComplete,
            (bool) $gate['chunk_source_complete'],
            $rowIdColumn,
        );

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next253',
            'window_current_source_chunk_gate_next253' => $gate,
            'current_source_window_chunks_next253' => $base['yield_ack_chunks_next249'],
            'acknowledged_window_chunk_tokens_next253' => $acknowledged,
            'required_window_chunk_tokens_next253' => $chunkTokens,
            'window_current_source_cursor_next253' => $cursorRows,
            'window_current_source_cursor_tokens_next253' => array_column($cursorRows, 'cursor_token'),
            'window_current_source_cursor_rowids_next253' => array_column($cursorRows, $rowIdColumn),
            'window_current_source_retry_exposed_next253' => $retryExposed,
            'window_current_source_retry_rowids_next253' => $retryExposed
                ? array_column($base['retry_window_rows_next249'], $rowIdColumn)
                : [],
            'window_current_source_release_token_next253' => $retryExposed
                ? self::releaseToken($savepoint, $chunkTokens, $base['retry_window_sequence_next249'])
                : null,
            'dependency_closure_next253' => 'no new support component needed; next253 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next249 chunked current-source windows, and retry window rows while adding chunk-token source admission before next-source retry publication',
            'dependencies_next253' => [
                'sqlite-rowvalue-returning-window-current-source-chunk-gate-next253',
                'sqlite-returning-next-source-held-for-window-chunk-receipts-next253',
                'wordpress-rowvalue-returning-window-current-source-next253',
            ],
            'non_overlap_next253' => 'adds chunk-token current-source admission above accepted next249 chunk construction and next245 raw yield-ticket admission; avoids next248 publication cursor, next236 current-row frame receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function chunkGate(array $required, array $acknowledged): array
    {
        $requiredSet = array_fill_keys($required, true);
        $ackSet = array_fill_keys($acknowledged, true);
        $missing = [];
        foreach ($required as $token) {
            if (!isset($ackSet[$token])) {
                $missing[] = $token;
            }
        }

        $unexpected = [];
        foreach ($acknowledged as $token) {
            if (!isset($requiredSet[$token])) {
                $unexpected[] = $token;
            }
        }

        $complete = $missing === [] && $unexpected === [];

        return [
            'required_chunk_count' => count($required),
            'acknowledged_chunk_count' => count($acknowledged),
            'missing_chunk_tokens' => $missing,
            'unexpected_chunk_tokens' => $unexpected,
            'chunk_source_complete' => $complete,
            'next_source_retry_exposed' => $complete,
            'source_boundary' => $complete
                ? 'current-source-window-chunks-complete-next253'
                : 'next-source-retry-held-for-current-window-chunks-next253',
        ];
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @param list<array<string,mixed>> $retryRows
     * @return list<array<string,mixed>>
     */
    private static function cursorRows(
        array $chunks,
        array $retryRows,
        bool $yieldTicketsComplete,
        bool $chunkSourceComplete,
        string $rowIdColumn,
    ): array {
        $rows = [];
        foreach ($chunks as $chunk) {
            $rowids = $chunk['rowids'] ?? [];
            if (!is_array($rowids)) {
                throw new \InvalidArgumentException('SQLite row-value returning window next253 chunk rowids are malformed');
            }
            foreach (array_values($rowids) as $offset => $rowId) {
                if (!is_int($rowId) && !is_string($rowId)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next253 rowid column {$rowIdColumn} must be int or string");
                }
                $rows[] = [
                    'source' => 'current-window-chunk-next253',
                    'chunk' => $chunk['chunk'],
                    'chunk_complete' => $yieldTicketsComplete && $chunkSourceComplete,
                    'ordinal_in_chunk' => $offset + 1,
                    $rowIdColumn => $rowId,
                    'resume_token' => $chunk['resume_token'],
                    'cursor_token' => hash('sha256', 'current|' . (string) $chunk['resume_token'] . '|' . (string) $rowId),
                ];
            }
        }

        if (!$yieldTicketsComplete || !$chunkSourceComplete) {
            return $rows;
        }

        foreach (array_values($retryRows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next253 retry rowid column {$rowIdColumn} is missing");
            }
            $rowId = $row[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next253 retry rowid column {$rowIdColumn} must be int or string");
            }
            $rows[] = [
                'source' => 'next-source-retry-window-next253',
                'chunk' => null,
                'chunk_complete' => true,
                'ordinal_in_chunk' => $index + 1,
                $rowIdColumn => $rowId,
                'resume_token' => null,
                'cursor_token' => hash('sha256', 'retry|' . (string) ($row['window_sequence_token'] ?? '') . '|' . (string) $rowId),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $chunkTokens
     * @param list<string> $retrySequence
     */
    private static function releaseToken(string $savepoint, array $chunkTokens, array $retrySequence): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'chunks' => $chunkTokens,
            'retry' => $retrySequence,
        ], JSON_THROW_ON_ERROR));
    }
}

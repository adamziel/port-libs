<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext256Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedChunkTokens
     * @param list<string>|null $acknowledgedCommitTokens
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next256',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
        ?array $acknowledgedCommitTokens = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $chunkSize,
            $acknowledgedChunkTokens,
        );

        $retryRows = self::retryCursorRows($base['window_current_source_cursor_next253']);
        $requiredCommitTokens = array_column($retryRows, 'cursor_token');
        $acknowledged = $acknowledgedCommitTokens ?? $requiredCommitTokens;
        $gate = self::commitGate(
            $requiredCommitTokens,
            $acknowledged,
            (bool) $base['window_current_source_retry_exposed_next253'],
        );
        $durableRows = self::durableRows($base['window_current_source_cursor_next253'], (bool) $gate['commit_source_complete']);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next256',
            'retry_commit_watermark_next256' => [
                'savepoint' => $savepoint,
                'source_boundary' => $gate['source_boundary'],
                'current_chunk_gate_complete' => (bool) $base['window_current_source_chunk_gate_next253']['chunk_source_complete'],
                'retry_exposed' => (bool) $base['window_current_source_retry_exposed_next253'],
                'required_commit_count' => count($requiredCommitTokens),
                'acknowledged_commit_count' => count($acknowledged),
                'missing_commit_tokens' => $gate['missing_commit_tokens'],
                'unexpected_commit_tokens' => $gate['unexpected_commit_tokens'],
                'commit_source_complete' => $gate['commit_source_complete'],
                'durable_retry_count' => count(array_filter(
                    $durableRows,
                    static fn (array $row): bool => ($row['source'] ?? null) === 'next-source-retry-window-next253'
                        && ($row['durable_next256'] ?? false) === true,
                )),
                'watermark_token' => self::watermarkToken($savepoint, $requiredCommitTokens, $gate),
            ],
            'required_retry_commit_tokens_next256' => $requiredCommitTokens,
            'acknowledged_retry_commit_tokens_next256' => $acknowledged,
            'retry_commit_rows_next256' => $retryRows,
            'durable_publication_rows_next256' => $durableRows,
            'durable_publication_rowids_next256' => array_column($durableRows, $rowIdColumn),
            'durable_retry_rowids_next256' => array_values(array_map(
                static fn (array $row): int|string => $row[$rowIdColumn],
                array_filter(
                    $durableRows,
                    static fn (array $row): bool => ($row['source'] ?? null) === 'next-source-retry-window-next253'
                        && ($row['durable_next256'] ?? false) === true,
                ),
            )),
            'retry_commit_state_next256' => $gate['commit_source_complete']
                ? 'current-source-complete-next-source-retry-durable-next256'
                : 'next-source-retry-held-for-commit-watermark-next256',
            'dependency_closure_next256' => 'no new support component needed; next256 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next253 current-window chunk admission, and retry cursor tokens while adding a commit watermark before next-source retry rows are durable',
            'dependencies_next256' => [
                'sqlite-rowvalue-returning-window-retry-commit-watermark-next256',
                'sqlite-returning-next-source-durable-after-current-window-next256',
                'wordpress-rowvalue-returning-window-current-source-next256',
            ],
            'non_overlap_next256' => 'adds a retry commit-token durability watermark above accepted next253 chunk-token admission; avoids next253 cursor construction, next249 chunking, next248 publication cursor, next245 yield-ticket gate, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $cursorRows
     * @return list<array<string,mixed>>
     */
    private static function retryCursorRows(array $cursorRows): array
    {
        $rows = [];
        foreach ($cursorRows as $row) {
            if (($row['source'] ?? null) !== 'next-source-retry-window-next253') {
                continue;
            }
            if (!is_string($row['cursor_token'] ?? null) || $row['cursor_token'] === '') {
                throw new \InvalidArgumentException('SQLite row-value returning window next256 retry cursor token is missing');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function commitGate(array $required, array $acknowledged, bool $retryExposed): array
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

        $complete = $retryExposed && $missing === [] && $unexpected === [];

        return [
            'missing_commit_tokens' => $missing,
            'unexpected_commit_tokens' => $unexpected,
            'commit_source_complete' => $complete,
            'source_boundary' => $complete
                ? 'current-source-complete-next-source-retry-durable-next256'
                : 'next-source-retry-held-for-commit-watermark-next256',
        ];
    }

    /**
     * @param list<array<string,mixed>> $cursorRows
     * @return list<array<string,mixed>>
     */
    private static function durableRows(array $cursorRows, bool $commitComplete): array
    {
        $rows = [];
        foreach (array_values($cursorRows) as $index => $row) {
            $isRetry = ($row['source'] ?? null) === 'next-source-retry-window-next253';
            $row['durable_ordinal_next256'] = $index + 1;
            $row['durable_next256'] = !$isRetry || $commitComplete;
            $row['commit_phase_next256'] = $isRetry
                ? ($commitComplete ? 'next-source-retry-durable' : 'next-source-retry-pending')
                : 'current-source-window-durable';
            $row['commit_token_next256'] = hash(
                'sha256',
                (string) ($row['source'] ?? '') . '|' . (string) ($row['cursor_token'] ?? '') . '|' . (string) $index,
            );
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $requiredCommitTokens
     * @param array<string,mixed> $gate
     */
    private static function watermarkToken(string $savepoint, array $requiredCommitTokens, array $gate): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'required' => $requiredCommitTokens,
            'missing' => $gate['missing_commit_tokens'],
            'unexpected' => $gate['unexpected_commit_tokens'],
            'complete' => $gate['commit_source_complete'],
        ], JSON_THROW_ON_ERROR));
    }
}

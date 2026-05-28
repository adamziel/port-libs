<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next257',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
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

        $yield = self::phaseDeleteRows($tables, $yieldStatements, $uniqueConstraints, $rowIdColumn, 'current-source-yield-next257');
        $afterYield = $yield['tables'];
        $attempt = self::phaseDeleteRows($afterYield, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'suppressed-attempt-next257');
        $retry = self::phaseDeleteRows($afterYield, $retryStatements, $uniqueConstraints, $rowIdColumn, 'next-source-retry-next257');

        $currentTombstones = $yield['tombstones'];
        $retryTombstones = $retry['tombstones'];
        $suppressedTombstones = $attempt['tombstones'];
        $gate = self::tombstoneGate($base, $currentTombstones, $retryTombstones, $rowIdColumn);
        $stream = self::publicationStream(
            $base,
            $currentTombstones,
            $retryTombstones,
            $suppressedTombstones,
            (bool) $gate['next_source_retry_tombstones_exposed'],
            $rowIdColumn,
        );

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next257',
            'delete_returning_tombstone_gate_next257' => $gate,
            'current_source_delete_tombstones_next257' => $currentTombstones,
            'suppressed_attempt_delete_tombstones_next257' => $suppressedTombstones,
            'next_source_retry_delete_tombstones_next257' => $gate['next_source_retry_tombstones_exposed'] ? $retryTombstones : [],
            'held_next_source_retry_delete_tombstones_next257' => $gate['next_source_retry_tombstones_exposed'] ? [] : $retryTombstones,
            'delete_returning_publication_stream_next257' => $stream,
            'delete_returning_publication_rowids_next257' => array_column($stream, $rowIdColumn),
            'delete_returning_publication_sources_next257' => array_column($stream, 'source'),
            'delete_returning_publication_tokens_next257' => array_column($stream, 'publication_token'),
            'delete_returning_release_token_next257' => $gate['next_source_retry_tombstones_exposed']
                ? self::releaseToken($savepoint, $currentTombstones, $retryTombstones, $base['window_current_source_release_token_next253'])
                : null,
            'dependency_closure_next257' => 'no new support component needed; next257 reuses native PHP UPDATE/DELETE RETURNING execution and next253 current-source window chunk admission while adding DELETE RETURNING tombstone ordering before next-source retry publication',
            'dependencies_next257' => [
                'sqlite-rowvalue-delete-returning-current-source-tombstone-gate-next257',
                'sqlite-returning-delete-tombstones-before-next-source-retry-next257',
                'wordpress-rowvalue-returning-window-current-source-next257',
            ],
            'non_overlap_next257' => 'adds DELETE RETURNING tombstone ordering over accepted next253 chunk-token admission; avoids next253 chunk construction, next252/next251 source fences, next248 publication cursor, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,tombstones:list<array<string,mixed>>}
     */
    private static function phaseDeleteRows(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $working = $tables;
        $tombstones = [];

        foreach ($statements as $statementIndex => $sql) {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $working, $rowIdColumn, $uniqueConstraints);
            if ($parsed['action'] === 'delete') {
                foreach ($result['returning'] as $rowIndex => $row) {
                    if (!array_key_exists($rowIdColumn, $row)) {
                        throw new \InvalidArgumentException("SQLite row-value returning window next257 delete rowid column {$rowIdColumn} is missing");
                    }
                    $rowId = $row[$rowIdColumn];
                    if (!is_int($rowId) && !is_string($rowId)) {
                        throw new \InvalidArgumentException("SQLite row-value returning window next257 delete rowid column {$rowIdColumn} must be int or string");
                    }
                    $tombstones[] = array_merge($row, [
                        'source' => $phase,
                        'statement_ordinal_next257' => $statementIndex + 1,
                        'delete_ordinal_next257' => count($tombstones) + 1,
                        'tombstone_token_next257' => hash('sha256', $phase . '|' . (string) ($statementIndex + 1) . '|' . (string) $rowId . '|' . (string) $rowIndex),
                    ]);
                }
            }
            $working = $result['tables'];
        }

        return [
            'tables' => $working,
            'tombstones' => $tombstones,
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     * @return array<string,mixed>
     */
    private static function tombstoneGate(array $base, array $currentTombstones, array $retryTombstones, string $rowIdColumn): array
    {
        $chunkGate = $base['window_current_source_chunk_gate_next253'] ?? [];
        $currentComplete = (bool) ($chunkGate['yield_tickets_complete'] ?? false)
            && (bool) ($chunkGate['chunk_source_complete'] ?? false);
        $retryReady = $currentComplete && (bool) ($base['window_current_source_retry_exposed_next253'] ?? false);

        return [
            'savepoint' => $base['savepoint'] ?? null,
            'current_source_delete_count' => count($currentTombstones),
            'next_source_retry_delete_count' => count($retryTombstones),
            'current_source_delete_rowids' => array_column($currentTombstones, $rowIdColumn),
            'next_source_retry_delete_rowids' => array_column($retryTombstones, $rowIdColumn),
            'current_source_tombstones_complete' => $currentComplete,
            'next_source_retry_tombstones_exposed' => $retryReady,
            'blocked_reasons' => $retryReady ? [] : self::blockedReasons($chunkGate),
            'source_boundary' => $retryReady
                ? 'current-source-delete-tombstones-before-next-source-retry-next257'
                : 'next-source-delete-tombstones-held-for-current-source-next257',
        ];
    }

    /**
     * @param array<string,mixed> $chunkGate
     * @return list<string>
     */
    private static function blockedReasons(array $chunkGate): array
    {
        $reasons = [];
        if (!($chunkGate['yield_tickets_complete'] ?? false)) {
            $reasons[] = 'current-source-yield-tickets-incomplete-next257';
        }
        if (!($chunkGate['chunk_source_complete'] ?? false)) {
            $reasons[] = 'current-source-window-chunks-incomplete-next257';
        }

        return $reasons === [] ? ['next-source-retry-window-held-next257'] : $reasons;
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     * @param list<array<string,mixed>> $suppressedTombstones
     * @return list<array<string,mixed>>
     */
    private static function publicationStream(
        array $base,
        array $currentTombstones,
        array $retryTombstones,
        array $suppressedTombstones,
        bool $retryExposed,
        string $rowIdColumn,
    ): array {
        $rows = [];
        foreach ($currentTombstones as $row) {
            $rows[] = self::streamRow($row, 'current-delete-returning-next257', count($rows) + 1, true, $rowIdColumn);
        }
        foreach ($suppressedTombstones as $row) {
            $rows[] = self::streamRow($row, 'suppressed-attempt-delete-returning-next257', count($rows) + 1, false, $rowIdColumn);
        }
        if (!$retryExposed) {
            return $rows;
        }
        foreach ($retryTombstones as $row) {
            $rows[] = self::streamRow($row, 'next-source-retry-delete-returning-next257', count($rows) + 1, true, $rowIdColumn);
        }

        foreach ($base['window_current_source_cursor_next253'] ?? [] as $cursorRow) {
            if (($cursorRow['source'] ?? null) !== 'next-source-retry-window-next253') {
                continue;
            }
            $rowId = $cursorRow[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                continue;
            }
            $rows[] = [
                'source' => 'next-source-retry-window-row-next257',
                'visible' => true,
                'publication_ordinal_next257' => count($rows) + 1,
                $rowIdColumn => $rowId,
                'option_name' => $cursorRow['option_name'] ?? null,
                'publication_token' => hash('sha256', 'retry-window|' . (string) ($cursorRow['cursor_token'] ?? '') . '|' . (string) $rowId),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function streamRow(array $row, string $source, int $ordinal, bool $visible, string $rowIdColumn): array
    {
        $rowId = $row[$rowIdColumn] ?? null;
        if (!is_int($rowId) && !is_string($rowId)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next257 stream rowid column {$rowIdColumn} must be int or string");
        }

        return array_merge($row, [
            'source' => $source,
            'visible' => $visible,
            'publication_ordinal_next257' => $ordinal,
            'publication_token' => hash('sha256', $source . '|' . (string) $ordinal . '|' . (string) $rowId . '|' . (string) ($row['tombstone_token_next257'] ?? '')),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     */
    private static function releaseToken(string $savepoint, array $currentTombstones, array $retryTombstones, mixed $chunkReleaseToken): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'chunkReleaseToken' => $chunkReleaseToken,
            'current' => array_column($currentTombstones, 'tombstone_token_next257'),
            'retry' => array_column($retryTombstones, 'tombstone_token_next257'),
        ], JSON_THROW_ON_ERROR));
    }
}

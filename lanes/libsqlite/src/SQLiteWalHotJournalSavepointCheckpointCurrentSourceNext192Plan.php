<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext192Plan
{
    /**
     * @param array<string,mixed> $basePlan
     * @param list<int> $checkpointPages
     * @param list<array{name:string,root_pages?:list<int>,observed_page_digests?:array<int,string>,closed?:bool,dirty?:bool}> $statements
     * @param list<array{name:string,pinned_pages?:list<int>,observed_page_digests?:array<int,string>,closed?:bool}> $readers
     * @return array<string,mixed>
     */
    public static function plan(
        array $basePlan,
        string $preCheckpointDatabaseBytes,
        string $checkpointedDatabaseBytes,
        SQLiteWal $currentWal,
        array $checkpointPages,
        array $statements,
        array $readers
    ): array {
        if (($basePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next188') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 requires an admitted next188 base plan');
        }
        if ($checkpointPages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 requires checkpoint pages');
        }
        if ($statements === [] || $readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 requires statement and reader rows');
        }

        $pageSize = (int) ($basePlan['page_size'] ?? $currentWal->header->pageSize);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 page size must be a power of two at least 512');
        }
        if (strlen($preCheckpointDatabaseBytes) % $pageSize !== 0 || strlen($checkpointedDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 database images must be page-size aligned');
        }

        $pageRows = self::checkpointPageRows($preCheckpointDatabaseBytes, $checkpointedDatabaseBytes, $currentWal, $checkpointPages, $pageSize);
        $mismatchedPages = array_values(array_map(
            static fn (array $row): int => $row['page_number'],
            array_filter($pageRows, static fn (array $row): bool => !$row['matches'])
        ));
        $checkpointDigests = [];
        foreach ($pageRows as $row) {
            $checkpointDigests[$row['page_number']] = $row['actual_digest'];
        }

        $statementRows = [];
        $admittedStatements = [];
        $reprepareStatements = [];
        foreach ($statements as $statement) {
            $row = self::cacheDecision('statement', $statement, $checkpointDigests, $mismatchedPages);
            $statementRows[] = $row;
            if ($row['admitted']) {
                $admittedStatements[] = $row['name'];
            } else {
                $reprepareStatements[] = $row['name'];
            }
        }

        $readerRows = [];
        $admittedReaders = [];
        $reopenReaders = [];
        foreach ($readers as $reader) {
            $row = self::cacheDecision('reader', $reader, $checkpointDigests, $mismatchedPages);
            $readerRows[] = $row;
            if ($row['admitted']) {
                $admittedReaders[] = $row['name'];
            } else {
                $reopenReaders[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'base_commit_hook_current_source',
                'matched' => ($basePlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next188',
                'reason' => 'next188 commit-hook admission must pass before checkpoint image publication',
            ],
            [
                'name' => 'checkpoint_pages_materialized',
                'matched' => $mismatchedPages === [],
                'reason' => 'all checkpointed pages must contain the committed current WAL page images',
            ],
            [
                'name' => 'statement_digest_mix',
                'matched' => $admittedStatements !== [] && $reprepareStatements !== [],
                'reason' => 'statement cache keeps matching page digests and reprepares stale page digests',
            ],
            [
                'name' => 'reader_digest_mix',
                'matched' => $admittedReaders !== [] && $reopenReaders !== [],
                'reason' => 'reader cache keeps matching page digests and reopens stale page digests',
            ],
        ];
        $staleGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $status = $staleGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next192'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next192';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next192'
                ? 'checkpointed_database_pages_match_committed_wal_frames_before_current_source_reuse'
                : 'checkpointed_database_page_images_block_current_source_reuse',
            'database_path' => $basePlan['database_path'] ?? null,
            'journal_path' => $basePlan['journal_path'] ?? null,
            'wal_path' => $basePlan['wal_path'] ?? null,
            'page_size' => $pageSize,
            'base_status' => $basePlan['status'],
            'base_hook_digest' => $basePlan['hook_digest'] ?? null,
            'checkpoint_pages' => array_values($checkpointPages),
            'checkpoint_page_rows' => $pageRows,
            'checkpoint_page_digests' => $checkpointDigests,
            'mismatched_checkpoint_pages' => $mismatchedPages,
            'statement_rows' => $statementRows,
            'reader_rows' => $readerRows,
            'admitted_statement_names' => $admittedStatements,
            'reprepare_statement_names' => $reprepareStatements,
            'admitted_reader_names' => $admittedReaders,
            'reopen_reader_names' => $reopenReaders,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => $staleGuards,
            'operation_names' => array_values(array_merge(
                $basePlan['operation_names'] ?? [],
                ['verify_checkpoint_page_images_current_source_next192'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_checkpoint_page_digest_current_source_next192'
                        : 'reprepare_checkpoint_page_digest_current_source_next192',
                    $statementRows
                ),
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_reader_checkpoint_page_digest_next192'
                        : 'reopen_reader_checkpoint_page_digest_next192',
                    $readerRows
                ),
                ['publish_checkpoint_page_image_current_source_next192']
            )),
            'page_image_digest' => hash('sha256', implode('|', array_merge(
                [(string) ($basePlan['hook_digest'] ?? ''), hash('sha256', $checkpointedDatabaseBytes)],
                array_map(static fn (array $row): string => $row['page_number'] . ':' . $row['expected_digest'] . ':' . $row['actual_digest'], $pageRows),
                array_column($statementRows, 'digest_transition'),
                array_column($readerRows, 'digest_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($basePlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next192',
                'sqlite-wal-checkpoint-page-image-publication',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses the accepted WAL parser, page-image reader snapshots, and next188 current-source commit-hook admission',
            'non_overlap' => 'next192 verifies checkpointed database page images against committed WAL frames before reusing current-source readers; it does not repeat next188 hook checks, next185 salt/sequence generation, WAL byte truncation, rollback-journal apply, VFS savepoint rollback, or checkpoint transaction planning',
        ];
    }

    /**
     * @param list<int> $checkpointPages
     * @return list<array<string,mixed>>
     */
    private static function checkpointPageRows(string $preCheckpointDatabaseBytes, string $checkpointedDatabaseBytes, SQLiteWal $currentWal, array $checkpointPages, int $pageSize): array
    {
        $rows = [];
        $seen = [];
        foreach ($checkpointPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next192 checkpoint pages must be one-based integers');
            }
            if (isset($seen[$pageNumber])) {
                continue;
            }
            $seen[$pageNumber] = true;
            $expected = $currentWal->readerSnapshotPageImage($preCheckpointDatabaseBytes, $pageNumber);
            $offset = ($pageNumber - 1) * $pageSize;
            $actual = substr($checkpointedDatabaseBytes, $offset, $pageSize);
            if (strlen($actual) !== $pageSize) {
                throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint current-source next192 checkpoint page {$pageNumber} is missing from the database image");
            }
            $expectedDigest = hash('sha256', $expected['image']);
            $actualDigest = hash('sha256', $actual);
            $rows[] = [
                'page_number' => $pageNumber,
                'source' => $expected['source'],
                'frame_index' => $expected['frame_index'],
                'database_offset' => $offset,
                'expected_digest' => $expectedDigest,
                'actual_digest' => $actualDigest,
                'matches' => $expectedDigest === $actualDigest,
                'expected_prefix' => rtrim(substr($expected['image'], 0, 48), '.'),
                'actual_prefix' => rtrim(substr($actual, 0, 48), '.'),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $checkpointDigests
     * @param list<int> $mismatchedPages
     * @return array<string,mixed>
     */
    private static function cacheDecision(string $kind, array $row, array $checkpointDigests, array $mismatchedPages): array
    {
        $name = $row['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next192 {$kind} name is required");
        }
        $pages = $kind === 'statement' ? ($row['root_pages'] ?? []) : ($row['pinned_pages'] ?? []);
        if (!is_array($pages) || $pages === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next192 {$kind} pages are required");
        }
        $observed = $row['observed_page_digests'] ?? null;
        if (!is_array($observed)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next192 {$kind} observed page digests are required");
        }

        $admitted = empty($row['closed']) && empty($row['dirty']);
        $reason = $admitted ? "{$kind}_checkpoint_page_images_match_current_source" : "{$kind}_closed_or_dirty_before_checkpoint_publication";
        $pageRows = [];
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next192 {$kind} pages must be one-based integers");
            }
            $expectedDigest = $checkpointDigests[$pageNumber] ?? null;
            $observedDigest = $observed[$pageNumber] ?? null;
            if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next192 {$kind} observed page digest is malformed");
            }
            $pageRows[] = [
                'page_number' => $pageNumber,
                'expected_digest' => $expectedDigest,
                'observed_digest' => $observedDigest,
                'matches' => $expectedDigest !== null && $expectedDigest === $observedDigest,
                'checkpoint_page_mismatch' => in_array($pageNumber, $mismatchedPages, true),
            ];
            if ($expectedDigest === null) {
                $admitted = false;
                $reason = "{$kind}_page_not_in_checkpoint_publication";
            } elseif (in_array($pageNumber, $mismatchedPages, true)) {
                $admitted = false;
                $reason = "{$kind}_checkpoint_page_image_not_materialized";
            } elseif ($observedDigest !== $expectedDigest) {
                $admitted = false;
                $reason = "{$kind}_observed_page_digest_predates_checkpoint";
            }
        }

        return array_merge($row, [
            'admitted' => $admitted,
            'digest_reason' => $reason,
            'page_rows' => $pageRows,
            'digest_transition' => $name . '>' . ($admitted ? 'retain-checkpoint-pages' : 'reprepare-checkpoint-pages'),
        ]);
    }
}

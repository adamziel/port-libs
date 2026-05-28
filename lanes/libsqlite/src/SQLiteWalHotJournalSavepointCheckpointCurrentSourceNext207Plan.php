<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext207Plan
{
    /**
     * @param array<string,mixed> $consumerPlan
     * @param list<array{name:string,consumer_name:string,cursor_generation:int,commit_generation:int,write_lock_token:string,root_pages:list<int>,observed_database_digest:string,observed_wal_digest:string,observed_page_digests:array<int,string>,read_only?:bool,pending_savepoint_depth?:int,hot_journal_digest?:?string,dirty_reader_cache?:bool}> $writeCursors
     * @return array<string,mixed>
     */
    public static function plan(array $consumerPlan, array $writeCursors, string $expectedWriteLockToken, int $minimumCommitGeneration): array
    {
        if (($consumerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next206') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 requires an admitted next206 consumer plan');
        }
        if ($writeCursors === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 requires write cursor rows');
        }
        if ($expectedWriteLockToken === '' || $minimumCommitGeneration < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 requires a write lock token and non-negative commit generation');
        }

        $databaseDigest = self::digestString($consumerPlan, 'checkpointed_database_digest');
        $walDigest = self::digestString($consumerPlan, 'expected_wal_digest');
        $pageDigests = $consumerPlan['expected_page_digests'] ?? null;
        if (!is_array($pageDigests) || $pageDigests === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 requires checkpoint page digests');
        }
        $admittedConsumers = $consumerPlan['admitted_consumer_names'] ?? null;
        if (!is_array($admittedConsumers)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 requires admitted consumer names');
        }

        $cursorRows = [];
        $admitted = [];
        $blocked = [];
        foreach ($writeCursors as $cursor) {
            $row = self::cursorDecision(
                $cursor,
                array_values($admittedConsumers),
                $databaseDigest,
                $walDigest,
                $pageDigests,
                $expectedWriteLockToken,
                $minimumCommitGeneration
            );
            $cursorRows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $blocked[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'next206_statement_generation_fence',
                'matched' => ($consumerPlan['blocked_guard_names'] ?? []) === [],
                'reason' => 'next206 must admit a clean statement-generation fence before write cursors commit',
            ],
            [
                'name' => 'write_cursor_commit_mix',
                'matched' => $admitted !== [] && $blocked !== [],
                'reason' => 'current write cursors are admitted while stale cursors are blocked for reprepare',
            ],
            [
                'name' => 'exclusive_write_lock_token',
                'matched' => self::admittedLockTokensMatch($cursorRows, $expectedWriteLockToken),
                'reason' => 'every admitted write cursor must observe the exclusive WAL write-lock token',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        $status = $blockedGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next207'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next207';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next207'
                ? 'write_cursors_match_checkpoint_generation_and_exclusive_lock'
                : 'write_cursors_wait_for_checkpoint_generation_or_lock_reprepare',
            'base_status' => $consumerPlan['status'],
            'database_path' => $consumerPlan['database_path'] ?? null,
            'journal_path' => $consumerPlan['journal_path'] ?? null,
            'wal_path' => $consumerPlan['wal_path'] ?? null,
            'page_size' => $consumerPlan['page_size'] ?? null,
            'minimum_commit_generation' => $minimumCommitGeneration,
            'expected_write_lock_token' => $expectedWriteLockToken,
            'checkpointed_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_page_digests' => $pageDigests,
            'cursor_rows' => $cursorRows,
            'admitted_cursor_names' => $admitted,
            'blocked_cursor_names' => $blocked,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_merge(
                $consumerPlan['operation_names'] ?? [],
                ['verify_write_cursor_generation_current_source_next207'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'admit_write_cursor_current_source_next207'
                        : 'block_stale_write_cursor_current_source_next207',
                    $cursorRows
                )
            )),
            'cursor_digest' => hash('sha256', implode('|', array_merge(
                [$databaseDigest, $walDigest, $expectedWriteLockToken, (string) $minimumCommitGeneration],
                array_column($cursorRows, 'cursor_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($consumerPlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next207',
                'sqlite-wal-write-cursor-generation-fence',
                'wordpress-import-write-cursor-reprepare',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses next206 checkpoint generation, page digests, and existing WAL/VFS lock evidence to fence write cursor admission',
            'non_overlap' => 'next207 validates write-cursor commit admission after next206 statement reprepare; it does not repeat WAL byte truncation, rollback-journal apply/commit, VFS savepoint rollback, checkpoint transaction planning, WAL sidecar lease checks, or next206 prepared-statement generation quarantine',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || strlen($value) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $cursor
     * @param list<string> $admittedConsumers
     * @param array<int,string> $pageDigests
     * @return array<string,mixed>
     */
    private static function cursorDecision(
        array $cursor,
        array $admittedConsumers,
        string $databaseDigest,
        string $walDigest,
        array $pageDigests,
        string $expectedWriteLockToken,
        int $minimumCommitGeneration
    ): array {
        $name = $cursor['name'] ?? null;
        $consumerName = $cursor['consumer_name'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($consumerName) || $consumerName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next207 cursor and consumer names are required');
        }
        $cursorGeneration = $cursor['cursor_generation'] ?? null;
        $commitGeneration = $cursor['commit_generation'] ?? null;
        if (!is_int($cursorGeneration) || $cursorGeneration < 0 || !is_int($commitGeneration) || $commitGeneration < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} generations must be non-negative integers");
        }
        $lockToken = $cursor['write_lock_token'] ?? null;
        if (!is_string($lockToken) || $lockToken === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} write lock token is required");
        }
        $observedDatabaseDigest = $cursor['observed_database_digest'] ?? null;
        $observedWalDigest = $cursor['observed_wal_digest'] ?? null;
        if (!is_string($observedDatabaseDigest) || strlen($observedDatabaseDigest) !== 64 || !is_string($observedWalDigest) || strlen($observedWalDigest) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} observed digests are required");
        }
        $rootPages = $cursor['root_pages'] ?? null;
        $observedPageDigests = $cursor['observed_page_digests'] ?? null;
        if (!is_array($rootPages) || $rootPages === [] || !is_array($observedPageDigests) || $observedPageDigests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} page rows are required");
        }

        $pageRows = [];
        $stalePages = [];
        $missingPages = [];
        foreach ($rootPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} root page must be positive");
            }
            $expectedDigest = $pageDigests[$pageNumber] ?? null;
            if (!is_string($expectedDigest)) {
                $missingPages[] = $pageNumber;
                $pageRows[] = [
                    'page' => $pageNumber,
                    'matched' => false,
                    'reason' => 'write_cursor_page_outside_checkpoint_generation',
                    'expected_digest' => null,
                    'observed_digest' => $observedPageDigests[$pageNumber] ?? null,
                ];
                continue;
            }
            $observedDigest = $observedPageDigests[$pageNumber] ?? null;
            if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} page digest is required");
            }
            $matched = hash_equals($expectedDigest, $observedDigest);
            if (!$matched) {
                $stalePages[] = $pageNumber;
            }
            $pageRows[] = [
                'page' => $pageNumber,
                'matched' => $matched,
                'reason' => $matched ? 'write_cursor_checkpoint_page_matches' : 'write_cursor_checkpoint_page_stale',
                'expected_digest' => $expectedDigest,
                'observed_digest' => $observedDigest,
            ];
        }

        $hotJournalDigest = $cursor['hot_journal_digest'] ?? null;
        if ($hotJournalDigest !== null && (!is_string($hotJournalDigest) || strlen($hotJournalDigest) !== 64)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next207 {$name} hot journal digest must be a sha256 string or null");
        }

        $reasons = [];
        if (!in_array($consumerName, $admittedConsumers, true)) {
            $reasons[] = 'write_cursor_consumer_not_admitted_by_next206';
        }
        if ($commitGeneration < $minimumCommitGeneration) {
            $reasons[] = 'write_cursor_commit_generation_predates_checkpoint';
        }
        if (!hash_equals($expectedWriteLockToken, $lockToken)) {
            $reasons[] = 'write_cursor_lock_token_mismatch';
        }
        if (!hash_equals($databaseDigest, $observedDatabaseDigest)) {
            $reasons[] = 'write_cursor_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, $observedWalDigest)) {
            $reasons[] = 'write_cursor_wal_digest_mismatch';
        }
        if ($stalePages !== [] || $missingPages !== []) {
            $reasons[] = 'write_cursor_page_digest_mismatch';
        }
        if (!empty($cursor['read_only'])) {
            $reasons[] = 'write_cursor_read_only_after_checkpoint';
        }
        if (($cursor['pending_savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'write_cursor_savepoint_scope_not_closed';
        }
        if ($hotJournalDigest !== null) {
            $reasons[] = 'write_cursor_retains_hot_journal_digest';
        }
        if (!empty($cursor['dirty_reader_cache'])) {
            $reasons[] = 'write_cursor_dirty_reader_cache_after_checkpoint';
        }

        $admitted = $reasons === [];

        return array_merge($cursor, [
            'admitted' => $admitted,
            'cursor_reason' => $admitted ? 'write_cursor_matches_checkpoint_generation_and_lock' : $reasons[0],
            'blocked_reasons' => $reasons,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_write_lock_token' => $expectedWriteLockToken,
            'root_pages' => array_values($rootPages),
            'page_rows' => $pageRows,
            'stale_pages' => $stalePages,
            'missing_pages' => $missingPages,
            'hot_journal_retained' => $hotJournalDigest !== null,
            'cursor_transition' => $name . '>' . ($admitted ? 'admit-write-cursor' : 'block-write-cursor'),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $cursorRows
     */
    private static function admittedLockTokensMatch(array $cursorRows, string $expectedWriteLockToken): bool
    {
        foreach ($cursorRows as $row) {
            if (($row['admitted'] ?? false) === true && ($row['write_lock_token'] ?? null) !== $expectedWriteLockToken) {
                return false;
            }
        }

        return true;
    }
}

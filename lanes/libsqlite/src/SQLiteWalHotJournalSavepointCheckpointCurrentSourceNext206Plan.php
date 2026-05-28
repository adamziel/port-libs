<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext206Plan
{
    /**
     * @param array<string,mixed> $leasePlan
     * @param list<array{name:string,reader_epoch:int,statement_generation:int,root_pages:list<int>,observed_database_digest:string,observed_wal_digest:string,observed_page_digests:array<int,string>,hot_journal_digest?:?string,savepoint_depth?:int,dirty?:bool,closed?:bool}> $consumers
     * @return array<string,mixed>
     */
    public static function plan(array $leasePlan, array $consumers, int $minimumStatementGeneration): array
    {
        if (($leasePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next203') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next206 requires an admitted next203 lease plan');
        }
        if ($consumers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next206 requires consumer rows');
        }
        if ($minimumStatementGeneration < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next206 requires a non-negative statement generation');
        }

        $databaseDigest = self::stringDigest($leasePlan, 'checkpointed_database_digest');
        $walDigest = self::stringDigest($leasePlan, 'expected_wal_digest');
        $pageDigests = $leasePlan['expected_page_digests'] ?? null;
        if (!is_array($pageDigests) || $pageDigests === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next206 requires checkpoint page digests');
        }

        $consumerRows = [];
        $admitted = [];
        $quarantined = [];
        foreach ($consumers as $consumer) {
            $row = self::consumerDecision($consumer, $databaseDigest, $walDigest, $pageDigests, $minimumStatementGeneration);
            $consumerRows[] = $row;
            if ($row['admitted']) {
                $admitted[] = $row['name'];
            } else {
                $quarantined[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'next203_page_cache_lease_fence',
                'matched' => ($leasePlan['stale_guard_names'] ?? []) === [],
                'reason' => 'next203 must publish a clean WAL sidecar and checkpoint page-cache fence',
            ],
            [
                'name' => 'statement_generation_mix',
                'matched' => $admitted !== [] && $quarantined !== [],
                'reason' => 'current consumers are retained while stale prepared statements are quarantined',
            ],
            [
                'name' => 'hot_journal_absent_from_admitted_consumers',
                'matched' => self::admittedHotJournalFree($consumerRows),
                'reason' => 'no admitted reopened statement may retain hot-journal identity after checkpoint publication',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        $status = $blockedGuards === []
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next206'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next206';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next206'
                ? 'reopened_statement_consumers_match_checkpoint_generation'
                : 'reopened_statement_consumers_wait_for_checkpoint_generation_reprepare',
            'base_status' => $leasePlan['status'],
            'database_path' => $leasePlan['database_path'] ?? null,
            'wal_path' => $leasePlan['wal_path'] ?? null,
            'journal_path' => $leasePlan['journal_path'] ?? null,
            'page_size' => $leasePlan['page_size'] ?? null,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'checkpointed_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_page_digests' => $pageDigests,
            'consumer_rows' => $consumerRows,
            'admitted_consumer_names' => $admitted,
            'quarantined_consumer_names' => $quarantined,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_merge(
                $leasePlan['operation_names'] ?? [],
                ['verify_reopened_statement_generation_current_source_next206'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_reopened_statement_current_source_next206'
                        : 'quarantine_stale_statement_current_source_next206',
                    $consumerRows
                )
            )),
            'consumer_digest' => hash('sha256', implode('|', array_merge(
                [$databaseDigest, $walDigest, (string) $minimumStatementGeneration],
                array_column($consumerRows, 'consumer_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge($leasePlan['dependencies'] ?? [], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206',
                'sqlite-reopened-statement-generation-fence',
                'wordpress-current-source-prepared-statement-reprepare',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses next203 WAL sidecar and checkpoint page digests to fence reopened prepared statements and page-cache consumers',
            'non_overlap' => 'next206 admits or quarantines reopened statement consumers after next203 page-cache lease fencing; it does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, WAL sidecar file writing, or next203 lease digest checks',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function stringDigest(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || strlen($value) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $consumer
     * @param array<int,string> $pageDigests
     * @return array<string,mixed>
     */
    private static function consumerDecision(array $consumer, string $databaseDigest, string $walDigest, array $pageDigests, int $minimumStatementGeneration): array
    {
        $name = $consumer['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next206 consumer name is required');
        }
        $readerEpoch = $consumer['reader_epoch'] ?? null;
        $statementGeneration = $consumer['statement_generation'] ?? null;
        if (!is_int($readerEpoch) || $readerEpoch < 0 || !is_int($statementGeneration) || $statementGeneration < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} generations must be non-negative integers");
        }
        $observedDatabaseDigest = $consumer['observed_database_digest'] ?? null;
        $observedWalDigest = $consumer['observed_wal_digest'] ?? null;
        if (!is_string($observedDatabaseDigest) || strlen($observedDatabaseDigest) !== 64 || !is_string($observedWalDigest) || strlen($observedWalDigest) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} observed digests are required");
        }
        $rootPages = $consumer['root_pages'] ?? null;
        $observedPageDigests = $consumer['observed_page_digests'] ?? null;
        if (!is_array($rootPages) || $rootPages === [] || !is_array($observedPageDigests) || $observedPageDigests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} page rows are required");
        }

        $pageRows = [];
        $stalePages = [];
        $missingPages = [];
        foreach ($rootPages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} root page must be positive");
            }
            $expectedDigest = $pageDigests[$pageNumber] ?? null;
            if (!is_string($expectedDigest)) {
                $missingPages[] = $pageNumber;
                $pageRows[] = [
                    'page' => $pageNumber,
                    'matched' => false,
                    'reason' => 'page_outside_checkpoint_generation',
                    'expected_digest' => null,
                    'observed_digest' => $observedPageDigests[$pageNumber] ?? null,
                ];
                continue;
            }
            $observedDigest = $observedPageDigests[$pageNumber] ?? null;
            if (!is_string($observedDigest) || strlen($observedDigest) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} page digest is required");
            }
            $matched = hash_equals($expectedDigest, $observedDigest);
            if (!$matched) {
                $stalePages[] = $pageNumber;
            }
            $pageRows[] = [
                'page' => $pageNumber,
                'matched' => $matched,
                'reason' => $matched ? 'checkpoint_generation_page_matches' : 'checkpoint_generation_page_stale',
                'expected_digest' => $expectedDigest,
                'observed_digest' => $observedDigest,
            ];
        }

        $hotJournalDigest = $consumer['hot_journal_digest'] ?? null;
        if ($hotJournalDigest !== null && (!is_string($hotJournalDigest) || strlen($hotJournalDigest) !== 64)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next206 {$name} hot journal digest must be a sha256 string or null");
        }

        $reasons = [];
        if ($statementGeneration < $minimumStatementGeneration) {
            $reasons[] = 'statement_generation_predates_checkpoint_publication';
        }
        if (!hash_equals($databaseDigest, $observedDatabaseDigest)) {
            $reasons[] = 'statement_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, $observedWalDigest)) {
            $reasons[] = 'statement_wal_digest_mismatch';
        }
        if ($stalePages !== [] || $missingPages !== []) {
            $reasons[] = 'statement_page_digest_mismatch';
        }
        if ($hotJournalDigest !== null) {
            $reasons[] = 'statement_retains_hot_journal_digest';
        }
        if (($consumer['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'statement_savepoint_scope_not_closed';
        }
        if (!empty($consumer['dirty'])) {
            $reasons[] = 'statement_cache_dirty_before_checkpoint_publication';
        }
        if (!empty($consumer['closed'])) {
            $reasons[] = 'statement_cache_closed_before_reopen';
        }

        $admitted = $reasons === [];

        return array_merge($consumer, [
            'admitted' => $admitted,
            'consumer_reason' => $admitted ? 'statement_matches_checkpoint_generation' : $reasons[0],
            'blocked_reasons' => $reasons,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'root_pages' => array_values($rootPages),
            'page_rows' => $pageRows,
            'stale_pages' => $stalePages,
            'missing_pages' => $missingPages,
            'hot_journal_retained' => $hotJournalDigest !== null,
            'consumer_transition' => $name . '>' . ($admitted ? 'retain-statement' : 'quarantine-statement'),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $consumerRows
     */
    private static function admittedHotJournalFree(array $consumerRows): bool
    {
        foreach ($consumerRows as $row) {
            if (($row['admitted'] ?? false) === true && ($row['hot_journal_retained'] ?? false) === true) {
                return false;
            }
        }

        return true;
    }
}

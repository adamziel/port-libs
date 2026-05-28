<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext209Plan
{
    /**
     * @param array<string,mixed> $statementPlan
     * @param list<array<string,mixed>> $writers
     * @return array<string,mixed>
     */
    public static function plan(array $statementPlan, array $writers, int $nextWriterGeneration): array
    {
        if (($statementPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next206') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 requires an admitted next206 statement-consumer plan');
        }
        if ($writers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 requires writer rows');
        }
        if ($nextWriterGeneration < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 requires a positive writer generation');
        }

        $databaseDigest = self::digestField($statementPlan, 'checkpointed_database_digest');
        $walDigest = self::digestField($statementPlan, 'expected_wal_digest');
        $consumerDigest = self::digestField($statementPlan, 'consumer_digest');
        $minimumStatementGeneration = $statementPlan['minimum_statement_generation'] ?? null;
        if (!is_int($minimumStatementGeneration) || $minimumStatementGeneration < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 requires a non-negative minimum statement generation');
        }
        if ($nextWriterGeneration <= $minimumStatementGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 writer generation must follow the statement generation');
        }

        $admittedConsumers = self::stringList($statementPlan, 'admitted_consumer_names');
        $quarantinedConsumers = self::stringList($statementPlan, 'quarantined_consumer_names');
        $blockedGuards = $statementPlan['blocked_guard_names'] ?? null;
        if (!is_array($blockedGuards)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 requires next206 guard state');
        }

        $writerRows = [];
        $admittedWriters = [];
        $reopenWriters = [];
        foreach ($writers as $writer) {
            $row = self::writerDecision($writer, $databaseDigest, $walDigest, $consumerDigest, $nextWriterGeneration, $admittedConsumers, $quarantinedConsumers);
            $writerRows[] = $row;
            if ($row['admitted']) {
                $admittedWriters[] = $row['name'];
            } else {
                $reopenWriters[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'next206_statement_generation_fence',
                'matched' => $blockedGuards === [],
                'reason' => 'reopened statement consumers must already be admitted or quarantined by next206',
            ],
            [
                'name' => 'current_and_stale_consumer_mix',
                'matched' => $admittedConsumers !== [] && $quarantinedConsumers !== [],
                'reason' => 'writer admission must prove current consumers are retained while stale consumers stay quarantined',
            ],
            [
                'name' => 'writer_generation_mix',
                'matched' => $admittedWriters !== [] && $reopenWriters !== [],
                'reason' => 'current writers are retained while stale writer handles are reopened',
            ],
            [
                'name' => 'writer_rows_hot_journal_free',
                'matched' => self::admittedWritersHotJournalFree($writerRows),
                'reason' => 'no admitted writer may retain hot-journal identity after checkpoint publication',
            ],
        ];
        $blocked = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next209'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next209',
            'reason' => $ready
                ? 'post_checkpoint_writers_follow_current_statement_generation'
                : 'post_checkpoint_writers_wait_for_current_source_reopen',
            'base_status' => $statementPlan['status'],
            'database_path' => $statementPlan['database_path'] ?? null,
            'journal_path' => $statementPlan['journal_path'] ?? null,
            'wal_path' => $statementPlan['wal_path'] ?? null,
            'page_size' => $statementPlan['page_size'] ?? null,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'next_writer_generation' => $nextWriterGeneration,
            'checkpointed_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'consumer_digest' => $consumerDigest,
            'admitted_consumer_names' => $admittedConsumers,
            'quarantined_consumer_names' => $quarantinedConsumers,
            'writer_rows' => $writerRows,
            'admitted_writer_names' => $admittedWriters,
            'reopen_writer_names' => $reopenWriters,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blocked,
            'operation_names' => array_values(array_merge(
                is_array($statementPlan['operation_names'] ?? null) ? $statementPlan['operation_names'] : [],
                ['verify_post_checkpoint_writer_generation_current_source_next209'],
                array_map(
                    static fn (array $row): string => $row['admitted']
                        ? 'retain_post_checkpoint_writer_current_source_next209'
                        : 'reopen_post_checkpoint_writer_current_source_next209',
                    $writerRows
                )
            )),
            'writer_digest' => hash('sha256', implode('|', array_merge(
                [$databaseDigest, $walDigest, $consumerDigest, (string) $nextWriterGeneration],
                array_column($writerRows, 'writer_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($statementPlan['dependencies'] ?? null) ? $statementPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209',
                    'sqlite-post-checkpoint-writer-generation-fence',
                    'wordpress-import-post-checkpoint-writer-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next206 statement-consumer fences, WAL/database digests, and lane-local writer handle metadata',
            'non_overlap' => 'next209 gates post-checkpoint writer handle reuse after next206 statement admission; it does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, WAL file writing, or next206 reopened-statement consumer classification',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || strlen($value) !== 64) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     * @return list<string>
     */
    private static function stringList(array $values, string $key): array
    {
        $list = $values[$key] ?? null;
        if (!is_array($list)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $writer
     * @param list<string> $admittedConsumers
     * @param list<string> $quarantinedConsumers
     * @return array<string,mixed>
     */
    private static function writerDecision(
        array $writer,
        string $databaseDigest,
        string $walDigest,
        string $consumerDigest,
        int $nextWriterGeneration,
        array $admittedConsumers,
        array $quarantinedConsumers
    ): array {
        $name = $writer['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next209 writer name is required');
        }
        $kind = $writer['kind'] ?? 'writer';
        if (!is_string($kind) || !in_array($kind, ['writer', 'checkpoint', 'schema'], true)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$name} writer kind is invalid");
        }
        $writerGeneration = $writer['writer_generation'] ?? null;
        $statementGeneration = $writer['statement_generation'] ?? null;
        if (!is_int($writerGeneration) || $writerGeneration < 0 || !is_int($statementGeneration) || $statementGeneration < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$name} generations must be non-negative integers");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_consumer_digest'] as $key) {
            if (!is_string($writer[$key] ?? null) || strlen((string) $writer[$key]) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$name} {$key} is required");
            }
        }
        $retainsConsumers = $writer['retains_consumers'] ?? null;
        $reopensConsumers = $writer['reopens_consumers'] ?? null;
        if (!is_array($retainsConsumers) || !is_array($reopensConsumers)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$name} consumer lists are required");
        }

        $missingRetained = array_values(array_diff($admittedConsumers, $retainsConsumers));
        $missingReopened = array_values(array_diff($quarantinedConsumers, $reopensConsumers));
        $unexpectedRetained = array_values(array_intersect($quarantinedConsumers, $retainsConsumers));
        $unexpectedReopened = array_values(array_intersect($admittedConsumers, $reopensConsumers));

        $reasons = [];
        if ($writerGeneration !== $nextWriterGeneration) {
            $reasons[] = 'writer_generation_mismatch';
        }
        if ($statementGeneration < $nextWriterGeneration - 1) {
            $reasons[] = 'writer_statement_generation_predates_checkpoint';
        }
        if (!hash_equals($databaseDigest, (string) $writer['observed_database_digest'])) {
            $reasons[] = 'writer_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $writer['observed_wal_digest'])) {
            $reasons[] = 'writer_wal_digest_mismatch';
        }
        if (!hash_equals($consumerDigest, (string) $writer['observed_consumer_digest'])) {
            $reasons[] = 'writer_consumer_digest_mismatch';
        }
        if ($missingRetained !== [] || $unexpectedReopened !== []) {
            $reasons[] = 'writer_current_consumers_not_retained';
        }
        if ($missingReopened !== [] || $unexpectedRetained !== []) {
            $reasons[] = 'writer_stale_consumers_not_reopened';
        }
        if (($writer['hot_journal_digest'] ?? null) !== null) {
            if (!is_string($writer['hot_journal_digest']) || strlen($writer['hot_journal_digest']) !== 64) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next209 {$name} hot journal digest must be a sha256 string or null");
            }
            $reasons[] = 'writer_retains_hot_journal_digest';
        }
        if (($writer['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'writer_savepoint_scope_not_closed';
        }
        if (!empty($writer['dirty'])) {
            $reasons[] = 'writer_cache_dirty_before_append';
        }
        if (!empty($writer['closed'])) {
            $reasons[] = 'writer_handle_closed_before_append';
        }

        $admitted = $reasons === [];

        return array_merge($writer, [
            'kind' => $kind,
            'admitted' => $admitted,
            'writer_reason' => $admitted ? 'writer_matches_post_checkpoint_generation' : $reasons[0],
            'blocked_reasons' => $reasons,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_consumer_digest' => $consumerDigest,
            'missing_retained_consumers' => $missingRetained,
            'missing_reopened_consumers' => $missingReopened,
            'unexpected_retained_consumers' => $unexpectedRetained,
            'unexpected_reopened_consumers' => $unexpectedReopened,
            'hot_journal_retained' => ($writer['hot_journal_digest'] ?? null) !== null,
            'writer_transition' => $name . '>' . ($admitted ? 'retain-writer' : 'reopen-writer'),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $writerRows
     */
    private static function admittedWritersHotJournalFree(array $writerRows): bool
    {
        foreach ($writerRows as $row) {
            if (($row['admitted'] ?? false) === true && ($row['hot_journal_retained'] ?? false) === true) {
                return false;
            }
        }

        return true;
    }
}

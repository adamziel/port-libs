<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext210Plan
{
    /**
     * @param array<string,mixed> $writerPlan
     * @param list<array<string,mixed>> $appendBatches
     * @return array<string,mixed>
     */
    public static function plan(array $writerPlan, array $appendBatches, int $nextCommitFrame): array
    {
        if (($writerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next209') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 requires an admitted next209 writer plan');
        }
        if ($appendBatches === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 requires append batches');
        }

        $minimumStatementGeneration = self::intField($writerPlan, 'minimum_statement_generation', 0);
        $nextWriterGeneration = self::intField($writerPlan, 'next_writer_generation', $minimumStatementGeneration + 1);
        if ($nextWriterGeneration <= $minimumStatementGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 writer generation must follow the statement generation');
        }
        if ($nextCommitFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 commit frame must be positive');
        }

        $databaseDigest = self::digestField($writerPlan, 'checkpointed_database_digest');
        $walDigest = self::digestField($writerPlan, 'expected_wal_digest');
        $consumerDigest = self::digestField($writerPlan, 'consumer_digest');
        $admittedWriters = self::stringList($writerPlan, 'admitted_writer_names');
        $reopenWriters = self::stringList($writerPlan, 'reopen_writer_names');
        $blockedGuards = $writerPlan['blocked_guard_names'] ?? null;
        if (!is_array($blockedGuards)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 requires next209 guard state');
        }

        $batchRows = [];
        $accepted = [];
        $blocked = [];
        foreach ($appendBatches as $batch) {
            $row = self::batchDecision($batch, $databaseDigest, $walDigest, $consumerDigest, $nextWriterGeneration, $nextCommitFrame, $admittedWriters, $reopenWriters);
            $batchRows[] = $row;
            if ($row['accepted']) {
                $accepted[] = $row['name'];
            } else {
                $blocked[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'next209_writer_generation_fence',
                'matched' => $blockedGuards === [],
                'reason' => 'writer handles must already be admitted or reopened by next209',
            ],
            [
                'name' => 'post_checkpoint_append_mix',
                'matched' => $accepted !== [] && $blocked !== [],
                'reason' => 'the append fence must prove current batches advance while stale batches are blocked',
            ],
            [
                'name' => 'commit_frame_advances_checkpoint',
                'matched' => $nextCommitFrame > self::maxCheckpointFrame($batchRows),
                'reason' => 'new WAL commit frames must follow the checkpoint frame held by the writer source',
            ],
            [
                'name' => 'accepted_batches_hot_journal_free',
                'matched' => self::acceptedBatchesHotJournalFree($batchRows),
                'reason' => 'accepted appends cannot retain hot rollback-journal identity',
            ],
        ];
        $blockedGuardsNext210 = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blockedGuardsNext210 === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next210'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next210',
            'reason' => $ready
                ? 'post_checkpoint_wal_appends_follow_current_writer_generation'
                : 'post_checkpoint_wal_appends_wait_for_current_source_refresh',
            'base_status' => $writerPlan['status'],
            'database_path' => $writerPlan['database_path'] ?? null,
            'journal_path' => $writerPlan['journal_path'] ?? null,
            'wal_path' => $writerPlan['wal_path'] ?? null,
            'page_size' => $writerPlan['page_size'] ?? null,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'next_writer_generation' => $nextWriterGeneration,
            'next_commit_frame' => $nextCommitFrame,
            'checkpointed_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'consumer_digest' => $consumerDigest,
            'admitted_writer_names' => $admittedWriters,
            'reopen_writer_names' => $reopenWriters,
            'append_batch_rows' => $batchRows,
            'accepted_append_batch_names' => $accepted,
            'blocked_append_batch_names' => $blocked,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuardsNext210,
            'operation_names' => array_values(array_merge(
                is_array($writerPlan['operation_names'] ?? null) ? $writerPlan['operation_names'] : [],
                ['verify_post_checkpoint_wal_append_current_source_next210'],
                array_map(
                    static fn (array $row): string => $row['accepted']
                        ? 'accept_post_checkpoint_wal_append_current_source_next210'
                        : 'block_post_checkpoint_wal_append_current_source_next210',
                    $batchRows
                )
            )),
            'append_digest' => hash('sha256', implode('|', array_merge(
                [$databaseDigest, $walDigest, $consumerDigest, (string) $nextWriterGeneration, (string) $nextCommitFrame],
                array_column($batchRows, 'append_transition')
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($writerPlan['dependencies'] ?? null) ? $writerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next210',
                    'sqlite-post-checkpoint-wal-append-generation-fence',
                    'wordpress-import-post-checkpoint-wal-append-after-hot-journal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next209 writer-generation fences, WAL/database digests, and append-frame metadata',
            'non_overlap' => 'next210 gates new WAL append batches after next209 writer reuse; it does not repeat next208 reader-slot reuse, next209 writer-handle admission, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback apply, rollback-journal commit/apply, WAL file writing, or hot-journal recovery application',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function intField(array $values, string $key, int $minimum): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value < $minimum) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 requires {$key}");
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
        if (!is_array($list) || $list === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $batch
     * @param list<string> $admittedWriters
     * @param list<string> $reopenWriters
     * @return array<string,mixed>
     */
    private static function batchDecision(
        array $batch,
        string $databaseDigest,
        string $walDigest,
        string $consumerDigest,
        int $nextWriterGeneration,
        int $nextCommitFrame,
        array $admittedWriters,
        array $reopenWriters
    ): array {
        $name = $batch['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next210 append batch name is required');
        }
        $writer = $batch['writer_name'] ?? null;
        if (!is_string($writer) || $writer === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} writer name is required");
        }
        $writerGeneration = $batch['writer_generation'] ?? null;
        $checkpointFrame = $batch['checkpoint_frame'] ?? null;
        $firstFrame = $batch['first_frame'] ?? null;
        $commitFrame = $batch['commit_frame'] ?? null;
        foreach (['writer_generation' => $writerGeneration, 'checkpoint_frame' => $checkpointFrame, 'first_frame' => $firstFrame, 'commit_frame' => $commitFrame] as $key => $value) {
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} {$key} must be a non-negative integer");
            }
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_consumer_digest'] as $key) {
            if (!is_string($batch[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $batch[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} {$key} is required");
            }
        }
        $pageDigests = $batch['page_digests'] ?? null;
        if (!is_array($pageDigests) || $pageDigests === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} page digests are required");
        }
        $normalizedPages = [];
        foreach ($pageDigests as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} page digests must map positive pages to sha256 strings");
            }
            $normalizedPages[$pageNumber] = $digest;
        }
        ksort($normalizedPages);

        $reasons = [];
        if (!in_array($writer, $admittedWriters, true)) {
            $reasons[] = in_array($writer, $reopenWriters, true) ? 'append_writer_requires_reopen' : 'append_writer_not_admitted';
        }
        if ($writerGeneration !== $nextWriterGeneration) {
            $reasons[] = 'append_writer_generation_mismatch';
        }
        if ($firstFrame !== $checkpointFrame + 1) {
            $reasons[] = 'append_first_frame_does_not_follow_checkpoint';
        }
        if ($commitFrame !== $nextCommitFrame) {
            $reasons[] = 'append_commit_frame_mismatch';
        }
        if ($commitFrame < $firstFrame) {
            $reasons[] = 'append_commit_frame_before_first_frame';
        }
        if (!hash_equals($databaseDigest, (string) $batch['observed_database_digest'])) {
            $reasons[] = 'append_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $batch['observed_wal_digest'])) {
            $reasons[] = 'append_wal_digest_mismatch';
        }
        if (!hash_equals($consumerDigest, (string) $batch['observed_consumer_digest'])) {
            $reasons[] = 'append_consumer_digest_mismatch';
        }
        if (($batch['hot_journal_digest'] ?? null) !== null) {
            if (!is_string($batch['hot_journal_digest']) || !preg_match('/^[a-f0-9]{64}$/', $batch['hot_journal_digest'])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next210 {$name} hot journal digest must be a sha256 string or null");
            }
            $reasons[] = 'append_retains_hot_journal_digest';
        }
        if (($batch['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'append_savepoint_scope_not_closed';
        }
        if (empty($batch['exclusive_lock_receipt'])) {
            $reasons[] = 'append_missing_exclusive_lock_receipt';
        }
        if (!empty($batch['dirty_before_append'])) {
            $reasons[] = 'append_dirty_cache_before_frame_write';
        }

        $accepted = $reasons === [];

        return array_merge($batch, [
            'page_digests' => $normalizedPages,
            'accepted' => $accepted,
            'append_reason' => $accepted ? 'append_batch_matches_current_writer_generation' : $reasons[0],
            'blocked_reasons' => $reasons,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_consumer_digest' => $consumerDigest,
            'expected_writer_generation' => $nextWriterGeneration,
            'expected_commit_frame' => $nextCommitFrame,
            'page_numbers' => array_keys($normalizedPages),
            'page_digest' => hash('sha256', implode('|', array_map(
                static fn (int $page, string $digest): string => $page . ':' . $digest,
                array_keys($normalizedPages),
                $normalizedPages
            ))),
            'hot_journal_retained' => ($batch['hot_journal_digest'] ?? null) !== null,
            'append_transition' => $name . '>' . ($accepted ? 'append-wal-frames' : 'block-wal-append'),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $batchRows
     */
    private static function maxCheckpointFrame(array $batchRows): int
    {
        $frames = array_map(static fn (array $row): int => (int) $row['checkpoint_frame'], $batchRows);

        return max($frames);
    }

    /**
     * @param list<array<string,mixed>> $batchRows
     */
    private static function acceptedBatchesHotJournalFree(array $batchRows): bool
    {
        foreach ($batchRows as $row) {
            if (($row['accepted'] ?? false) === true && ($row['hot_journal_retained'] ?? false) === true) {
                return false;
            }
        }

        return true;
    }
}

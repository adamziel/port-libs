<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext215Plan
{
    /**
     * @param array<string,mixed> $passivePlan
     * @param list<array<string,mixed>> $reopenRows
     * @return array<string,mixed>
     */
    public static function restartCheckpoint(array $passivePlan, array $reopenRows, string $mode = 'restart'): array
    {
        if (($passivePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next215 requires an admitted next212 passive checkpoint plan');
        }
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next215 mode must be restart or truncate');
        }
        if ($reopenRows === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next215 requires reopened reader rows');
        }

        $requestedFrame = self::positiveInt($passivePlan, 'requested_checkpoint_frame');
        $checkpointedFrame = self::positiveInt($passivePlan, 'checkpointed_frame');
        $nextWriterGeneration = self::positiveInt($passivePlan, 'next_writer_generation');
        $minimumStatementGeneration = self::nonNegativeInt($passivePlan, 'minimum_statement_generation');
        $databaseDigest = self::digestField($passivePlan, 'database_digest');
        $walDigest = self::digestField($passivePlan, 'wal_digest');
        $writerDigest = self::digestField($passivePlan, 'writer_digest');
        $reopenReaderNames = self::stringList($passivePlan, 'reopen_reader_names');
        $activeReaderNames = self::stringList($passivePlan, 'active_reader_names');

        $readerRows = [];
        foreach ($reopenRows as $row) {
            $readerRows[] = self::reopenRow(
                $row,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $nextWriterGeneration,
                $minimumStatementGeneration,
                $requestedFrame
            );
        }

        $admitted = array_values(array_column(array_filter($readerRows, static fn (array $row): bool => $row['admitted']), 'name'));
        $blocked = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blocked as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }

        $missingReopens = array_values(array_diff($reopenReaderNames, $admitted));
        $unexpectedReopens = array_values(array_diff($admitted, $reopenReaderNames));
        $checkpointComplete = $checkpointedFrame === $requestedFrame || (($passivePlan['busy'] ?? null) === false);
        $noPins = $activeReaderNames === [];
        $operationNames = is_array($passivePlan['operation_names'] ?? null) ? $passivePlan['operation_names'] : [];
        $hasPriorBusyPin = in_array('preserve_wal_for_pinned_reader_next212', $operationNames, true);

        $guardRows = [
            [
                'name' => 'prior_passive_checkpoint_reported_reader_pin',
                'matched' => $hasPriorBusyPin,
                'reason' => 'restart/truncate completion must follow a passive checkpoint that stopped at a current reader pin',
            ],
            [
                'name' => 'all_stale_readers_reopened',
                'matched' => $missingReopens === [] && $unexpectedReopens === [],
                'reason' => 'stale readers must be reopened before checkpoint reset/truncate can publish a new current source',
            ],
            [
                'name' => 'reopened_readers_match_current_source',
                'matched' => $blocked === [],
                'reason' => 'reopened readers must observe the post-hot-journal database, WAL, and writer digests',
            ],
            [
                'name' => 'no_active_reader_pin_remaining',
                'matched' => $noPins,
                'reason' => 'restart/truncate checkpoint reset waits until current reader pins drain',
            ],
            [
                'name' => 'checkpoint_covers_requested_frame',
                'matched' => $checkpointComplete,
                'reason' => 'database image must contain all frames through the requested checkpoint frame',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blockedGuards === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next215'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next215',
            'reason' => $ready
                ? 'restart_checkpoint_resets_wal_after_current_source_readers_reopen'
                : 'restart_checkpoint_waits_for_reader_reopen_and_pin_drain',
            'base_status' => $passivePlan['status'],
            'database_path' => $passivePlan['database_path'] ?? null,
            'journal_path' => $passivePlan['journal_path'] ?? null,
            'wal_path' => $passivePlan['wal_path'] ?? null,
            'page_size' => $passivePlan['page_size'] ?? null,
            'mode' => $mode,
            'requested_checkpoint_frame' => $requestedFrame,
            'passive_checkpointed_frame' => $checkpointedFrame,
            'checkpointed_frame' => $ready ? $requestedFrame : $checkpointedFrame,
            'busy' => !$ready,
            'reset_allowed' => $ready,
            'truncate_allowed' => $ready && $mode === 'truncate',
            'wal_action' => $ready ? ($mode === 'truncate' ? 'truncate_wal_after_restart_checkpoint' : 'reset_wal_header_after_restart_checkpoint') : 'preserve_wal',
            'database_action' => $ready ? 'write_frames_through_' . $requestedFrame : 'write_frames_through_' . $checkpointedFrame,
            'journal_action' => $ready ? 'hot_journal_removed_before_wal_reset' : 'retain_hot_journal_fence',
            'new_current_source_epoch' => $nextWriterGeneration + 1,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'next_writer_generation' => $nextWriterGeneration,
            'database_digest' => $databaseDigest,
            'wal_digest' => $walDigest,
            'writer_digest' => $writerDigest,
            'required_reopen_reader_names' => $reopenReaderNames,
            'active_reader_names' => $activeReaderNames,
            'admitted_reopen_reader_names' => $admitted,
            'missing_reopen_reader_names' => $missingReopens,
            'unexpected_reopen_reader_names' => $unexpectedReopens,
            'blocked_reader_reasons' => array_values(array_unique($blockedReasons)),
            'reader_rows' => $readerRows,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                $operationNames,
                [
                    'verify_restart_checkpoint_reopen_current_source_next215',
                    $ready ? 'publish_restart_checkpoint_current_source_next215' : 'preserve_wal_until_reader_pin_drains_next215',
                ]
            ))),
            'checkpoint_digest' => hash('sha256', json_encode([$mode, $requestedFrame, $ready, $readerRows], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($passivePlan['dependencies'] ?? null) ? $passivePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next215',
                    'sqlite-restart-checkpoint-reader-reopen-after-hot-journal',
                    'wordpress-import-restart-checkpoint-reset-after-reader-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next212 passive checkpoint reader-pin metadata and current-source digest fences',
            'non_overlap' => 'next215 models RESTART/TRUNCATE completion after next212 PASSIVE reader-pin discovery; it does not repeat next212 passive progress, next209 writer fences, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, sync plans, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function nonNegativeInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 requires non-negative {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $reader
     * @return array<string,mixed>
     */
    private static function reopenRow(
        array $reader,
        string $databaseDigest,
        string $walDigest,
        string $writerDigest,
        int $nextWriterGeneration,
        int $minimumStatementGeneration,
        int $requestedFrame
    ): array {
        $name = $reader['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next215 reader name is required');
        }
        $endFrame = $reader['reader_end_frame'] ?? null;
        $generation = $reader['reader_generation'] ?? null;
        if (!is_int($endFrame) || $endFrame <= 0 || !is_int($generation) || $generation < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 {$name} reader frame/generation is invalid");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($reader[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $reader[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next215 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if (($reader['reopened'] ?? false) !== true) {
            $reasons[] = 'reader_not_reopened';
        }
        if ($generation !== $nextWriterGeneration) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if ($endFrame < $minimumStatementGeneration || $endFrame > $requestedFrame) {
            $reasons[] = 'reader_end_frame_outside_checkpoint_window';
        }
        if (!hash_equals($databaseDigest, (string) $reader['observed_database_digest'])) {
            $reasons[] = 'reader_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $reader['observed_wal_digest'])) {
            $reasons[] = 'reader_wal_digest_mismatch';
        }
        if (!hash_equals($writerDigest, (string) $reader['observed_writer_digest'])) {
            $reasons[] = 'reader_writer_digest_mismatch';
        }
        if (($reader['dirty'] ?? false) === true) {
            $reasons[] = 'reader_cache_dirty';
        }
        if (($reader['closed'] ?? false) === true) {
            $reasons[] = 'reader_handle_closed';
        }

        $admitted = $reasons === [];

        return [
            'name' => $name,
            'reader_end_frame' => $endFrame,
            'reader_generation' => $generation,
            'reopened' => ($reader['reopened'] ?? false) === true,
            'admitted' => $admitted,
            'reader_reason' => $admitted ? 'reader_reopened_on_current_source_for_restart_checkpoint' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
            'transition' => $name . '>' . ($admitted ? 'reopened-current-source' : 'preserve-old-source') . ':next215',
        ];
    }
}

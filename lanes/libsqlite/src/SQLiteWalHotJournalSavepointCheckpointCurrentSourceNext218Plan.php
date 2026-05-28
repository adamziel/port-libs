<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext218Plan
{
    /**
     * @param array<string,mixed> $passiveCheckpoint
     * @param list<array<string,mixed>> $writers
     * @return array<string,mixed>
     */
    public static function restartOrTruncate(array $passiveCheckpoint, array $writers, string $mode): array
    {
        if (($passiveCheckpoint['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next218 requires an admitted next212 passive checkpoint');
        }
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next218 mode must be restart or truncate');
        }
        if ($writers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next218 requires writer rows');
        }

        $requestedFrame = self::positiveInt($passiveCheckpoint, 'requested_checkpoint_frame');
        $checkpointedFrame = self::positiveInt($passiveCheckpoint, 'checkpointed_frame');
        $databaseDigest = self::digestField($passiveCheckpoint, 'database_digest');
        $walDigest = self::digestField($passiveCheckpoint, 'wal_digest');
        $writerDigest = self::digestField($passiveCheckpoint, 'writer_digest');
        $nextWriterGeneration = self::positiveInt($passiveCheckpoint, 'next_writer_generation');
        $minimumStatementGeneration = self::nonNegativeInt($passiveCheckpoint, 'minimum_statement_generation');
        $activeReaders = self::stringList($passiveCheckpoint, 'active_reader_names');
        $reopenReaders = self::stringList($passiveCheckpoint, 'reopen_reader_names');

        $writerRows = [];
        foreach ($writers as $writer) {
            $writerRows[] = self::writerRow(
                $writer,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $requestedFrame,
                $checkpointedFrame,
                $nextWriterGeneration,
                $minimumStatementGeneration
            );
        }

        $admitted = array_values(array_filter($writerRows, static fn (array $row): bool => $row['admitted']));
        $blocked = array_values(array_filter($writerRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blocked as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }

        $readerPins = ($passiveCheckpoint['busy'] ?? false) === true || $checkpointedFrame < $requestedFrame || $activeReaders !== [];
        $hasReopenReaders = $reopenReaders !== [];
        $allFramesCheckpointed = $checkpointedFrame === $requestedFrame;
        $canReset = !$readerPins && !$hasReopenReaders && $allFramesCheckpointed && $blocked === [];

        $guardRows = [
            [
                'name' => 'next212_passive_checkpoint_complete',
                'matched' => !$readerPins && $allFramesCheckpointed,
                'reason' => 'restart/truncate may reset only after PASSIVE checkpoint wrote every requested frame and no reader pins the WAL',
            ],
            [
                'name' => 'no_reopen_readers_pending',
                'matched' => !$hasReopenReaders,
                'reason' => 'stale current-source readers must reopen before a WAL reset can be published',
            ],
            [
                'name' => 'writer_generation_fence',
                'matched' => $admitted !== [] && $blocked === [],
                'reason' => 'all candidate writers must observe the checkpointed database, WAL, writer digest, and savepoint fence',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        $walAction = $canReset
            ? ($mode === 'truncate' ? 'truncate_wal_to_zero_bytes' : 'restart_wal_header_with_new_salt')
            : 'preserve_wal_for_reader_or_writer_reopen';

        return [
            'status' => $canReset
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next218'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next218',
            'reason' => $canReset
                ? 'restart_or_truncate_checkpoint_can_reset_wal_after_hot_journal_savepoint_fence'
                : 'restart_or_truncate_checkpoint_preserves_wal_until_readers_and_writers_reopen',
            'base_status' => $passiveCheckpoint['status'],
            'mode' => $mode,
            'database_path' => $passiveCheckpoint['database_path'] ?? null,
            'journal_path' => $passiveCheckpoint['journal_path'] ?? null,
            'wal_path' => $passiveCheckpoint['wal_path'] ?? null,
            'page_size' => $passiveCheckpoint['page_size'] ?? null,
            'requested_checkpoint_frame' => $requestedFrame,
            'checkpointed_frame' => $checkpointedFrame,
            'all_frames_checkpointed' => $allFramesCheckpointed,
            'reader_pins_wal' => $readerPins,
            'active_reader_names' => $activeReaders,
            'reopen_reader_names' => $reopenReaders,
            'database_digest' => $databaseDigest,
            'wal_digest' => $walDigest,
            'writer_digest' => $writerDigest,
            'next_writer_generation' => $nextWriterGeneration,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'writer_rows' => $writerRows,
            'admitted_writer_names' => array_values(array_column($admitted, 'name')),
            'blocked_writer_names' => array_values(array_column($blocked, 'name')),
            'blocked_writer_reasons' => array_values(array_unique($blockedReasons)),
            'can_reset_wal' => $canReset,
            'reset_allowed' => $canReset && $mode === 'restart',
            'truncate_allowed' => $canReset && $mode === 'truncate',
            'wal_action' => $walAction,
            'database_action' => 'checkpoint_database_already_contains_frames_through_' . $checkpointedFrame,
            'sync_action' => $canReset ? 'sync_database_then_' . $mode . '_wal_header' : 'skip_wal_reset_sync_until_reopen',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($passiveCheckpoint['operation_names'] ?? null) ? $passiveCheckpoint['operation_names'] : [],
                [
                    'verify_restart_truncate_current_source_next218',
                    $canReset ? 'publish_wal_reset_current_source_next218' : 'preserve_wal_reset_current_source_next218',
                    $walAction,
                ]
            ))),
            'reset_digest' => hash('sha256', json_encode([$mode, $canReset, $writerRows, $checkpointedFrame], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($passiveCheckpoint['dependencies'] ?? null) ? $passiveCheckpoint['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218',
                    'sqlite-wal-restart-truncate-after-hot-journal-savepoint-fence',
                    'wordpress-import-checkpoint-reset-waits-for-current-source-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next212 checkpoint frame accounting, current-source reader reopen lists, and writer generation digests',
            'non_overlap' => 'next218 finalizes RESTART/TRUNCATE reset admission after next212 PASSIVE checkpoint frame accounting; it does not repeat next212 reader-pin progress, next209 writer fences, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 requires positive {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 requires non-negative {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $writer
     * @return array<string,mixed>
     */
    private static function writerRow(
        array $writer,
        string $databaseDigest,
        string $walDigest,
        string $writerDigest,
        int $requestedFrame,
        int $checkpointedFrame,
        int $nextWriterGeneration,
        int $minimumStatementGeneration
    ): array {
        $name = $writer['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next218 writer name is required');
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($writer[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $writer[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 {$name} {$key} is required");
            }
        }
        $generation = $writer['writer_generation'] ?? null;
        $startFrame = $writer['start_frame'] ?? null;
        $lastFrame = $writer['last_frame'] ?? null;
        if (!is_int($generation) || $generation < 0 || !is_int($startFrame) || $startFrame <= 0 || !is_int($lastFrame) || $lastFrame < $startFrame) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next218 {$name} generation/frame bounds are invalid");
        }

        $reasons = [];
        if ($generation !== $nextWriterGeneration) {
            $reasons[] = 'writer_generation_mismatch';
        }
        if ($startFrame < $minimumStatementGeneration) {
            $reasons[] = 'writer_start_frame_before_statement_generation';
        }
        if ($lastFrame !== $requestedFrame || $checkpointedFrame !== $requestedFrame) {
            $reasons[] = 'writer_wal_frame_not_fully_checkpointed';
        }
        if (!hash_equals($databaseDigest, (string) $writer['observed_database_digest'])) {
            $reasons[] = 'writer_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $writer['observed_wal_digest'])) {
            $reasons[] = 'writer_wal_digest_mismatch';
        }
        if (!hash_equals($writerDigest, (string) $writer['observed_writer_digest'])) {
            $reasons[] = 'writer_digest_mismatch';
        }
        if (($writer['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'writer_hot_journal_present';
        }
        if (($writer['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'writer_savepoint_scope_not_closed';
        }
        if (($writer['dirty'] ?? false) === true) {
            $reasons[] = 'writer_dirty_page_cache';
        }
        if (($writer['sync_receipt'] ?? false) !== true) {
            $reasons[] = 'writer_missing_sync_receipt';
        }

        $admitted = $reasons === [];

        return [
            'name' => $name,
            'writer_generation' => $generation,
            'expected_generation' => $nextWriterGeneration,
            'start_frame' => $startFrame,
            'last_frame' => $lastFrame,
            'expected_checkpoint_frame' => $requestedFrame,
            'observed_database_digest' => (string) $writer['observed_database_digest'],
            'observed_wal_digest' => (string) $writer['observed_wal_digest'],
            'observed_writer_digest' => (string) $writer['observed_writer_digest'],
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_writer_digest' => $writerDigest,
            'hot_journal_present' => ($writer['hot_journal_present'] ?? false) === true,
            'savepoint_depth' => (int) ($writer['savepoint_depth'] ?? 0),
            'dirty' => ($writer['dirty'] ?? false) === true,
            'sync_receipt' => ($writer['sync_receipt'] ?? false) === true,
            'admitted' => $admitted,
            'writer_reason' => $admitted ? 'writer_can_publish_restart_truncate_reset' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }
}

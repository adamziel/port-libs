<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext214Plan
{
    /**
     * @param array<string,mixed> $passivePlan
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function restartCheckpoint(array $passivePlan, array $readers, array $options = []): array
    {
        if (($passivePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next214 requires an admitted next212 passive checkpoint plan');
        }
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next214 requires reader rows');
        }

        $requestedFrame = self::positiveInt($passivePlan, 'requested_checkpoint_frame');
        $checkpointedFrame = self::positiveInt($passivePlan, 'checkpointed_frame');
        $databaseDigest = self::digestField($passivePlan, 'database_digest');
        $walDigest = self::digestField($passivePlan, 'wal_digest');
        $writerDigest = self::digestField($passivePlan, 'writer_digest');
        $writerGeneration = self::positiveInt($passivePlan, 'next_writer_generation');
        $saltBefore = self::digestOption($options, 'wal_salt_before');
        $saltAfter = self::digestOption($options, 'wal_salt_after');
        $hotJournalDigest = self::digestOption($options, 'hot_journal_digest');
        $savepointClosed = ($options['savepoint_closed'] ?? false) === true;
        $exclusiveLock = ($options['exclusive_checkpoint_lock'] ?? false) === true;
        $databaseSynced = ($options['database_synced'] ?? false) === true;
        $walHeaderSynced = ($options['wal_header_synced'] ?? false) === true;
        $directorySynced = ($options['directory_synced'] ?? false) === true;
        $deleteHotJournal = ($options['delete_hot_journal_after_reset'] ?? false) === true;

        $readerRows = [];
        foreach ($readers as $reader) {
            $readerRows[] = self::readerRow(
                $reader,
                $requestedFrame,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $writerGeneration
            );
        }

        $currentReaders = array_values(array_filter($readerRows, static fn (array $row): bool => $row['pins_current_source']));
        $staleReaders = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted'] && !$row['pins_current_source']));
        $blockedReasons = [];
        foreach ($readerRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }

        $guards = [
            'passive_checkpoint_complete' => $checkpointedFrame === $requestedFrame && ($passivePlan['busy'] ?? true) === false,
            'all_current_readers_released' => $currentReaders === [],
            'stale_readers_reopened' => $staleReaders !== [],
            'savepoint_closed' => $savepointClosed,
            'exclusive_checkpoint_lock' => $exclusiveLock,
            'database_synced' => $databaseSynced,
            'wal_header_synced' => $walHeaderSynced,
            'directory_synced' => $directorySynced,
            'wal_salt_rotated' => !hash_equals($saltBefore, $saltAfter),
            'hot_journal_digest_verified' => !hash_equals($hotJournalDigest, str_repeat('0', 64)),
            'delete_hot_journal_after_reset' => $deleteHotJournal,
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $ready = $blockedGuards === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next214'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next214',
            'reason' => $ready
                ? 'restart_checkpoint_resets_wal_after_current_source_readers_release'
                : 'restart_checkpoint_waits_for_reader_release_and_durable_reset',
            'base_status' => $passivePlan['status'],
            'database_path' => $passivePlan['database_path'] ?? null,
            'journal_path' => $passivePlan['journal_path'] ?? null,
            'wal_path' => $passivePlan['wal_path'] ?? null,
            'page_size' => $passivePlan['page_size'] ?? null,
            'requested_checkpoint_frame' => $requestedFrame,
            'checkpointed_frame' => $checkpointedFrame,
            'restart_allowed' => $ready,
            'reset_allowed' => $ready,
            'truncate_allowed' => false,
            'wal_action' => $ready ? 'restart_wal_header_with_rotated_salt' : 'preserve_wal',
            'database_action' => 'write_frames_through_' . $checkpointedFrame,
            'journal_action' => $ready ? 'delete_hot_journal_after_wal_restart_sync' : 'preserve_hot_journal',
            'database_digest' => $databaseDigest,
            'wal_digest_before' => $walDigest,
            'writer_digest' => $writerDigest,
            'writer_generation' => $writerGeneration,
            'wal_salt_before' => $saltBefore,
            'wal_salt_after' => $saltAfter,
            'hot_journal_digest' => $hotJournalDigest,
            'reader_rows' => $readerRows,
            'current_reader_names' => array_values(array_column($currentReaders, 'name')),
            'reopen_reader_names' => array_values(array_column($staleReaders, 'name')),
            'blocked_reader_reasons' => array_values(array_unique($blockedReasons)),
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'sync_sequence' => $ready
                ? ['database', 'wal-header', 'directory', 'hot-journal-delete']
                : ['database', 'wal-header-pending'],
            'operation_names' => array_values(array_unique(array_merge(
                is_array($passivePlan['operation_names'] ?? null) ? $passivePlan['operation_names'] : [],
                [
                    'verify_restart_checkpoint_reader_release_current_source_next214',
                    $ready ? 'restart_wal_after_hot_journal_savepoint_checkpoint_next214' : 'preserve_wal_until_restart_checkpoint_safe_next214',
                ]
            ))),
            'restart_digest' => hash('sha256', json_encode([$readerRows, $guards, $saltBefore, $saltAfter], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($passivePlan['dependencies'] ?? null) ? $passivePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next214',
                    'sqlite-restart-checkpoint-current-source-reader-release',
                    'wordpress-import-restart-checkpoint-deletes-hot-journal-after-wal-reset',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next212 passive checkpoint current-source metadata, WAL salt digests, and lane-local VFS sync/delete receipts',
            'non_overlap' => 'next214 models RESTART checkpoint admission after reader release and durable WAL-header salt rotation; it does not repeat next212 PASSIVE reader pins, next209 writer fences, next206 statement consumers, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, WAL byte truncation, or WAL file writer wrappers',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next214 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next214 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestOption(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next214 requires {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $reader
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $reader,
        int $requestedFrame,
        string $databaseDigest,
        string $walDigest,
        string $writerDigest,
        int $writerGeneration
    ): array {
        $name = $reader['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next214 reader name is required');
        }
        $released = $reader['released'] ?? null;
        $endFrame = $reader['reader_end_frame'] ?? null;
        $generation = $reader['reader_generation'] ?? null;
        if (!is_bool($released) || !is_int($endFrame) || $endFrame <= 0 || !is_int($generation) || $generation < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next214 {$name} reader release/frame/generation is invalid");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($reader[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $reader[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next214 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if ($generation !== $writerGeneration) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if ($endFrame > $requestedFrame) {
            $reasons[] = 'reader_end_frame_after_requested_checkpoint';
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
        if (!$released && $reasons === []) {
            $reasons[] = 'current_reader_not_released';
        }

        $pinsCurrentSource = in_array('current_reader_not_released', $reasons, true);
        $admitted = $released && $reasons === [];

        return [
            'name' => $name,
            'released' => $released,
            'reader_end_frame' => $endFrame,
            'reader_generation' => $generation,
            'expected_generation' => $writerGeneration,
            'admitted' => $admitted,
            'pins_current_source' => $pinsCurrentSource,
            'reader_action' => $admitted ? 'released_before_restart_checkpoint' : ($pinsCurrentSource ? 'preserve_wal_for_reader' : 'reopen_reader_before_restart_checkpoint'),
            'blocked_reasons' => $reasons,
            'dirty' => ($reader['dirty'] ?? false) === true,
        ];
    }
}

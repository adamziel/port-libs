<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext212Plan
{
    /**
     * @param array<string,mixed> $writerPlan
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function passiveCheckpoint(array $writerPlan, array $readers, int $requestedFrame): array
    {
        if (($writerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next209') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next212 requires an admitted next209 writer plan');
        }
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next212 requires reader rows');
        }
        if ($requestedFrame <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next212 requires a positive checkpoint frame');
        }

        $databaseDigest = self::digestField($writerPlan, 'checkpointed_database_digest');
        $walDigest = self::digestField($writerPlan, 'expected_wal_digest');
        $writerDigest = self::digestField($writerPlan, 'writer_digest');
        $nextWriterGeneration = self::positiveInt($writerPlan, 'next_writer_generation');
        $minimumStatementGeneration = self::nonNegativeInt($writerPlan, 'minimum_statement_generation');
        $admittedWriters = self::stringList($writerPlan, 'admitted_writer_names');
        $reopenWriters = self::stringList($writerPlan, 'reopen_writer_names');

        $readerRows = [];
        foreach ($readers as $reader) {
            $readerRows[] = self::readerRow(
                $reader,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $nextWriterGeneration,
                $minimumStatementGeneration,
                $requestedFrame
            );
        }

        $activePins = array_values(array_filter($readerRows, static fn (array $row): bool => $row['pins_current_source']));
        $staleReaders = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));
        $checkpointedFrame = $activePins === []
            ? $requestedFrame
            : min(array_column($activePins, 'reader_end_frame'));
        $busy = $checkpointedFrame < $requestedFrame;
        $blockedReasons = [];
        foreach ($staleReaders as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }

        $guardRows = [
            [
                'name' => 'next209_writer_generation_admitted',
                'matched' => $admittedWriters !== [] && $reopenWriters !== [],
                'reason' => 'passive checkpoint follows the mixed current/stale writer fence from next209',
            ],
            [
                'name' => 'checkpoint_frame_not_before_statement_generation',
                'matched' => $requestedFrame >= $minimumStatementGeneration,
                'reason' => 'checkpoint may not publish a frame older than the current statement generation',
            ],
            [
                'name' => 'active_reader_pin_detected',
                'matched' => $activePins !== [],
                'reason' => 'PASSIVE checkpoint must report partial progress when a current reader pins the source',
            ],
            [
                'name' => 'stale_readers_reopened',
                'matched' => $staleReaders !== [],
                'reason' => 'stale readers are not allowed to advance the current-source checkpoint',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        return [
            'status' => $blockedGuards === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next212'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next212',
            'reason' => $blockedGuards === []
                ? 'passive_checkpoint_stops_at_current_reader_pin_after_hot_journal_recovery'
                : 'passive_checkpoint_waits_for_current_source_reader_reopen',
            'base_status' => $writerPlan['status'],
            'database_path' => $writerPlan['database_path'] ?? null,
            'journal_path' => $writerPlan['journal_path'] ?? null,
            'wal_path' => $writerPlan['wal_path'] ?? null,
            'page_size' => $writerPlan['page_size'] ?? null,
            'requested_checkpoint_frame' => $requestedFrame,
            'checkpointed_frame' => $checkpointedFrame,
            'busy' => $busy,
            'wal_action' => $busy ? 'preserve_wal' : 'passive_checkpoint_complete',
            'database_action' => 'write_frames_through_' . $checkpointedFrame,
            'reset_allowed' => false,
            'truncate_allowed' => false,
            'database_digest' => $databaseDigest,
            'wal_digest' => $walDigest,
            'writer_digest' => $writerDigest,
            'next_writer_generation' => $nextWriterGeneration,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'reader_rows' => $readerRows,
            'active_reader_names' => array_values(array_column($activePins, 'name')),
            'reopen_reader_names' => array_values(array_column($staleReaders, 'name')),
            'blocked_reader_reasons' => array_values(array_unique($blockedReasons)),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($writerPlan['operation_names'] ?? null) ? $writerPlan['operation_names'] : [],
                [
                    'verify_passive_checkpoint_reader_pin_current_source_next212',
                    $busy ? 'preserve_wal_for_pinned_reader_next212' : 'complete_passive_checkpoint_next212',
                ]
            ))),
            'checkpoint_digest' => hash('sha256', json_encode([$checkpointedFrame, $busy, $readerRows], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($writerPlan['dependencies'] ?? null) ? $writerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212',
                    'sqlite-passive-checkpoint-current-reader-pin-after-hot-journal',
                    'wordpress-import-passive-checkpoint-preserves-wal-for-current-reader',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next209 writer generation metadata, current-source digests, and reader end-frame pins',
            'non_overlap' => 'next212 models PASSIVE checkpoint partial progress after next209 writer admission; it does not repeat next209 writer fences, next206 statement consumers, restart/truncate checkpoint reset, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or WAL file writing',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 requires positive {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 requires non-negative {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $reader
     * @return array<string,mixed>
     */
    private static function readerRow(
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
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next212 reader name is required');
        }
        $endFrame = $reader['reader_end_frame'] ?? null;
        $generation = $reader['reader_generation'] ?? null;
        if (!is_int($endFrame) || $endFrame <= 0 || !is_int($generation) || $generation < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 {$name} reader frame/generation is invalid");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($reader[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $reader[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next212 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if ($generation !== $nextWriterGeneration) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if ($endFrame < $minimumStatementGeneration) {
            $reasons[] = 'reader_end_frame_before_current_statement';
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
        if (($reader['closed'] ?? false) === true) {
            $reasons[] = 'reader_handle_closed';
        }

        $admitted = $reasons === [];

        return [
            'name' => $name,
            'reader_end_frame' => $endFrame,
            'reader_generation' => $generation,
            'expected_generation' => $nextWriterGeneration,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_writer_digest' => $writerDigest,
            'admitted' => $admitted,
            'pins_current_source' => $admitted,
            'reader_reason' => $admitted ? 'reader_pins_current_source_for_passive_checkpoint' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
            'dirty' => ($reader['dirty'] ?? false) === true,
            'closed' => ($reader['closed'] ?? false) === true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext216Plan
{
    /**
     * @param array<string,mixed> $passivePlan
     * @param list<array<string,mixed>> $readerTransitions
     * @return array<string,mixed>
     */
    public static function restartOrTruncateAfterReaderDrain(array $passivePlan, array $readerTransitions, string $mode): array
    {
        if (($passivePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next216 requires an admitted next212 passive checkpoint plan');
        }
        if (!in_array($mode, ['RESTART', 'TRUNCATE'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next216 mode must be RESTART or TRUNCATE');
        }
        if ($readerTransitions === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next216 requires reader transition rows');
        }

        $requestedFrame = self::positiveInt($passivePlan, 'requested_checkpoint_frame');
        $checkpointedFrame = self::positiveInt($passivePlan, 'checkpointed_frame');
        $databaseDigest = self::digestField($passivePlan, 'database_digest');
        $walDigest = self::digestField($passivePlan, 'wal_digest');
        $writerDigest = self::digestField($passivePlan, 'writer_digest');
        $nextWriterGeneration = self::positiveInt($passivePlan, 'next_writer_generation');
        $minimumStatementGeneration = self::nonNegativeInt($passivePlan, 'minimum_statement_generation');
        $activeReaders = self::stringList($passivePlan, 'active_reader_names');
        $reopenReaders = self::stringList($passivePlan, 'reopen_reader_names');
        if ($activeReaders === [] || $reopenReaders === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next216 requires active and reopened reader names from next212');
        }

        $transitionRows = [];
        foreach ($readerTransitions as $transition) {
            $transitionRows[] = self::transitionRow(
                $transition,
                $activeReaders,
                $reopenReaders,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $nextWriterGeneration,
                $minimumStatementGeneration,
                $requestedFrame
            );
        }

        $releasedActive = self::namesByRoleAndState($transitionRows, 'active', true);
        $blockedActive = self::namesByRoleAndState($transitionRows, 'active', false);
        $reopenedStale = self::namesByRoleAndState($transitionRows, 'stale', true);
        $blockedStale = self::namesByRoleAndState($transitionRows, 'stale', false);
        $unknownReaders = array_values(array_column(
            array_filter($transitionRows, static fn (array $row): bool => $row['role'] === 'unknown'),
            'name'
        ));

        $guardRows = [
            [
                'name' => 'passive_checkpoint_was_busy',
                'matched' => ($passivePlan['busy'] ?? null) === true && $checkpointedFrame < $requestedFrame,
                'reason' => 'next216 only follows the current-reader pin boundary from next212',
            ],
            [
                'name' => 'all_current_readers_released',
                'matched' => self::sameSet($activeReaders, $releasedActive),
                'reason' => 'RESTART/TRUNCATE may not reset the WAL until every current reader pin is gone',
            ],
            [
                'name' => 'all_stale_readers_reopened',
                'matched' => self::sameSet($reopenReaders, $reopenedStale),
                'reason' => 'stale reader handles must reopen before the post-checkpoint source is published',
            ],
            [
                'name' => 'no_unknown_reader_transitions',
                'matched' => $unknownReaders === [],
                'reason' => 'untracked reader handles cannot participate in the reset/truncate decision',
            ],
            [
                'name' => 'checkpoint_reaches_requested_frame',
                'matched' => $requestedFrame >= $minimumStatementGeneration,
                'reason' => 'the final checkpoint frame must still cover the current statement generation',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blockedGuards === [];
        $nextReaderGeneration = $nextWriterGeneration + 1;
        $resetSalt = substr(hash('sha256', implode('|', [
            $databaseDigest,
            $walDigest,
            $writerDigest,
            (string) $requestedFrame,
            $mode,
            (string) $nextReaderGeneration,
        ])), 0, 16);

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next216'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next216',
            'reason' => $ready
                ? 'restart_or_truncate_checkpoint_publishes_next_source_after_reader_drain'
                : 'restart_or_truncate_checkpoint_waits_for_reader_drain',
            'base_status' => $passivePlan['status'],
            'database_path' => $passivePlan['database_path'] ?? null,
            'journal_path' => $passivePlan['journal_path'] ?? null,
            'wal_path' => $passivePlan['wal_path'] ?? null,
            'page_size' => $passivePlan['page_size'] ?? null,
            'mode' => $mode,
            'requested_checkpoint_frame' => $requestedFrame,
            'previous_checkpointed_frame' => $checkpointedFrame,
            'checkpointed_frame' => $ready ? $requestedFrame : $checkpointedFrame,
            'busy' => !$ready,
            'reset_allowed' => $ready,
            'truncate_allowed' => $ready && $mode === 'TRUNCATE',
            'wal_action' => $ready
                ? ($mode === 'TRUNCATE' ? 'truncate_wal_after_reader_drain' : 'restart_wal_header_after_reader_drain')
                : 'preserve_wal_until_reader_drain',
            'database_action' => $ready ? 'write_frames_through_' . $requestedFrame : 'keep_frames_through_' . $checkpointedFrame,
            'next_reader_generation' => $nextReaderGeneration,
            'reset_salt' => $ready ? $resetSalt : null,
            'database_digest' => $databaseDigest,
            'wal_digest_before_reset' => $walDigest,
            'writer_digest' => $writerDigest,
            'next_writer_generation' => $nextWriterGeneration,
            'minimum_statement_generation' => $minimumStatementGeneration,
            'active_reader_names' => $activeReaders,
            'reopen_reader_names' => $reopenReaders,
            'released_active_reader_names' => $releasedActive,
            'blocked_active_reader_names' => $blockedActive,
            'reopened_stale_reader_names' => $reopenedStale,
            'blocked_stale_reader_names' => $blockedStale,
            'unknown_reader_names' => $unknownReaders,
            'reader_transition_rows' => $transitionRows,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($passivePlan['operation_names'] ?? null) ? $passivePlan['operation_names'] : [],
                [
                    'verify_reader_drain_before_restart_truncate_current_source_next216',
                    $ready && $mode === 'TRUNCATE'
                        ? 'truncate_wal_after_reader_drain_next216'
                        : ($ready ? 'restart_wal_after_reader_drain_next216' : 'preserve_wal_until_reader_drain_next216'),
                ]
            ))),
            'checkpoint_digest' => hash('sha256', json_encode([$mode, $ready, $requestedFrame, $transitionRows], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($passivePlan['dependencies'] ?? null) ? $passivePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next216',
                    'sqlite-restart-truncate-after-hot-journal-reader-drain',
                    'wordpress-import-checkpoint-reset-after-reader-drain',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next212 passive checkpoint pins, writer generation digests, and reader reopen metadata',
            'non_overlap' => 'next216 only models RESTART/TRUNCATE admission after next212 reader drain; it does not repeat WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal apply/commit, hot-journal recovery, or passive checkpoint pin detection',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 requires positive {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 requires non-negative {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $transition
     * @param list<string> $activeReaders
     * @param list<string> $reopenReaders
     * @return array<string,mixed>
     */
    private static function transitionRow(
        array $transition,
        array $activeReaders,
        array $reopenReaders,
        string $databaseDigest,
        string $walDigest,
        string $writerDigest,
        int $nextWriterGeneration,
        int $minimumStatementGeneration,
        int $requestedFrame
    ): array {
        $name = $transition['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next216 reader transition name is required');
        }
        $role = in_array($name, $activeReaders, true)
            ? 'active'
            : (in_array($name, $reopenReaders, true) ? 'stale' : 'unknown');
        $generation = $transition['reader_generation'] ?? null;
        $endFrame = $transition['reader_end_frame'] ?? null;
        if (!is_int($generation) || $generation < 0 || !is_int($endFrame) || $endFrame < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 {$name} generation/frame is invalid");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($transition[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $transition[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next216 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if ($role === 'active' && ($transition['released'] ?? false) !== true) {
            $reasons[] = 'current_reader_not_released';
        }
        if ($role === 'stale' && ($transition['reopened'] ?? false) !== true) {
            $reasons[] = 'stale_reader_not_reopened';
        }
        if ($role === 'unknown') {
            $reasons[] = 'reader_not_tracked_by_passive_checkpoint';
        }
        if ($generation !== $nextWriterGeneration) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if ($endFrame < $minimumStatementGeneration) {
            $reasons[] = 'reader_end_frame_before_current_statement';
        }
        if ($endFrame > $requestedFrame) {
            $reasons[] = 'reader_end_frame_after_requested_checkpoint';
        }
        if (!hash_equals($databaseDigest, (string) $transition['observed_database_digest'])) {
            $reasons[] = 'reader_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $transition['observed_wal_digest'])) {
            $reasons[] = 'reader_wal_digest_mismatch';
        }
        if (!hash_equals($writerDigest, (string) $transition['observed_writer_digest'])) {
            $reasons[] = 'reader_writer_digest_mismatch';
        }
        if (($transition['dirty'] ?? false) === true) {
            $reasons[] = 'reader_cache_dirty';
        }
        if (($transition['closed'] ?? false) !== true) {
            $reasons[] = 'reader_handle_not_closed_for_reset';
        }

        return [
            'name' => $name,
            'role' => $role,
            'released' => ($transition['released'] ?? false) === true,
            'reopened' => ($transition['reopened'] ?? false) === true,
            'closed' => ($transition['closed'] ?? false) === true,
            'dirty' => ($transition['dirty'] ?? false) === true,
            'reader_generation' => $generation,
            'reader_end_frame' => $endFrame,
            'expected_generation' => $nextWriterGeneration,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_writer_digest' => $writerDigest,
            'blocked_reasons' => $reasons,
            'admitted_for_reset' => $reasons === [],
            'transition' => $reasons === []
                ? ($role === 'active' ? 'released_current_reader_pin' : 'reopened_stale_reader_handle')
                : implode('|', $reasons),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function namesByRoleAndState(array $rows, string $role, bool $admitted): array
    {
        return array_values(array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['role'] === $role && $row['admitted_for_reset'] === $admitted
        ), 'name'));
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }
}

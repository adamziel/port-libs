<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext213Plan
{
    /**
     * @param array<string,mixed> $passivePlan
     * @param list<array<string,mixed>> $receipts
     * @return array<string,mixed>
     */
    public static function restartAdmission(array $passivePlan, array $receipts, int $targetFrame): array
    {
        if (($passivePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next213 requires an admitted next212 passive checkpoint plan');
        }
        if ($receipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next213 requires reopen receipts');
        }
        if ($targetFrame <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next213 requires a positive target frame');
        }

        $databaseDigest = self::digestField($passivePlan, 'database_digest');
        $walDigest = self::digestField($passivePlan, 'wal_digest');
        $writerDigest = self::digestField($passivePlan, 'writer_digest');
        $checkpointDigest = self::digestField($passivePlan, 'checkpoint_digest');
        $requestedFrame = self::positiveInt($passivePlan, 'requested_checkpoint_frame');
        $checkpointedFrame = self::positiveInt($passivePlan, 'checkpointed_frame');
        $writerGeneration = self::positiveInt($passivePlan, 'next_writer_generation');
        $minimumGeneration = self::nonNegativeInt($passivePlan, 'minimum_statement_generation');
        $activeReaders = self::stringList($passivePlan, 'active_reader_names');
        $reopenReaders = self::stringList($passivePlan, 'reopen_reader_names');

        $rows = [];
        foreach ($receipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $databaseDigest,
                $walDigest,
                $writerDigest,
                $checkpointDigest,
                $writerGeneration,
                $minimumGeneration,
                $targetFrame
            );
        }

        $admittedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['admitted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $admittedNames = array_values(array_column($admittedRows, 'name'));
        $blockedNames = array_values(array_column($blockedRows, 'name'));
        $missingReopens = array_values(array_diff($reopenReaders, $admittedNames));
        $unexpectedPins = array_values(array_intersect($activeReaders, $admittedNames));
        $targetComplete = $targetFrame >= $requestedFrame && $checkpointedFrame < $requestedFrame;
        $receiptReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $receiptReasons[] = $reason;
            }
        }

        $guardRows = [
            [
                'name' => 'next212_partial_passive_checkpoint',
                'matched' => ($passivePlan['busy'] ?? null) === true && ($passivePlan['wal_action'] ?? null) === 'preserve_wal',
                'reason' => 'restart admission follows a PASSIVE checkpoint that preserved WAL bytes for a pinned reader',
            ],
            [
                'name' => 'target_frame_reaches_requested_checkpoint',
                'matched' => $targetComplete,
                'reason' => 'restart admission may only reset after the target frame reaches the original requested checkpoint frame',
            ],
            [
                'name' => 'stale_reader_reopen_receipts_complete',
                'matched' => $missingReopens === [],
                'reason' => 'every stale reader from next212 must reopen on the current hot-journal-free source',
            ],
            [
                'name' => 'active_reader_pins_not_reused_for_reset',
                'matched' => $unexpectedPins === [],
                'reason' => 'reader handles that pinned the PASSIVE checkpoint cannot be reused to authorize a reset',
            ],
            [
                'name' => 'receipt_rows_current_source_clean',
                'matched' => $blockedRows === [],
                'reason' => 'all reopen receipts must match database/WAL/writer/checkpoint digests and be clean',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blockedGuards === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next213'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next213',
            'reason' => $ready
                ? 'restart_checkpoint_admits_only_reopened_hot_journal_free_reader_receipts'
                : 'restart_checkpoint_waits_for_reopened_current_source_reader_receipts',
            'base_status' => $passivePlan['status'],
            'database_path' => $passivePlan['database_path'] ?? null,
            'journal_path' => $passivePlan['journal_path'] ?? null,
            'wal_path' => $passivePlan['wal_path'] ?? null,
            'page_size' => $passivePlan['page_size'] ?? null,
            'requested_checkpoint_frame' => $requestedFrame,
            'passive_checkpointed_frame' => $checkpointedFrame,
            'target_checkpoint_frame' => $targetFrame,
            'target_complete' => $targetComplete,
            'restart_allowed' => $ready,
            'reset_allowed' => $ready,
            'truncate_allowed' => false,
            'wal_action' => $ready ? 'restart_wal_after_reopened_readers' : 'preserve_wal_until_reopen',
            'database_action' => $ready ? 'write_frames_through_' . $targetFrame : 'write_frames_through_' . $checkpointedFrame,
            'database_digest' => $databaseDigest,
            'wal_digest' => $walDigest,
            'writer_digest' => $writerDigest,
            'checkpoint_digest' => $checkpointDigest,
            'next_writer_generation' => $writerGeneration,
            'minimum_statement_generation' => $minimumGeneration,
            'active_reader_names' => $activeReaders,
            'required_reopen_reader_names' => $reopenReaders,
            'admitted_reopen_reader_names' => $admittedNames,
            'blocked_reopen_reader_names' => $blockedNames,
            'missing_reopen_reader_names' => $missingReopens,
            'unexpected_active_reader_receipts' => $unexpectedPins,
            'receipt_rows' => $rows,
            'receipt_blocked_reasons' => array_values(array_unique($receiptReasons)),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($passivePlan['operation_names'] ?? null) ? $passivePlan['operation_names'] : [],
                [
                    'verify_restart_checkpoint_reader_reopen_receipts_current_source_next213',
                    $ready ? 'restart_wal_after_reopened_reader_receipts_next213' : 'preserve_wal_until_reopen_receipts_next213',
                ]
            ))),
            'restart_receipt_digest' => hash('sha256', json_encode([$targetFrame, $rows, $blockedGuards], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($passivePlan['dependencies'] ?? null) ? $passivePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next213',
                    'sqlite-restart-checkpoint-reader-reopen-receipts-after-hot-journal',
                    'wordpress-import-restart-checkpoint-reopens-stale-readers',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next212 passive checkpoint source digests, reader pin names, and lane-local reopen receipts',
            'non_overlap' => 'next213 models restart-checkpoint admission receipts after next212 PASSIVE partial progress; it does not repeat next212 passive frame selection, next209 writer fences, restart/truncate byte reset, VFS writer application, rollback-journal apply/commit, or WAL byte truncation',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 requires positive {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 requires non-negative {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 requires {$key}");
        }
        foreach ($list as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 {$key} must contain non-empty strings");
            }
        }

        return array_values($list);
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function receiptRow(
        array $receipt,
        string $databaseDigest,
        string $walDigest,
        string $writerDigest,
        string $checkpointDigest,
        int $writerGeneration,
        int $minimumGeneration,
        int $targetFrame
    ): array {
        $name = $receipt['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next213 receipt name is required');
        }
        $frame = $receipt['reader_end_frame'] ?? null;
        $generation = $receipt['reader_generation'] ?? null;
        if (!is_int($frame) || $frame <= 0 || !is_int($generation) || $generation < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 {$name} frame/generation is invalid");
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest', 'observed_checkpoint_digest'] as $key) {
            if (!is_string($receipt[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $receipt[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if ($generation !== $writerGeneration) {
            $reasons[] = 'receipt_generation_mismatch';
        }
        if ($frame < $minimumGeneration) {
            $reasons[] = 'receipt_frame_before_current_statement';
        }
        if ($frame < $targetFrame) {
            $reasons[] = 'receipt_frame_before_restart_target';
        }
        if ($frame > $targetFrame) {
            $reasons[] = 'receipt_frame_after_restart_target';
        }
        if (!hash_equals($databaseDigest, (string) $receipt['observed_database_digest'])) {
            $reasons[] = 'receipt_database_digest_mismatch';
        }
        if (!hash_equals($walDigest, (string) $receipt['observed_wal_digest'])) {
            $reasons[] = 'receipt_wal_digest_mismatch';
        }
        if (!hash_equals($writerDigest, (string) $receipt['observed_writer_digest'])) {
            $reasons[] = 'receipt_writer_digest_mismatch';
        }
        if (!hash_equals($checkpointDigest, (string) $receipt['observed_checkpoint_digest'])) {
            $reasons[] = 'receipt_checkpoint_digest_mismatch';
        }
        if (($receipt['hot_journal_digest'] ?? null) !== null) {
            if (!is_string($receipt['hot_journal_digest']) || !preg_match('/^[a-f0-9]{64}$/', $receipt['hot_journal_digest'])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next213 {$name} hot journal digest must be a sha256 string or null");
            }
            $reasons[] = 'receipt_retains_hot_journal_digest';
        }
        if (($receipt['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'receipt_savepoint_scope_not_closed';
        }
        if (($receipt['lock_receipt'] ?? false) !== true) {
            $reasons[] = 'receipt_missing_shared_lock';
        }
        if (($receipt['dirty'] ?? false) === true) {
            $reasons[] = 'receipt_cache_dirty';
        }
        if (($receipt['closed'] ?? false) === true) {
            $reasons[] = 'receipt_handle_closed';
        }

        $admitted = $reasons === [];

        return [
            'name' => $name,
            'reader_end_frame' => $frame,
            'reader_generation' => $generation,
            'expected_generation' => $writerGeneration,
            'expected_target_frame' => $targetFrame,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_writer_digest' => $writerDigest,
            'expected_checkpoint_digest' => $checkpointDigest,
            'admitted' => $admitted,
            'receipt_reason' => $admitted ? 'reader_reopened_on_current_source_for_restart_checkpoint' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
            'hot_journal_retained' => ($receipt['hot_journal_digest'] ?? null) !== null,
            'savepoint_depth' => (int) ($receipt['savepoint_depth'] ?? 0),
            'lock_receipt' => ($receipt['lock_receipt'] ?? false) === true,
            'dirty' => ($receipt['dirty'] ?? false) === true,
            'closed' => ($receipt['closed'] ?? false) === true,
        ];
    }
}

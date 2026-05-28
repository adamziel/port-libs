<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext223Plan
{
    /**
     * @param array<string,mixed> $resetPlan
     * @param list<array<string,mixed>> $receipts
     * @return array<string,mixed>
     */
    public static function publishCurrentSource(array $resetPlan, array $receipts, int $nextSourceEpoch): array
    {
        if (($resetPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next218') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 requires an admitted next218 reset plan');
        }
        if (($resetPlan['can_reset_wal'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 requires reset-admitted WAL state');
        }
        if ($receipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 requires publication receipts');
        }
        if ($nextSourceEpoch <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 requires a positive next source epoch');
        }

        $databaseDigest = self::digestField($resetPlan, 'database_digest');
        $walDigest = self::digestField($resetPlan, 'wal_digest');
        $writerDigest = self::digestField($resetPlan, 'writer_digest');
        $checkpointedFrame = self::positiveInt($resetPlan, 'checkpointed_frame');
        $writerGeneration = self::positiveInt($resetPlan, 'next_writer_generation');
        $mode = $resetPlan['mode'] ?? null;
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 requires restart or truncate mode');
        }

        $receiptRows = [];
        foreach ($receipts as $receipt) {
            $receiptRows[] = self::receiptRow($receipt, $databaseDigest, $walDigest, $writerDigest, $checkpointedFrame, $writerGeneration);
        }

        $admitted = array_values(array_filter($receiptRows, static fn (array $row): bool => $row['admitted']));
        $blocked = array_values(array_filter($receiptRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blocked as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }

        $requiredRoles = ['database', 'wal', 'journal', 'reader-cache'];
        $admittedRoles = array_values(array_unique(array_column($admitted, 'role')));
        $missingRoles = array_values(array_diff($requiredRoles, $admittedRoles));
        $canPublish = $blocked === [] && $missingRoles === [];

        $guardRows = [
            [
                'name' => 'next218_reset_admitted',
                'matched' => true,
                'reason' => 'publication follows a reset-admitted next218 restart/truncate checkpoint',
            ],
            [
                'name' => 'all_publication_receipts_current',
                'matched' => $blocked === [],
                'reason' => 'database, WAL, journal, and reader-cache receipts must all match the checkpoint generation',
            ],
            [
                'name' => 'required_publication_roles_present',
                'matched' => $missingRoles === [],
                'reason' => 'current-source publication needs database, WAL, journal, and reader-cache receipt roles',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));

        return [
            'status' => $canPublish
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next223'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next223',
            'reason' => $canPublish
                ? 'checkpoint_reset_publication_receipts_advance_current_source'
                : 'checkpoint_reset_publication_waits_for_hot_journal_savepoint_receipts',
            'base_status' => $resetPlan['status'],
            'mode' => $mode,
            'database_path' => $resetPlan['database_path'] ?? null,
            'journal_path' => $resetPlan['journal_path'] ?? null,
            'wal_path' => $resetPlan['wal_path'] ?? null,
            'page_size' => $resetPlan['page_size'] ?? null,
            'checkpointed_frame' => $checkpointedFrame,
            'next_source_epoch' => $nextSourceEpoch,
            'database_digest' => $databaseDigest,
            'wal_digest' => $walDigest,
            'writer_digest' => $writerDigest,
            'writer_generation' => $writerGeneration,
            'receipt_rows' => $receiptRows,
            'admitted_receipt_names' => array_values(array_column($admitted, 'name')),
            'blocked_receipt_names' => array_values(array_column($blocked, 'name')),
            'blocked_receipt_reasons' => array_values(array_unique($blockedReasons)),
            'required_roles' => $requiredRoles,
            'admitted_roles' => $admittedRoles,
            'missing_roles' => $missingRoles,
            'publication_allowed' => $canPublish,
            'current_source_action' => $canPublish ? 'advance_current_source_epoch_' . $nextSourceEpoch : 'preserve_previous_current_source_epoch',
            'reader_cache_action' => $canPublish ? 'drop_reopened_reader_cache_images' : 'retain_reader_cache_until_receipts_reopen',
            'journal_action' => $canPublish ? 'forget_hot_journal_generation_after_receipt' : 'retain_hot_journal_generation_fence',
            'wal_action' => $canPublish ? (string) ($resetPlan['wal_action'] ?? 'publish_wal_reset') : 'preserve_wal_reset_publication_fence',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($resetPlan['operation_names'] ?? null) ? $resetPlan['operation_names'] : [],
                [
                    'verify_checkpoint_reset_publication_current_source_next223',
                    $canPublish ? 'advance_checkpoint_current_source_epoch_next223' : 'preserve_checkpoint_publication_fence_next223',
                ]
            ))),
            'publication_digest' => hash('sha256', json_encode([$mode, $nextSourceEpoch, $receiptRows], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($resetPlan['dependencies'] ?? null) ? $resetPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next223',
                    'sqlite-checkpoint-reset-publication-receipts',
                    'wordpress-import-checkpoint-current-source-publication',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next218 reset admission, current-source digests, writer generation receipts, and reader-cache reopen fences',
            'non_overlap' => 'next223 verifies post-reset publication receipts before advancing current-source epoch; it does not repeat next218 restart/truncate reset admission, next212 reader-pin PASSIVE progress, next209 writer fences, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next223 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next223 requires positive {$key}");
        }

        return $value;
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
        int $checkpointedFrame,
        int $writerGeneration
    ): array {
        $name = $receipt['name'] ?? null;
        $role = $receipt['role'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($role) || $role === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next223 receipt name and role are required');
        }
        foreach (['observed_database_digest', 'observed_wal_digest', 'observed_writer_digest'] as $key) {
            if (!is_string($receipt[$key] ?? null) || !preg_match('/^[a-f0-9]{64}$/', (string) $receipt[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next223 {$name} {$key} is required");
            }
        }
        $frame = $receipt['checkpoint_frame'] ?? null;
        $generation = $receipt['writer_generation'] ?? null;
        if (!is_int($frame) || $frame <= 0 || !is_int($generation) || $generation <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next223 {$name} frame/generation is invalid");
        }

        $reasons = [];
        if ($frame !== $checkpointedFrame) {
            $reasons[] = 'receipt_checkpoint_frame_mismatch';
        }
        if ($generation !== $writerGeneration) {
            $reasons[] = 'receipt_writer_generation_mismatch';
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
        if (($receipt['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'receipt_hot_journal_still_present';
        }
        if (($receipt['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'receipt_savepoint_scope_not_closed';
        }
        if (($receipt['reader_cache_dirty'] ?? false) === true) {
            $reasons[] = 'receipt_reader_cache_dirty';
        }
        if (($receipt['sync_receipt'] ?? false) !== true) {
            $reasons[] = 'receipt_missing_sync_receipt';
        }

        return [
            'name' => $name,
            'role' => $role,
            'checkpoint_frame' => $frame,
            'expected_checkpoint_frame' => $checkpointedFrame,
            'writer_generation' => $generation,
            'expected_writer_generation' => $writerGeneration,
            'expected_database_digest' => $databaseDigest,
            'expected_wal_digest' => $walDigest,
            'expected_writer_digest' => $writerDigest,
            'observed_database_digest' => (string) $receipt['observed_database_digest'],
            'observed_wal_digest' => (string) $receipt['observed_wal_digest'],
            'observed_writer_digest' => (string) $receipt['observed_writer_digest'],
            'hot_journal_present' => ($receipt['hot_journal_present'] ?? false) === true,
            'savepoint_depth' => (int) ($receipt['savepoint_depth'] ?? 0),
            'reader_cache_dirty' => ($receipt['reader_cache_dirty'] ?? false) === true,
            'sync_receipt' => ($receipt['sync_receipt'] ?? false) === true,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons[0] ?? 'receipt_can_publish_current_source',
            'blocked_reasons' => $reasons,
        ];
    }
}

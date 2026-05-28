<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext228Plan
{
    /**
     * @param array<string,mixed> $publication
     * @param list<array<string,mixed>> $barriers
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function admitDurableSource(array $publication, array $barriers, array $readers, string $sourceToken): array
    {
        if (($publication['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next224') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 requires a published next224 reset source');
        }
        if (($publication['publication_allowed'] ?? null) !== true || ($publication['checkpoint_reset_visible'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 requires visible reset publication');
        }
        if ($barriers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 requires durability barriers');
        }
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 requires reader receipts');
        }

        $expectedToken = self::token($publication['source_token'] ?? null, 'publication source token');
        $requestedToken = self::token($sourceToken, 'requested source token');
        if ($requestedToken !== $expectedToken) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 source token mismatch');
        }

        $generation = self::positiveInt($publication, 'next_writer_generation');
        $databaseDigest = self::digest($publication['database_digest'] ?? null, 'database digest');
        $mode = self::mode($publication['mode'] ?? null);

        $barrierRows = [];
        foreach ($barriers as $barrier) {
            $barrierRows[] = self::barrierRow($barrier, $mode, $generation, $databaseDigest, $expectedToken);
        }

        $readerRows = [];
        foreach ($readers as $reader) {
            $readerRows[] = self::readerRow($reader, $generation, $expectedToken);
        }

        $requiredBarriers = ['database_sync', 'wal_reset_sync', 'journal_unlink_dir_sync', 'shm_lock_epoch', 'savepoint_release'];
        $barrierTypes = array_values(array_unique(array_column($barrierRows, 'type')));
        $missingBarriers = array_values(array_diff($requiredBarriers, $barrierTypes));
        $blockedBarriers = array_values(array_filter($barrierRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReaders = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));

        $guardRows = [
            [
                'name' => 'next224_publication_visible',
                'matched' => true,
                'reason' => 'next224 already verified reset sidecars and reader reopen admission',
            ],
            [
                'name' => 'required_durability_barriers_present',
                'matched' => $missingBarriers === [],
                'reason' => 'database sync, WAL reset sync, journal unlink directory sync, SHM lock epoch, and savepoint release barriers are required',
            ],
            [
                'name' => 'durability_barriers_match_current_source',
                'matched' => $blockedBarriers === [],
                'reason' => 'barrier receipts must match source token, generation, mode, database digest, lock epoch, and durable sync ordering',
            ],
            [
                'name' => 'readers_observe_durable_source',
                'matched' => $blockedReaders === [],
                'reason' => 'readers may reuse a handle only after observing the durable source token and generation',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next228'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next228',
            'reason' => $admitted
                ? 'durable_checkpoint_source_barriers_admit_reopened_readers'
                : 'durable_checkpoint_source_waits_for_barrier_or_reader_receipts',
            'base_status' => $publication['status'],
            'mode' => $mode,
            'source_token' => $expectedToken,
            'next_writer_generation' => $generation,
            'database_digest' => $databaseDigest,
            'barrier_rows' => $barrierRows,
            'reader_rows' => $readerRows,
            'admitted_barrier_names' => array_values(array_column(array_filter($barrierRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_barrier_names' => array_values(array_column($blockedBarriers, 'name')),
            'blocked_barrier_reasons' => self::reasonList($blockedBarriers),
            'missing_barrier_types' => $missingBarriers,
            'admitted_reader_names' => array_values(array_column(array_filter($readerRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedReaders, 'name')),
            'blocked_reader_reasons' => self::reasonList($blockedReaders),
            'current_source_admitted' => $admitted,
            'checkpoint_reusable_by_readers' => $admitted,
            'next_writer_action' => $admitted ? 'start_after_durable_checkpoint_source' : 'wait_for_durable_checkpoint_source',
            'reader_action' => $admitted ? 'reuse_reopened_durable_source_readers' : 'reopen_after_durability_barrier',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publication['operation_names'] ?? null) ? $publication['operation_names'] : [],
                [
                    'verify_durable_checkpoint_source_barriers_current_source_next228',
                    $admitted ? 'admit_durable_checkpoint_source_current_source_next228' : 'defer_durable_checkpoint_source_current_source_next228',
                ]
            ))),
            'admission_digest' => hash('sha256', json_encode([$expectedToken, $mode, $generation, $barrierRows, $readerRows, $blockedGuards], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publication['dependencies'] ?? null) ? $publication['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next228',
                    'sqlite-durable-checkpoint-source-barriers',
                    'wordpress-import-reopened-reader-durable-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next224 reset publication metadata, VFS sync receipts, SHM lock epochs, and savepoint release receipts',
            'non_overlap' => 'next228 verifies post-publication durability barriers and reopened-reader reuse; it does not repeat next224 sidecar publication receipts, next218 reset admission, WAL byte truncation, VFS writer/sync apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $barrier
     * @return array<string,mixed>
     */
    private static function barrierRow(array $barrier, string $mode, int $generation, string $databaseDigest, string $sourceToken): array
    {
        $name = self::token($barrier['name'] ?? null, 'barrier name');
        $type = self::token($barrier['type'] ?? null, "{$name} barrier type");
        if (!in_array($type, ['database_sync', 'wal_reset_sync', 'journal_unlink_dir_sync', 'shm_lock_epoch', 'savepoint_release'], true)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next228 {$name} barrier type is invalid");
        }
        $observedToken = self::token($barrier['source_token'] ?? null, "{$name} source token");
        $observedGeneration = self::nonNegativeInt($barrier['generation'] ?? null, "{$name} generation");
        $syncOrder = self::nonNegativeInt($barrier['sync_order'] ?? null, "{$name} sync order");
        $receipt = ($barrier['receipt'] ?? false) === true;
        $exclusive = ($barrier['exclusive_lock'] ?? false) === true;
        $digest = isset($barrier['database_digest']) ? self::digest($barrier['database_digest'], "{$name} database digest") : null;
        $observedMode = isset($barrier['mode']) ? self::mode($barrier['mode']) : $mode;
        $savepointReleased = ($barrier['savepoint_released'] ?? false) === true;
        $journalUnlinked = ($barrier['journal_unlinked'] ?? false) === true;
        $walReset = ($barrier['wal_reset'] ?? false) === true;

        $reasons = [];
        if (!$receipt) {
            $reasons[] = 'barrier_receipt_missing';
        }
        if ($observedToken !== $sourceToken) {
            $reasons[] = 'source_token_mismatch';
        }
        if ($observedGeneration !== $generation) {
            $reasons[] = 'generation_mismatch';
        }
        if ($syncOrder <= 0) {
            $reasons[] = 'sync_order_missing';
        }
        if (!$exclusive && in_array($type, ['database_sync', 'wal_reset_sync', 'shm_lock_epoch'], true)) {
            $reasons[] = 'exclusive_lock_missing';
        }
        if ($type === 'database_sync' && $digest !== $databaseDigest) {
            $reasons[] = 'database_digest_mismatch';
        }
        if ($type === 'wal_reset_sync') {
            if (!$walReset) {
                $reasons[] = 'wal_reset_not_observed';
            }
            if ($observedMode !== $mode) {
                $reasons[] = 'wal_reset_mode_mismatch';
            }
        }
        if ($type === 'journal_unlink_dir_sync' && !$journalUnlinked) {
            $reasons[] = 'hot_journal_unlink_not_synced';
        }
        if ($type === 'savepoint_release' && !$savepointReleased) {
            $reasons[] = 'savepoint_not_released';
        }

        return [
            'name' => $name,
            'type' => $type,
            'source_token' => $observedToken,
            'expected_source_token' => $sourceToken,
            'generation' => $observedGeneration,
            'expected_generation' => $generation,
            'mode' => $observedMode,
            'expected_mode' => $mode,
            'sync_order' => $syncOrder,
            'receipt' => $receipt,
            'exclusive_lock' => $exclusive,
            'database_digest' => $digest,
            'wal_reset' => $walReset,
            'journal_unlinked' => $journalUnlinked,
            'savepoint_released' => $savepointReleased,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'durability_barrier_matches_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $reader
     * @return array<string,mixed>
     */
    private static function readerRow(array $reader, int $generation, string $sourceToken): array
    {
        $name = self::token($reader['name'] ?? null, 'reader name');
        $observedToken = self::token($reader['source_token'] ?? null, "{$name} source token");
        $observedGeneration = self::nonNegativeInt($reader['generation'] ?? null, "{$name} generation");
        $reopened = ($reader['reopened'] ?? false) === true;
        $sawBarrier = ($reader['saw_durability_barrier'] ?? false) === true;
        $pinnedOldSource = ($reader['pinned_old_source'] ?? false) === true;

        $reasons = [];
        if (!$reopened) {
            $reasons[] = 'reader_not_reopened';
        }
        if (!$sawBarrier) {
            $reasons[] = 'reader_missed_durability_barrier';
        }
        if ($pinnedOldSource) {
            $reasons[] = 'reader_pins_old_source';
        }
        if ($observedToken !== $sourceToken) {
            $reasons[] = 'reader_source_token_mismatch';
        }
        if ($observedGeneration !== $generation) {
            $reasons[] = 'reader_generation_mismatch';
        }

        return [
            'name' => $name,
            'source_token' => $observedToken,
            'expected_source_token' => $sourceToken,
            'generation' => $observedGeneration,
            'expected_generation' => $generation,
            'reopened' => $reopened,
            'saw_durability_barrier' => $sawBarrier,
            'pinned_old_source' => $pinnedOldSource,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'reader_observes_durable_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next228 requires positive {$key}");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next228 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next228 requires {$label}");
        }

        return $value;
    }

    private static function mode(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next228 reset mode is invalid');
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next228 {$label} is invalid");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function reasonList(array $rows): array
    {
        $reasons = [];
        foreach ($rows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $reasons[] = $reason;
            }
        }

        return array_values(array_unique($reasons));
    }
}

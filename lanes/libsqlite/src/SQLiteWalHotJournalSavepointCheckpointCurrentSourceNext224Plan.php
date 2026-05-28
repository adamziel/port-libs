<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext224Plan
{
    /**
     * @param array<string,mixed> $resetPlan
     * @param list<array<string,mixed>> $sidecars
     * @param list<array<string,mixed>> $readers
     * @return array<string,mixed>
     */
    public static function publishReset(array $resetPlan, array $sidecars, array $readers, string $sourceToken): array
    {
        if (($resetPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next218') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 requires an admitted next218 reset plan');
        }
        if (($resetPlan['can_reset_wal'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 requires reset permission');
        }
        if ($sidecars === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 requires sidecar receipts');
        }
        if ($readers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 requires reader receipts');
        }
        if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $sourceToken)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 source token is invalid');
        }

        $mode = (string) ($resetPlan['mode'] ?? '');
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next224 reset mode is invalid');
        }
        $databaseDigest = self::digestField($resetPlan, 'database_digest');
        $walDigest = self::digestField($resetPlan, 'wal_digest');
        $generation = self::positiveInt($resetPlan, 'next_writer_generation');

        $sidecarRows = [];
        foreach ($sidecars as $receipt) {
            $sidecarRows[] = self::sidecarRow($receipt, $mode, $databaseDigest, $walDigest, $generation);
        }
        $readerRows = [];
        foreach ($readers as $receipt) {
            $readerRows[] = self::readerRow($receipt, $sourceToken, $generation);
        }

        $blockedSidecars = array_values(array_filter($sidecarRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReaders = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));
        $sidecarReasons = self::reasonList($blockedSidecars);
        $readerReasons = self::reasonList($blockedReaders);
        $sidecarTypes = array_values(array_unique(array_column($sidecarRows, 'type')));

        $requiredSidecars = ['database', 'wal', 'journal', 'shm'];
        $missingSidecars = array_values(array_diff($requiredSidecars, $sidecarTypes));
        $guardRows = [
            [
                'name' => 'next218_reset_already_admitted',
                'matched' => true,
                'reason' => 'restart/truncate admission and writer fences were handled by next218',
            ],
            [
                'name' => 'required_sidecar_receipts_present',
                'matched' => $missingSidecars === [],
                'reason' => 'database, wal, journal, and shm receipts must be present before publishing the current source',
            ],
            [
                'name' => 'sidecars_match_reset_publication',
                'matched' => $blockedSidecars === [],
                'reason' => 'sidecar receipts must match reset mode, digests, deletion state, generation, and sync receipts',
            ],
            [
                'name' => 'readers_reopened_or_invalidated',
                'matched' => $blockedReaders === [],
                'reason' => 'readers may observe the new source only after reopening on it or invalidating their old handle',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $published = $blockedGuards === [];

        return [
            'status' => $published
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next224'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next224',
            'reason' => $published
                ? 'restart_truncate_reset_publication_receipts_admit_current_source'
                : 'restart_truncate_reset_publication_waits_for_sidecar_or_reader_receipts',
            'base_status' => $resetPlan['status'],
            'mode' => $mode,
            'database_path' => $resetPlan['database_path'] ?? null,
            'journal_path' => $resetPlan['journal_path'] ?? null,
            'wal_path' => $resetPlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'next_writer_generation' => $generation,
            'database_digest' => $databaseDigest,
            'previous_wal_digest' => $walDigest,
            'sidecar_rows' => $sidecarRows,
            'reader_rows' => $readerRows,
            'admitted_sidecar_names' => array_values(array_column(array_filter($sidecarRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_sidecar_names' => array_values(array_column($blockedSidecars, 'name')),
            'blocked_sidecar_reasons' => $sidecarReasons,
            'admitted_reader_names' => array_values(array_column(array_filter($readerRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedReaders, 'name')),
            'blocked_reader_reasons' => $readerReasons,
            'missing_sidecar_types' => $missingSidecars,
            'publication_allowed' => $published,
            'checkpoint_reset_visible' => $published,
            'wal_publication_action' => $published
                ? ($mode === 'truncate' ? 'publish_zero_length_wal_generation' : 'publish_restarted_wal_header_generation')
                : 'hold_previous_wal_generation',
            'reader_action' => $published ? 'reuse_only_reopened_current_source_readers' : 'force_reader_reopen_before_current_source',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($resetPlan['operation_names'] ?? null) ? $resetPlan['operation_names'] : [],
                [
                    'verify_reset_publication_receipts_current_source_next224',
                    $published ? 'publish_checkpoint_reset_current_source_next224' : 'defer_checkpoint_reset_current_source_next224',
                ]
            ))),
            'publication_digest' => hash('sha256', json_encode([$sourceToken, $mode, $sidecarRows, $readerRows, $blockedGuards], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($resetPlan['dependencies'] ?? null) ? $resetPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next224',
                    'sqlite-wal-reset-publication-sidecar-receipts',
                    'wordpress-import-checkpoint-reset-reader-reopen-publication',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next218 reset admission, sidecar receipt metadata, and reader reopen invalidation receipts',
            'non_overlap' => 'next224 checks publication receipts after next218 reset admission; it does not repeat next218 writer fences, WAL byte truncation, VFS writer/sync/apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function digestField(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 requires {$key}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function sidecarRow(array $receipt, string $mode, string $databaseDigest, string $walDigest, int $generation): array
    {
        $name = self::token($receipt['name'] ?? null, 'sidecar name');
        $type = self::token($receipt['type'] ?? null, "{$name} sidecar type");
        if (!in_array($type, ['database', 'wal', 'journal', 'shm'], true)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$name} sidecar type is invalid");
        }
        $observedGeneration = $receipt['generation'] ?? null;
        if (!is_int($observedGeneration) || $observedGeneration < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$name} generation is invalid");
        }

        $exists = ($receipt['exists'] ?? false) === true;
        $deleted = ($receipt['deleted'] ?? false) === true;
        $synced = ($receipt['sync_receipt'] ?? false) === true;
        $size = $receipt['size'] ?? null;
        if (!is_int($size) || $size < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$name} size is invalid");
        }
        $digest = $receipt['digest'] ?? null;
        if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$name} digest is required");
        }

        $reasons = [];
        if ($type === 'database') {
            if (!$exists || $deleted) {
                $reasons[] = 'database_sidecar_missing';
            }
            if (!hash_equals($databaseDigest, $digest)) {
                $reasons[] = 'database_digest_mismatch';
            }
            if (!$synced) {
                $reasons[] = 'database_sync_missing';
            }
        } elseif ($type === 'wal') {
            if ($mode === 'truncate') {
                if ($exists && $size !== 0) {
                    $reasons[] = 'truncate_wal_not_empty';
                }
            } else {
                if (!$exists || $deleted || $size === 0) {
                    $reasons[] = 'restart_wal_header_missing';
                }
                if (hash_equals($walDigest, $digest)) {
                    $reasons[] = 'restart_wal_reused_old_digest';
                }
            }
            if (!$synced) {
                $reasons[] = 'wal_sync_missing';
            }
        } elseif ($type === 'journal') {
            if ($exists || !$deleted || $size !== 0) {
                $reasons[] = 'hot_journal_not_cleared';
            }
        } elseif ($type === 'shm') {
            if ($exists && $observedGeneration < $generation) {
                $reasons[] = 'shm_generation_stale';
            }
        }
        if ($observedGeneration !== 0 && $observedGeneration < $generation) {
            $reasons[] = 'sidecar_generation_before_reset';
        }

        return [
            'name' => $name,
            'type' => $type,
            'path' => is_string($receipt['path'] ?? null) ? $receipt['path'] : null,
            'generation' => $observedGeneration,
            'expected_generation' => $generation,
            'exists' => $exists,
            'deleted' => $deleted,
            'size' => $size,
            'digest' => $digest,
            'sync_receipt' => $synced,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'sidecar_matches_reset_publication' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function readerRow(array $receipt, string $sourceToken, int $generation): array
    {
        $name = self::token($receipt['name'] ?? null, 'reader name');
        $observedSource = self::token($receipt['source_token'] ?? null, "{$name} source token");
        $observedGeneration = $receipt['generation'] ?? null;
        if (!is_int($observedGeneration) || $observedGeneration < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$name} generation is invalid");
        }
        $reopened = ($receipt['reopened'] ?? false) === true;
        $invalidated = ($receipt['invalidated'] ?? false) === true;
        $pinned = ($receipt['pinned'] ?? false) === true;

        $reasons = [];
        if ($pinned) {
            $reasons[] = 'reader_still_pins_old_wal';
        }
        if (!$reopened && !$invalidated) {
            $reasons[] = 'reader_not_reopened_or_invalidated';
        }
        if ($reopened && $observedSource !== $sourceToken) {
            $reasons[] = 'reader_source_token_mismatch';
        }
        if ($reopened && $observedGeneration !== $generation) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if ($invalidated && $observedGeneration >= $generation) {
            $reasons[] = 'invalidated_reader_not_stale';
        }

        return [
            'name' => $name,
            'source_token' => $observedSource,
            'expected_source_token' => $sourceToken,
            'generation' => $observedGeneration,
            'expected_generation' => $generation,
            'reopened' => $reopened,
            'invalidated' => $invalidated,
            'pinned' => $pinned,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'reader_safe_for_reset_publication' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next224 {$label} is required");
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

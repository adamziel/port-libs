<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext244Plan
{
    /**
     * @param array<string,mixed> $baselinePlan
     * @param list<array<string,mixed>> $durableReceipts
     * @param list<array<string,mixed>> $readerAcks
     * @return array<string,mixed>
     */
    public static function sealDurableCurrentSource(array $baselinePlan, array $durableReceipts, array $readerAcks): array
    {
        if (($baselinePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next240') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next244 requires an admitted next240 baseline');
        }
        if (($baselinePlan['autocheckpoint_baseline_allowed'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next244 requires an allowed autocheckpoint baseline');
        }
        if ($durableReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next244 requires durable receipts');
        }
        if ($readerAcks === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next244 requires reader acknowledgements');
        }

        $sourceToken = self::token($baselinePlan['source_token'] ?? null, 'source token');
        $databaseDigest = self::digest($baselinePlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($baselinePlan['page_cache_digest'] ?? null, 'page cache digest');
        $schemaCookie = self::positiveInt($baselinePlan, 'schema_cookie');
        $commitGeneration = self::positiveInt($baselinePlan, 'commit_generation');
        $checkpointFrame = self::positiveInt($baselinePlan, 'checkpoint_frame');
        $mxFrame = self::positiveInt($baselinePlan, 'wal_index_mx_frame');
        $salt = self::walSalt($baselinePlan['wal_index_salt'] ?? null);
        $expectedPages = self::intSet($baselinePlan['dirty_pages'] ?? null, 'dirty pages');
        $expectedFrames = self::intSet($baselinePlan['commit_frames'] ?? null, 'commit frames');

        $durableRows = [];
        foreach ($durableReceipts as $receipt) {
            $durableRows[] = self::durableRow($receipt, $sourceToken, $databaseDigest, $pageCacheDigest, $schemaCookie, $commitGeneration, $checkpointFrame, $mxFrame, $salt, $expectedPages, $expectedFrames);
        }
        $readerRows = [];
        foreach ($readerAcks as $ack) {
            $readerRows[] = self::readerRow($ack, $sourceToken, $databaseDigest, $schemaCookie, $commitGeneration, $checkpointFrame, $mxFrame, $salt);
        }

        $blockedDurableRows = array_values(array_filter($durableRows, static fn (array $row): bool => !$row['admitted']));
        $blockedReaderRows = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['admitted']));
        $writtenPages = [];
        $syncedFrames = [];
        foreach ($durableRows as $row) {
            if (!$row['admitted']) {
                continue;
            }
            foreach ($row['database_pages_written'] as $pageNumber) {
                $writtenPages[$pageNumber] = true;
            }
            foreach ($row['wal_frames_synced'] as $frameNumber) {
                $syncedFrames[$frameNumber] = true;
            }
        }
        ksort($writtenPages);
        ksort($syncedFrames);
        $missingPages = array_values(array_diff(array_keys($expectedPages), array_keys($writtenPages)));
        $missingFrames = array_values(array_diff(array_keys($expectedFrames), array_keys($syncedFrames)));

        $blockedReasons = self::blockedReasons($blockedDurableRows, $blockedReaderRows);
        $guardRows = [
            [
                'name' => 'all_dirty_pages_durably_written',
                'matched' => $missingPages === [],
                'reason' => 'checkpoint publication must write every dirty page from the admitted next240 baseline',
            ],
            [
                'name' => 'all_commit_frames_durably_synced',
                'matched' => $missingFrames === [],
                'reason' => 'checkpoint publication must sync every committed WAL frame before deleting or resetting sidecars',
            ],
            [
                'name' => 'durable_receipts_match_current_source',
                'matched' => $blockedDurableRows === [],
                'reason' => 'durable receipts must match source token, digests, schema cookie, generation, checkpoint frame, mxFrame, salt, lock, and sync state',
            ],
            [
                'name' => 'reader_acknowledgements_match_current_source',
                'matched' => $blockedReaderRows === [],
                'reason' => 'reader acknowledgements must be pinned to the same checkpoint generation before the hot journal and stale WAL are retired',
            ],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $sealed = $blockedGuards === [];

        return [
            'status' => $sealed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next244'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next244',
            'reason' => $sealed
                ? 'durable_current_source_sealed_after_hot_journal_savepoint_checkpoint'
                : 'durable_current_source_held_after_hot_journal_savepoint_checkpoint',
            'base_status' => $baselinePlan['status'],
            'database_path' => $baselinePlan['database_path'] ?? null,
            'journal_path' => $baselinePlan['journal_path'] ?? null,
            'wal_path' => $baselinePlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'schema_cookie' => $schemaCookie,
            'commit_generation' => $commitGeneration,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'wal_index_salt' => $salt,
            'wal_index_mx_frame' => $mxFrame,
            'checkpoint_frame' => $checkpointFrame,
            'expected_dirty_pages' => array_keys($expectedPages),
            'durably_written_pages' => array_keys($writtenPages),
            'missing_dirty_pages' => $missingPages,
            'expected_commit_frames' => array_keys($expectedFrames),
            'durably_synced_frames' => array_keys($syncedFrames),
            'missing_commit_frames' => $missingFrames,
            'durable_rows' => $durableRows,
            'reader_rows' => $readerRows,
            'admitted_durable_names' => array_values(array_column(array_filter($durableRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_durable_names' => array_values(array_column($blockedDurableRows, 'name')),
            'admitted_reader_names' => array_values(array_column(array_filter($readerRows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedReaderRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'sealed_current_source' => $sealed,
            'hot_journal_action' => $sealed ? 'delete_hot_journal_after_durable_checkpoint' : 'retain_hot_journal_until_durable_receipts_match',
            'wal_sidecar_action' => $sealed ? 'reset_or_truncate_wal_sidecar_after_reader_acknowledgements' : 'preserve_wal_sidecar_for_current_source_replay',
            'reader_action' => $sealed ? 'advance_readers_to_commit_generation_' . $commitGeneration : 'hold_readers_on_prior_checkpoint_source',
            'page_cache_action' => $sealed ? 'seal_clean_page_cache_digest_' . $pageCacheDigest : 'discard_unsealed_checkpoint_page_cache',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'seal_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $salt, $durableRows, $readerRows, $missingPages, $missingFrames], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($baselinePlan['operation_names'] ?? null) ? $baselinePlan['operation_names'] : [],
                [
                    'verify_durable_current_source_after_hot_journal_savepoint_checkpoint_next244',
                    $sealed ? 'seal_durable_current_source_next244' : 'hold_durable_current_source_next244',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($baselinePlan['dependencies'] ?? null) ? $baselinePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next244',
                    'sqlite-wal-checkpoint-durable-current-source-seal',
                    'wordpress-import-hot-journal-savepoint-checkpoint-durable-seal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next240 WAL-index baseline metadata, VFS durable receipt evidence, reader acknowledgements, and page-cache digests',
            'non_overlap' => 'next244 seals durable current-source publication after next240 autocheckpoint admission; it does not repeat next240 commit baseline admission, next236 finalizer release, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, WAL byte truncation, or WAL-index reopen verification',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param array<int,true> $expectedPages
     * @param array<int,true> $expectedFrames
     * @param list<string> $salt
     * @return array<string,mixed>
     */
    private static function durableRow(array $receipt, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $schemaCookie, int $commitGeneration, int $checkpointFrame, int $mxFrame, array $salt, array $expectedPages, array $expectedFrames): array
    {
        $name = self::token($receipt['name'] ?? null, 'durable receipt name');
        $pages = self::intList($receipt['database_pages_written'] ?? null, "{$name} database pages written");
        $frames = self::intList($receipt['wal_frames_synced'] ?? null, "{$name} WAL frames synced");
        $reasons = self::commonReasons($receipt, $sourceToken, $databaseDigest, $schemaCookie, $commitGeneration, $checkpointFrame, $mxFrame, $salt, $name);

        if (!hash_equals($pageCacheDigest, self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest"))) {
            $reasons[] = 'durable_page_cache_digest_mismatch';
        }
        foreach ($pages as $pageNumber) {
            if (!isset($expectedPages[$pageNumber])) {
                $reasons[] = 'durable_unexpected_database_page';
                break;
            }
        }
        foreach ($frames as $frameNumber) {
            if (!isset($expectedFrames[$frameNumber])) {
                $reasons[] = 'durable_unexpected_wal_frame';
                break;
            }
        }
        if (($receipt['exclusive_lock_held'] ?? false) !== true) {
            $reasons[] = 'durable_exclusive_lock_missing';
        }
        if (($receipt['database_sync_done'] ?? false) !== true) {
            $reasons[] = 'durable_database_sync_missing';
        }
        if (($receipt['wal_sync_done'] ?? false) !== true) {
            $reasons[] = 'durable_wal_sync_missing';
        }
        if (($receipt['directory_sync_done'] ?? false) !== true) {
            $reasons[] = 'durable_directory_sync_missing';
        }
        if (($receipt['hot_journal_deleted'] ?? false) !== true) {
            $reasons[] = 'durable_hot_journal_not_deleted';
        }
        if (($receipt['stale_wal_preserved'] ?? false) === true) {
            $reasons[] = 'durable_stale_wal_preserved';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'database_pages_written' => $pages,
            'wal_frames_synced' => $frames,
            'exclusive_lock_held' => ($receipt['exclusive_lock_held'] ?? false) === true,
            'database_sync_done' => ($receipt['database_sync_done'] ?? false) === true,
            'wal_sync_done' => ($receipt['wal_sync_done'] ?? false) === true,
            'directory_sync_done' => ($receipt['directory_sync_done'] ?? false) === true,
            'hot_journal_deleted' => ($receipt['hot_journal_deleted'] ?? false) === true,
            'stale_wal_preserved' => ($receipt['stale_wal_preserved'] ?? false) === true,
            'admitted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'durable_receipt_seals_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $ack
     * @param list<string> $salt
     * @return array<string,mixed>
     */
    private static function readerRow(array $ack, string $sourceToken, string $databaseDigest, int $schemaCookie, int $commitGeneration, int $checkpointFrame, int $mxFrame, array $salt): array
    {
        $name = self::token($ack['name'] ?? null, 'reader acknowledgement name');
        $reasons = self::commonReasons($ack, $sourceToken, $databaseDigest, $schemaCookie, $commitGeneration, $checkpointFrame, $mxFrame, $salt, $name);

        if (($ack['reader_generation'] ?? null) !== $commitGeneration) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if (($ack['snapshot_reopened'] ?? false) !== true) {
            $reasons[] = 'reader_snapshot_not_reopened';
        }
        if (($ack['readmark_cleared'] ?? false) !== true) {
            $reasons[] = 'reader_readmark_not_cleared';
        }
        if (($ack['hot_journal_seen'] ?? false) === true) {
            $reasons[] = 'reader_hot_journal_still_visible';
        }
        if (($ack['stale_wal_seen'] ?? false) === true) {
            $reasons[] = 'reader_stale_wal_still_visible';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'reader_generation' => $ack['reader_generation'] ?? null,
            'snapshot_reopened' => ($ack['snapshot_reopened'] ?? false) === true,
            'readmark_cleared' => ($ack['readmark_cleared'] ?? false) === true,
            'hot_journal_seen' => ($ack['hot_journal_seen'] ?? false) === true,
            'stale_wal_seen' => ($ack['stale_wal_seen'] ?? false) === true,
            'admitted' => $reasons === [],
            'reader_reason' => $reasons === [] ? 'reader_acknowledges_durable_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $salt
     * @return list<string>
     */
    private static function commonReasons(array $row, string $sourceToken, string $databaseDigest, int $schemaCookie, int $commitGeneration, int $checkpointFrame, int $mxFrame, array $salt, string $name): array
    {
        $reasons = [];
        if (self::token($row['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'source_token_mismatch';
        }
        if (self::positiveValue($row['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'schema_cookie_mismatch';
        }
        if (self::positiveValue($row['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'commit_generation_mismatch';
        }
        if (self::positiveValue($row['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'checkpoint_frame_mismatch';
        }
        if (self::positiveValue($row['wal_index_mx_frame'] ?? null, "{$name} wal index mx frame") !== $mxFrame) {
            $reasons[] = 'wal_index_mx_frame_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($row['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'database_digest_mismatch';
        }
        if (self::walSalt($row['wal_index_salt'] ?? null) !== $salt) {
            $reasons[] = 'wal_index_salt_mismatch';
        }

        return $reasons;
    }

    /**
     * @param list<array<string,mixed>> $durableRows
     * @param list<array<string,mixed>> $readerRows
     * @return list<string>
     */
    private static function blockedReasons(array $durableRows, array $readerRows): array
    {
        $reasons = [];
        foreach (array_merge($durableRows, $readerRows) as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $reasons[$reason] = true;
            }
        }
        ksort($reasons);

        return array_keys($reasons);
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key): int
    {
        return self::positiveValue($values[$key] ?? null, $key);
    }

    private static function positiveValue(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 requires positive {$label}");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 requires {$label}");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function walSalt(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next244 requires two WAL-index salt values');
        }

        $salt = array_values($value);
        foreach ($salt as $part) {
            self::token($part, 'WAL-index salt');
        }

        return $salt;
    }

    /**
     * @return array<int,true>
     */
    private static function intSet(mixed $value, string $label): array
    {
        $list = self::intList($value, $label);
        if ($list === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 requires {$label}");
        }

        return array_fill_keys($list, true);
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 requires {$label}");
        }

        $values = [];
        foreach ($value as $number) {
            if (!is_int($number) || $number <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next244 {$label} contains an invalid number");
            }
            $values[$number] = true;
        }
        ksort($values);

        return array_keys($values);
    }
}

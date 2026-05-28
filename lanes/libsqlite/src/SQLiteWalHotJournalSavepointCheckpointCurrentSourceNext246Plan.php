<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext246Plan
{
    /**
     * @param array<string,mixed> $readerPlan
     * @param list<array<string,mixed>> $writeReceipts
     * @return array<string,mixed>
     */
    public static function admitDurableCurrentSourceHandoff(array $readerPlan, array $writeReceipts): array
    {
        if (($readerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next243'
            || ($readerPlan['reader_snapshot_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next246 requires an admitted next243 reader snapshot plan');
        }
        if ($writeReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next246 requires VFS write receipts');
        }

        $databasePath = self::path($readerPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($readerPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($readerPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($readerPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($readerPlan['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($readerPlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($readerPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($readerPlan['page_cache_digest'] ?? null, 'page cache digest');
        $checkpointFrame = self::nonNegativeInt($readerPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($readerPlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($readerPlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenSet($readerPlan['accepted_reader_names'] ?? null, 'accepted reader names');

        $rows = [];
        foreach ($writeReceipts as $receipt) {
            $rows[] = self::writeReceiptRow($receipt, $databasePath, $walPath, $journalPath, $sourceToken, $commitGeneration);
        }

        $receiptNames = array_column($rows, 'name');
        $duplicateReceipts = self::duplicates($receiptNames);
        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));

        $databasePageWrites = [];
        $commitFrameWrites = [];
        $syncTargets = [];
        $deleteTargets = [];
        $operationOrder = [];
        foreach ($acceptedRows as $row) {
            $operationOrder[] = $row['operation'] === 'sync' ? 'sync:' . $row['target'] : $row['operation'];
            if ($row['operation'] === 'write_database_page') {
                foreach ($row['pages'] as $page) {
                    $databasePageWrites[$page] = true;
                }
            }
            if ($row['operation'] === 'mark_wal_commit_frame') {
                foreach ($row['frames'] as $frame) {
                    $commitFrameWrites[$frame] = true;
                }
            }
            if ($row['operation'] === 'sync') {
                $syncTargets[$row['target']] = true;
            }
            if ($row['operation'] === 'delete') {
                $deleteTargets[$row['target']] = true;
            }
        }

        $writtenPages = array_map('intval', array_keys($databasePageWrites));
        sort($writtenPages);
        $writtenCommitFrames = array_map('intval', array_keys($commitFrameWrites));
        sort($writtenCommitFrames);
        $missingPages = array_values(array_diff($dirtyPages, $writtenPages));
        $missingFrames = array_values(array_diff($commitFrames, $writtenCommitFrames));
        $missingSyncTargets = array_values(array_diff(['database', 'wal', 'directory'], array_keys($syncTargets)));
        $hotJournalDeleted = isset($deleteTargets['journal']);
        $orderMatched = self::operationOrderIsSafe($operationOrder);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateReceipts !== []) {
            $blockedReasons[] = 'vfs_write_receipt_name_duplicate';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'checkpoint_database_page_write_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'checkpoint_commit_frame_mark_missing';
        }
        if ($missingSyncTargets !== []) {
            $blockedReasons[] = 'checkpoint_sync_target_missing';
        }
        if (!$hotJournalDeleted) {
            $blockedReasons[] = 'checkpoint_hot_journal_delete_missing';
        }
        if (!$orderMatched) {
            $blockedReasons[] = 'checkpoint_current_source_write_order_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            [
                'name' => 'next243_reader_snapshot_admitted',
                'matched' => true,
                'reason' => 'reader snapshots already match the autocheckpoint current source',
            ],
            [
                'name' => 'vfs_write_receipt_names_unique',
                'matched' => $duplicateReceipts === [],
                'reason' => 'each durable write receipt must be attributable exactly once',
            ],
            [
                'name' => 'all_dirty_pages_written_to_database',
                'matched' => $missingPages === [],
                'reason' => 'checkpoint promotion must materialize every dirty page in the database image',
            ],
            [
                'name' => 'all_commit_frames_marked',
                'matched' => $missingFrames === [],
                'reason' => 'the WAL commit frames that define the current source must remain attributable',
            ],
            [
                'name' => 'database_wal_directory_synced',
                'matched' => $missingSyncTargets === [],
                'reason' => 'database bytes, WAL metadata, and directory entry changes must reach durable storage',
            ],
            [
                'name' => 'hot_journal_deleted_after_sync',
                'matched' => $hotJournalDeleted && $orderMatched,
                'reason' => 'a hot journal may disappear only after checkpoint bytes and directory sync are durable',
            ],
            [
                'name' => 'all_vfs_receipts_accepted',
                'matched' => $blockedRows === [],
                'reason' => 'receipt path, token, generation, lock, sync, savepoint, and error metadata must match',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next246'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next246',
            'reason' => $admitted
                ? 'durable_vfs_handoff_promotes_checkpoint_current_source'
                : 'durable_vfs_handoff_holds_checkpoint_current_source',
            'base_status' => $readerPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'checkpoint_frame' => $checkpointFrame,
            'dirty_pages' => $dirtyPages,
            'commit_frames' => $commitFrames,
            'accepted_reader_names' => $readerNames,
            'write_rows' => $rows,
            'write_receipt_names' => $receiptNames,
            'accepted_write_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_write_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_write_receipt_names' => $duplicateReceipts,
            'written_database_pages' => $writtenPages,
            'missing_database_pages' => $missingPages,
            'written_commit_frames' => $writtenCommitFrames,
            'missing_commit_frames' => $missingFrames,
            'sync_targets' => array_values(array_keys($syncTargets)),
            'missing_sync_targets' => $missingSyncTargets,
            'delete_targets' => array_values(array_keys($deleteTargets)),
            'operation_order' => $operationOrder,
            'write_order_safe' => $orderMatched,
            'blocked_write_reasons' => $blockedReasons,
            'durable_handoff_admitted' => $admitted,
            'checkpoint_action' => $admitted ? 'publish_database_image_as_checkpoint_current_source' : 'retain_previous_current_source_until_vfs_handoff_is_durable',
            'wal_action' => $admitted ? 'retain_committed_frames_until_reader_epoch_advances' : 'hold_wal_reset_and_restart',
            'journal_action' => $admitted ? 'delete_hot_journal_after_directory_sync' : 'keep_hot_journal_recovery_visible',
            'savepoint_action' => $admitted ? 'release_savepoint_after_vfs_handoff' : 'keep_savepoint_scope_replayable',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'handoff_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $databaseDigest, $pageCacheDigest, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerPlan['operation_names'] ?? null) ? $readerPlan['operation_names'] : [],
                [
                    'verify_durable_vfs_handoff_next246',
                    $admitted ? 'admit_durable_current_source_handoff_next246' : 'hold_durable_current_source_handoff_next246',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerPlan['dependencies'] ?? null) ? $readerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246',
                    'sqlite-vfs-durable-checkpoint-handoff',
                    'wordpress-import-hot-journal-checkpoint-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native PHP VFS write receipts, sync targets, WAL commit-frame metadata, hot-journal delete receipts, and next243 reader snapshot admission',
            'non_overlap' => 'next246 validates durable VFS handoff ordering after next243 reader admission; it does not repeat reader snapshot matching, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync planning/apply, file locking, or SELECT/JSON/B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function writeReceiptRow(array $receipt, string $databasePath, string $walPath, string $journalPath, string $sourceToken, int $commitGeneration): array
    {
        $name = self::token($receipt['name'] ?? null, 'write receipt name');
        $target = self::target($receipt['target'] ?? null, "{$name} target");
        $operation = self::operation($receipt['operation'] ?? null, "{$name} operation");
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $pages = self::optionalPositiveIntSet($receipt['pages'] ?? null, "{$name} pages");
        $frames = self::optionalPositiveIntSet($receipt['frames'] ?? null, "{$name} frames");
        $sequence = self::positiveInt($receipt['sequence'] ?? null, "{$name} sequence");
        $reasons = [];

        $expectedPath = match ($target) {
            'database' => $databasePath,
            'wal' => $walPath,
            'journal' => $journalPath,
            'directory' => dirname($databasePath),
        };
        if ($path !== $expectedPath) {
            $reasons[] = 'vfs_write_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'vfs_write_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'vfs_write_commit_generation_mismatch';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'vfs_write_exclusive_lock_missing';
        }
        if (($receipt['savepoint_replayable'] ?? null) !== true) {
            $reasons[] = 'vfs_write_savepoint_not_replayable';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'vfs_write_io_error';
        }
        if ($operation === 'write_database_page' && ($target !== 'database' || $pages === [])) {
            $reasons[] = 'vfs_database_page_write_receipt_invalid';
        }
        if ($operation === 'mark_wal_commit_frame' && ($target !== 'wal' || $frames === [])) {
            $reasons[] = 'vfs_wal_commit_frame_receipt_invalid';
        }
        if ($operation === 'sync' && !in_array($target, ['database', 'wal', 'directory'], true)) {
            $reasons[] = 'vfs_sync_target_invalid';
        }
        if ($operation === 'delete' && $target !== 'journal') {
            $reasons[] = 'vfs_delete_target_invalid';
        }

        return [
            'name' => $name,
            'target' => $target,
            'operation' => $operation,
            'path' => $path,
            'sequence' => $sequence,
            'pages' => $pages,
            'frames' => $frames,
            'source_token' => $receipt['source_token'],
            'commit_generation' => $receipt['commit_generation'],
            'exclusive_lock_held' => $receipt['exclusive_lock_held'] ?? null,
            'savepoint_replayable' => $receipt['savepoint_replayable'] ?? null,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'blocked_reasons' => array_values(array_unique($reasons)),
            'receipt_reason' => $reasons === [] ? 'vfs_write_receipt_matches_checkpoint_current_source' : 'vfs_write_receipt_blocks_checkpoint_current_source',
        ];
    }

    /**
     * @param list<string> $operations
     */
    private static function operationOrderIsSafe(array $operations): bool
    {
        $firstPage = array_search('write_database_page', $operations, true);
        $firstFrame = array_search('mark_wal_commit_frame', $operations, true);
        $databaseSync = array_search('sync:database', $operations, true);
        $walSync = array_search('sync:wal', $operations, true);
        $directorySync = array_search('sync:directory', $operations, true);
        $delete = array_search('delete', $operations, true);

        return $firstPage !== false
            && $firstFrame !== false
            && $databaseSync !== false
            && $walSync !== false
            && $directorySync !== false
            && $delete !== false
            && $firstPage < $databaseSync
            && $firstFrame < $walSync
            && $databaseSync < $directorySync
            && $walSync < $directorySync
            && $directorySync < $delete;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9._:-]*$/i', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function tokenSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $tokens = [];
        foreach ($value as $item) {
            $tokens[] = self::token($item, $label);
        }
        return array_values(array_unique($tokens));
    }

    private static function target(mixed $value, string $label): string
    {
        $target = self::token($value, $label);
        if (!in_array($target, ['database', 'wal', 'journal', 'directory'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $target;
    }

    private static function operation(mixed $value, string $label): string
    {
        $operation = self::token($value, $label);
        if (!in_array($operation, ['write_database_page', 'mark_wal_commit_frame', 'sync', 'delete'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $operation;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<int> */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        $values = self::optionalPositiveIntSet($value, $label);
        if ($values === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $values;
    }

    /** @return list<int> */
    private static function optionalPositiveIntSet(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item < 1) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
            $set[$item] = true;
        }
        $values = array_map('intval', array_keys($set));
        sort($values);
        return $values;
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
                continue;
            }
            $seen[$value] = true;
        }
        return array_values(array_keys($duplicates));
    }
}

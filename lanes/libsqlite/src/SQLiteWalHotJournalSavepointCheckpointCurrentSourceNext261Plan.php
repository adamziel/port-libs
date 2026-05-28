<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Plan
{
    /**
     * @param array<string,mixed> $writerPlan
     * @param list<array<string,mixed>> $publishReceipts
     * @return array<string,mixed>
     */
    public static function sealPublishedCurrentSource(array $writerPlan, array $publishReceipts): array
    {
        if (($writerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next258'
            || ($writerPlan['post_restart_writer_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL current-source next261 requires admitted next258 post-restart writer plan');
        }
        if ($publishReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next261 requires publish receipts');
        }

        $databasePath = self::path($writerPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($writerPlan['wal_path'] ?? null, 'wal path');
        $sourceToken = self::token($writerPlan['source_token'] ?? null, 'source token');
        $generation = self::positiveInt($writerPlan['commit_generation'] ?? null, 'commit generation');
        $publishGeneration = $generation + 1;
        $checkpointFrame = self::nonNegativeInt($writerPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $databaseDigest = self::digest($writerPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($writerPlan['page_cache_digest'] ?? null, 'page cache digest');
        $oldSalt = self::salt($writerPlan['restart_wal_salt'] ?? null, 'restart WAL salt');
        $acceptedWriters = self::tokenSet($writerPlan['accepted_writer_receipt_names'] ?? null, 'accepted writer receipts');
        $readmarkSlots = self::positiveIntSet($writerPlan['readmark_slots'] ?? null, 'readmark slots');
        $readerNames = self::tokenSet($writerPlan['reopened_reader_names'] ?? null, 'reopened reader names');

        $rows = [];
        foreach ($publishReceipts as $receipt) {
            $rows[] = self::publishRow(
                $receipt,
                $databasePath,
                $walPath,
                $sourceToken,
                $publishGeneration,
                $checkpointFrame,
                $databaseDigest,
                $pageCacheDigest,
                $oldSalt,
                $acceptedWriters,
                $readmarkSlots,
                $readerNames
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $types = [];
        $coveredWriters = [];
        $coveredSlots = [];
        $coveredReaders = [];
        foreach ($acceptedRows as $row) {
            $types[$row['receipt_type']] = true;
            foreach ($row['writer_receipt_names'] as $name) {
                $coveredWriters[$name] = true;
            }
            foreach ($row['readmark_slots'] as $slot) {
                $coveredSlots[$slot] = true;
            }
            foreach ($row['reader_names'] as $readerName) {
                $coveredReaders[$readerName] = true;
            }
        }

        $requiredTypes = ['database-image', 'wal-frame', 'shm-index', 'readmark-fence', 'savepoint-release', 'sync'];
        $missingTypes = array_values(array_diff($requiredTypes, array_keys($types)));
        $missingWriters = array_values(array_diff($acceptedWriters, self::sortedStringKeys($coveredWriters)));
        $missingSlots = array_values(array_diff($readmarkSlots, self::sortedIntKeys($coveredSlots)));
        $missingReaders = array_values(array_diff($readerNames, self::sortedStringKeys($coveredReaders)));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'current_source_publish_receipt_name_duplicate:' . $name;
        }
        if ($missingTypes !== []) {
            $blockedReasons[] = 'current_source_publish_receipt_type_missing';
        }
        if ($missingWriters !== []) {
            $blockedReasons[] = 'current_source_writer_receipt_coverage_missing';
        }
        if ($missingSlots !== []) {
            $blockedReasons[] = 'current_source_readmark_coverage_missing';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'current_source_reader_coverage_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next258_writer_admitted', 'matched' => true, 'reason' => 'the first post-restart writer passed next258 reader-fence admission'],
            ['name' => 'publish_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each source publication receipt must be unique'],
            ['name' => 'all_publish_receipt_types_present', 'matched' => $missingTypes === [], 'reason' => 'database image, WAL frame, SHM, read-mark, savepoint, and sync receipts are required'],
            ['name' => 'all_writer_receipts_covered', 'matched' => $missingWriters === [], 'reason' => 'all next258 writer receipts must be represented in the published source'],
            ['name' => 'all_readmarks_fenced', 'matched' => $missingSlots === [], 'reason' => 'all restarted read-mark slots must point at the published generation'],
            ['name' => 'all_reopened_readers_covered', 'matched' => $missingReaders === [], 'reason' => 'all reopened readers must observe the sealed source digest'],
            ['name' => 'all_publish_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'publish receipts must match generation, paths, digests, salt transition, locks, and sync state'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $sealed = $blockedGuards === [];

        return [
            'status' => $sealed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next261'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next261',
            'reason' => $sealed ? 'current_source_sealed_after_post_restart_writer' : 'current_source_publish_waits_for_receipts',
            'base_status' => $writerPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'source_token' => $sourceToken,
            'commit_generation' => $generation,
            'publish_generation' => $publishGeneration,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'restart_wal_salt' => $oldSalt,
            'accepted_writer_receipt_names' => $acceptedWriters,
            'reopened_reader_names' => $readerNames,
            'readmark_slots' => $readmarkSlots,
            'publish_rows' => $rows,
            'publish_receipt_names' => array_column($rows, 'name'),
            'accepted_publish_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_publish_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_publish_receipt_names' => $duplicateNames,
            'publish_receipt_types' => array_values(array_keys($types)),
            'missing_publish_receipt_types' => $missingTypes,
            'covered_writer_receipt_names' => self::sortedStringKeys($coveredWriters),
            'missing_writer_receipt_names' => $missingWriters,
            'covered_readmark_slots' => self::sortedIntKeys($coveredSlots),
            'missing_readmark_slots' => $missingSlots,
            'covered_reader_names' => self::sortedStringKeys($coveredReaders),
            'missing_reader_names' => $missingReaders,
            'blocked_publish_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'current_source_sealed' => $sealed,
            'writer_action' => $sealed ? 'publish_post_restart_writer_generation_' . $publishGeneration : 'hold_post_restart_writer_source_unpublished',
            'wal_action' => $sealed ? 'seal_wal_frame_one_as_current_source' : 'preserve_restarted_wal_pending_publish',
            'reader_action' => $sealed ? 'advance_reopened_readers_to_source_digest' : 'keep_reopened_readers_on_checkpoint_snapshot',
            'savepoint_action' => $sealed ? 'release_post_restart_publish_savepoint' : 'keep_post_restart_publish_savepoint_replayable',
            'sync_action' => $sealed ? 'fsync_database_wal_shm_before_reader_publish' : 'wait_for_database_wal_shm_sync',
            'publication_digest' => hash('sha256', json_encode([$sourceToken, $publishGeneration, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($writerPlan['operation_names'] ?? null) ? $writerPlan['operation_names'] : [],
                [
                    'verify_current_source_publish_receipts_next261',
                    $sealed ? 'seal_current_source_next261' : 'hold_current_source_next261',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($writerPlan['dependencies'] ?? null) ? $writerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next261',
                    'sqlite-wal-current-source-publication-after-post-restart-writer',
                    'wordpress-import-wal-current-source-seal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next258 writer admission metadata with native PHP database/WAL/SHM/read-mark/savepoint/sync publication receipts',
            'non_overlap' => 'next261 seals the current-source publication after next258 writer admission; it does not repeat next258 writer admission, next255 reader restart admission, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, JSON, SELECT, B-tree, or encoding surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $oldSalt
     * @param list<string> $acceptedWriters
     * @param list<int> $readmarkSlots
     * @param list<string> $readerNames
     * @return array<string,mixed>
     */
    private static function publishRow(array $receipt, string $databasePath, string $walPath, string $sourceToken, int $publishGeneration, int $checkpointFrame, string $databaseDigest, string $pageCacheDigest, array $oldSalt, array $acceptedWriters, array $readmarkSlots, array $readerNames): array
    {
        $name = self::token($receipt['name'] ?? null, 'publish receipt name');
        $type = self::receiptType($receipt['receipt_type'] ?? null);
        $writers = self::tokenSet($receipt['writer_receipt_names'] ?? null, "{$name} writer receipts");
        $slots = self::positiveIntSet($receipt['readmark_slots'] ?? null, "{$name} readmark slots");
        $readers = self::tokenSet($receipt['reader_names'] ?? null, "{$name} reader names");
        $newSalt = self::salt($receipt['published_wal_salt'] ?? null, "{$name} published WAL salt");
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'current_source_publish_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'current_source_publish_wal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'current_source_publish_source_token_mismatch';
        }
        if (self::positiveInt($receipt['publish_generation'] ?? null, "{$name} publish generation") !== $publishGeneration) {
            $reasons[] = 'current_source_publish_generation_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'current_source_publish_checkpoint_frame_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'current_source_publish_database_digest_mismatch';
        }
        if (self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest") !== $pageCacheDigest) {
            $reasons[] = 'current_source_publish_page_cache_digest_mismatch';
        }
        if ($newSalt === $oldSalt) {
            $reasons[] = 'current_source_publish_reused_restart_salt';
        }
        foreach ($writers as $writer) {
            if (!in_array($writer, $acceptedWriters, true)) {
                $reasons[] = 'current_source_publish_unknown_writer_receipt';
            }
        }
        foreach ($slots as $slot) {
            if (!in_array($slot, $readmarkSlots, true)) {
                $reasons[] = 'current_source_publish_unknown_readmark_slot';
            }
        }
        foreach ($readers as $reader) {
            if (!in_array($reader, $readerNames, true)) {
                $reasons[] = 'current_source_publish_unknown_reader';
            }
        }
        if (self::positiveInt($receipt['mx_frame'] ?? null, "{$name} mxFrame") < 1) {
            $reasons[] = 'current_source_publish_mxframe_invalid';
        }
        if (self::nonNegativeInt($receipt['savepoint_depth'] ?? null, "{$name} savepoint depth") !== 0) {
            $reasons[] = 'current_source_publish_savepoint_scope_open';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'current_source_publish_exclusive_lock_missing';
        }
        if (($receipt['hot_journal_visible'] ?? null) !== false) {
            $reasons[] = 'current_source_publish_hot_journal_visible';
        }
        if (($receipt['database_synced'] ?? null) !== true) {
            $reasons[] = 'current_source_publish_database_not_synced';
        }
        if (($receipt['wal_synced'] ?? null) !== true) {
            $reasons[] = 'current_source_publish_wal_not_synced';
        }
        if (($receipt['shm_synced'] ?? null) !== true) {
            $reasons[] = 'current_source_publish_shm_not_synced';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'current_source_publish_io_error';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'receipt_type' => $type,
            'database_path' => $receipt['database_path'],
            'wal_path' => $receipt['wal_path'],
            'source_token' => $receipt['source_token'],
            'publish_generation' => $receipt['publish_generation'],
            'checkpoint_frame' => $receipt['checkpoint_frame'],
            'database_digest' => $receipt['database_digest'],
            'page_cache_digest' => $receipt['page_cache_digest'],
            'published_wal_salt' => $newSalt,
            'writer_receipt_names' => $writers,
            'readmark_slots' => $slots,
            'reader_names' => $readers,
            'mx_frame' => $receipt['mx_frame'],
            'savepoint_depth' => $receipt['savepoint_depth'],
            'exclusive_lock_held' => $receipt['exclusive_lock_held'] ?? null,
            'hot_journal_visible' => $receipt['hot_journal_visible'] ?? null,
            'database_synced' => $receipt['database_synced'] ?? null,
            'wal_synced' => $receipt['wal_synced'] ?? null,
            'shm_synced' => $receipt['shm_synced'] ?? null,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? 'current_source_publish_receipt_matches' : 'current_source_publish_receipt_blocks',
        ];
    }

    private static function receiptType(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['database-image', 'wal-frame', 'shm-index', 'readmark-fence', 'savepoint-release', 'sync'], true)) {
            throw new \InvalidArgumentException('Invalid publish receipt type');
        }
        return $value;
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
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /**
     * @return list<string>
     */
    private static function salt(mixed $value, string $label): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $salt = array_values($value);
        foreach ($salt as $part) {
            if (!is_string($part) || !preg_match('/^[a-f0-9]{8}$/', $part)) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
        }
        return $salt;
    }

    /**
     * @return list<string>
     */
    private static function tokenSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $items = [];
        foreach ($value as $item) {
            $items[] = self::token($item, $label);
        }
        $items = array_values(array_unique($items));
        sort($items);
        return $items;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $items = [];
        foreach ($value as $item) {
            $items[] = self::positiveInt($item, $label);
        }
        $items = array_values(array_unique($items));
        sort($items);
        return $items;
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

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
            }
            $seen[$value] = true;
        }
        $out = array_keys($duplicates);
        sort($out);
        return $out;
    }

    /**
     * @param array<string,bool> $map
     * @return list<string>
     */
    private static function sortedStringKeys(array $map): array
    {
        $keys = array_keys($map);
        sort($keys);
        return $keys;
    }

    /**
     * @param array<int|string,bool> $map
     * @return list<int>
     */
    private static function sortedIntKeys(array $map): array
    {
        $keys = array_map('intval', array_keys($map));
        sort($keys);
        return $keys;
    }
}

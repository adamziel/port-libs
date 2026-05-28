<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext258Plan
{
    /**
     * @param array<string,mixed> $readerPlan
     * @param list<array<string,mixed>> $writerReceipts
     * @return array<string,mixed>
     */
    public static function admitWriterAfterRestartedReaders(array $readerPlan, array $writerReceipts): array
    {
        if (($readerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next255'
            || ($readerPlan['restarted_reader_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next258 requires admitted next255 restarted readers');
        }
        if ($writerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next258 requires writer receipts');
        }

        $databasePath = self::path($readerPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($readerPlan['wal_path'] ?? null, 'wal path');
        $sourceToken = self::token($readerPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($readerPlan['commit_generation'] ?? null, 'commit generation');
        $checkpointFrame = self::nonNegativeInt($readerPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $databaseDigest = self::digest($readerPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($readerPlan['page_cache_digest'] ?? null, 'page cache digest');
        $restartSalt = self::salt($readerPlan['next_wal_salt'] ?? null, 'restart WAL salt');
        $reopenedReaders = self::tokenSet($readerPlan['reopened_reader_names'] ?? null, 'reopened reader names');
        $readmarkSlots = self::positiveIntSet($readerPlan['readmark_slots'] ?? null, 'readmark slots');

        $rows = [];
        foreach ($writerReceipts as $receipt) {
            $rows[] = self::writerRow(
                $receipt,
                $databasePath,
                $walPath,
                $sourceToken,
                $commitGeneration,
                $checkpointFrame,
                $databaseDigest,
                $pageCacheDigest,
                $restartSalt,
                $reopenedReaders,
                $readmarkSlots
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $kinds = array_values(array_unique(array_column($acceptedRows, 'kind')));
        sort($kinds);
        $requiredKinds = ['first-frame', 'header-salt', 'reader-fence', 'sync'];
        $missingKinds = array_values(array_diff($requiredKinds, $kinds));

        $coveredReaders = [];
        $coveredSlots = [];
        foreach ($acceptedRows as $row) {
            foreach ($row['reader_names'] as $readerName) {
                $coveredReaders[$readerName] = true;
            }
            foreach ($row['readmark_slots'] as $slot) {
                $coveredSlots[$slot] = true;
            }
        }
        $coveredReaders = self::sortedStringKeys($coveredReaders);
        $coveredSlots = self::sortedIntKeys($coveredSlots);
        $missingReaders = array_values(array_diff($reopenedReaders, $coveredReaders));
        $missingSlots = array_values(array_diff($readmarkSlots, $coveredSlots));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'post_restart_writer_receipt_name_duplicate:' . $name;
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'post_restart_writer_kind_missing';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'post_restart_reader_fence_missing';
        }
        if ($missingSlots !== []) {
            $blockedReasons[] = 'post_restart_readmark_fence_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next255_restarted_readers_admitted', 'matched' => true, 'reason' => 'write admission happens after restarted readers reopen on an empty WAL generation'],
            ['name' => 'writer_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each post-restart writer receipt must be unique'],
            ['name' => 'required_writer_kinds_present', 'matched' => $missingKinds === [], 'reason' => 'header salt, first frame, reader fence, and durable sync receipts are required'],
            ['name' => 'all_reopened_readers_fenced', 'matched' => $missingReaders === [], 'reason' => 'every reopened reader must be fenced before the first new write is visible'],
            ['name' => 'all_reopened_readmarks_fenced', 'matched' => $missingSlots === [], 'reason' => 'every restarted read-mark slot must be fenced from the new writer frame'],
            ['name' => 'all_writer_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'writer receipts must match paths, digests, generation, salt transition, frame numbers, locks, savepoint depth, and hot-journal state'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next258'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next258',
            'reason' => $admitted
                ? 'post_restart_writer_admitted_after_reader_fences'
                : 'post_restart_writer_waits_for_reader_fences',
            'base_status' => $readerPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'restart_wal_salt' => $restartSalt,
            'reopened_reader_names' => $reopenedReaders,
            'readmark_slots' => $readmarkSlots,
            'writer_rows' => $rows,
            'writer_receipt_names' => array_values(array_column($rows, 'name')),
            'accepted_writer_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_writer_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_writer_receipt_names' => $duplicateNames,
            'accepted_writer_kinds' => $kinds,
            'missing_writer_kinds' => $missingKinds,
            'covered_reader_names' => $coveredReaders,
            'missing_reader_names' => $missingReaders,
            'covered_readmark_slots' => $coveredSlots,
            'missing_readmark_slots' => $missingSlots,
            'blocked_writer_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'post_restart_writer_admitted' => $admitted,
            'writer_action' => $admitted ? 'start_new_wal_transaction_after_restarted_reader_fence' : 'hold_writer_until_restarted_reader_fence',
            'wal_action' => $admitted ? 'append_first_frame_with_new_salt_after_restart' : 'preserve_empty_restarted_wal_generation',
            'reader_action' => $admitted ? 'keep_restarted_readers_on_checkpoint_snapshot' : 'block_writer_visibility_to_restarted_readers',
            'journal_action' => $admitted ? 'keep_hot_journal_unlinked_for_post_restart_writer' : 'reject_writer_with_visible_hot_journal',
            'admission_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $restartSalt, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerPlan['operation_names'] ?? null) ? $readerPlan['operation_names'] : [],
                [
                    'verify_post_restart_writer_reader_fence_current_source_next258',
                    $admitted ? 'admit_post_restart_writer_current_source_next258' : 'hold_post_restart_writer_current_source_next258',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerPlan['dependencies'] ?? null) ? $readerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next258',
                    'sqlite-wal-post-restart-writer-reader-fence',
                    'wordpress-import-wal-post-restart-writer-admission',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next255 restarted-reader admission with native PHP writer receipts for salt transition, first-frame sequencing, read-mark fences, lock state, clean savepoint scope, and durable sync',
            'non_overlap' => 'next258 admits the first writer after next255 restarted readers by validating reader fences and new WAL salt/frame receipts; it does not repeat next255 reader reopen admission, next252 post-truncate sealing, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, SELECT, JSON, B-tree, or encoding surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $restartSalt
     * @param list<string> $reopenedReaders
     * @param list<int> $readmarkSlots
     * @return array<string,mixed>
     */
    private static function writerRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $sourceToken,
        int $commitGeneration,
        int $checkpointFrame,
        string $databaseDigest,
        string $pageCacheDigest,
        array $restartSalt,
        array $reopenedReaders,
        array $readmarkSlots
    ): array {
        $name = self::token($receipt['name'] ?? null, 'writer receipt name');
        $kind = self::kind($receipt['kind'] ?? null);
        $readerNames = self::tokenSet($receipt['reader_names'] ?? null, "{$name} reader names");
        $slots = self::positiveIntSet($receipt['readmark_slots'] ?? null, "{$name} readmark slots");
        $oldSalt = self::salt($receipt['old_wal_salt'] ?? null, "{$name} old WAL salt");
        $newSalt = self::salt($receipt['new_wal_salt'] ?? null, "{$name} new WAL salt");
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'post_restart_writer_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'post_restart_writer_wal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'post_restart_writer_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration + 1) {
            $reasons[] = 'post_restart_writer_generation_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'post_restart_writer_checkpoint_frame_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'post_restart_writer_database_digest_mismatch';
        }
        if (self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest") !== $pageCacheDigest) {
            $reasons[] = 'post_restart_writer_page_cache_digest_mismatch';
        }
        if ($oldSalt !== $restartSalt) {
            $reasons[] = 'post_restart_writer_old_salt_mismatch';
        }
        if ($newSalt === $restartSalt) {
            $reasons[] = 'post_restart_writer_reused_restart_salt';
        }
        foreach ($readerNames as $readerName) {
            if (!in_array($readerName, $reopenedReaders, true)) {
                $reasons[] = 'post_restart_writer_unknown_reader';
            }
        }
        foreach ($slots as $slot) {
            if (!in_array($slot, $readmarkSlots, true)) {
                $reasons[] = 'post_restart_writer_unknown_readmark_slot';
            }
        }
        if (self::nonNegativeInt($receipt['wal_size_before'] ?? null, "{$name} WAL size before") !== 32) {
            $reasons[] = 'post_restart_writer_wal_not_empty_before_write';
        }
        if (self::positiveInt($receipt['first_frame_index'] ?? null, "{$name} first frame index") !== 1) {
            $reasons[] = 'post_restart_writer_first_frame_not_one';
        }
        if (self::positiveInt($receipt['first_frame_page'] ?? null, "{$name} first frame page") < 1) {
            $reasons[] = 'post_restart_writer_first_page_invalid';
        }
        if (self::nonNegativeInt($receipt['mx_frame_before'] ?? null, "{$name} mxFrame before") !== 0) {
            $reasons[] = 'post_restart_writer_mxframe_not_zero_before_write';
        }
        if (self::positiveInt($receipt['mx_frame_after'] ?? null, "{$name} mxFrame after") !== 1) {
            $reasons[] = 'post_restart_writer_mxframe_not_first_frame';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'post_restart_writer_exclusive_lock_missing';
        }
        if (($receipt['hot_journal_visible'] ?? null) !== false) {
            $reasons[] = 'post_restart_writer_hot_journal_visible';
        }
        if (self::nonNegativeInt($receipt['pending_savepoint_depth'] ?? null, "{$name} pending savepoint depth") !== 0) {
            $reasons[] = 'post_restart_writer_savepoint_scope_open';
        }
        if (($receipt['durably_synced'] ?? null) !== true) {
            $reasons[] = 'post_restart_writer_not_durably_synced';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'post_restart_writer_io_error';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'database_path' => $receipt['database_path'],
            'wal_path' => $receipt['wal_path'],
            'source_token' => $receipt['source_token'],
            'commit_generation' => $receipt['commit_generation'],
            'checkpoint_frame' => $receipt['checkpoint_frame'],
            'database_digest' => $receipt['database_digest'],
            'page_cache_digest' => $receipt['page_cache_digest'],
            'old_wal_salt' => $oldSalt,
            'new_wal_salt' => $newSalt,
            'reader_names' => $readerNames,
            'readmark_slots' => $slots,
            'wal_size_before' => $receipt['wal_size_before'],
            'first_frame_index' => $receipt['first_frame_index'],
            'first_frame_page' => $receipt['first_frame_page'],
            'mx_frame_before' => $receipt['mx_frame_before'],
            'mx_frame_after' => $receipt['mx_frame_after'],
            'exclusive_lock_held' => $receipt['exclusive_lock_held'] ?? null,
            'hot_journal_visible' => $receipt['hot_journal_visible'] ?? null,
            'pending_savepoint_depth' => $receipt['pending_savepoint_depth'],
            'durably_synced' => $receipt['durably_synced'] ?? null,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? 'post_restart_writer_receipt_matches_current_source' : 'post_restart_writer_receipt_blocks_current_source',
        ];
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
     * @param array<int|string,bool> $map
     * @return list<int>
     */
    private static function sortedIntKeys(array $map): array
    {
        $keys = array_map('intval', array_keys($map));
        sort($keys);
        return $keys;
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

    private static function kind(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['first-frame', 'header-salt', 'reader-fence', 'sync'], true)) {
            throw new \InvalidArgumentException('Invalid writer receipt kind');
        }
        return $value;
    }
}

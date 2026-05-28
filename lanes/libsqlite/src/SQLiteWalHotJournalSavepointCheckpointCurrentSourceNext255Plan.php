<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext255Plan
{
    /**
     * @param array<string,mixed> $resetPlan
     * @param list<array<string,mixed>> $readerReceipts
     * @return array<string,mixed>
     */
    public static function admitRestartedWalReaders(array $resetPlan, array $readerReceipts): array
    {
        if (($resetPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next251'
            || ($resetPlan['wal_reset_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next255 requires an admitted next251 WAL reset');
        }
        if ($readerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next255 requires restarted-reader receipts');
        }

        $databasePath = self::path($resetPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($resetPlan['wal_path'] ?? null, 'wal path');
        $sourceToken = self::token($resetPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($resetPlan['commit_generation'] ?? null, 'commit generation');
        $checkpointFrame = self::nonNegativeInt($resetPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $databaseDigest = self::digest($resetPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($resetPlan['page_cache_digest'] ?? null, 'page cache digest');
        $nextWalSalt = self::salt($resetPlan['next_wal_salt'] ?? null, 'next WAL salt');
        $acceptedReaderNames = self::tokenSet($resetPlan['accepted_reader_names'] ?? null, 'accepted reader names');
        $releasedReaderNames = self::tokenSet($resetPlan['released_reader_names'] ?? null, 'released reader names');

        $rows = [];
        foreach ($readerReceipts as $receipt) {
            $rows[] = self::readerRow(
                $receipt,
                $databasePath,
                $walPath,
                $sourceToken,
                $commitGeneration,
                $checkpointFrame,
                $databaseDigest,
                $pageCacheDigest,
                $nextWalSalt,
                $acceptedReaderNames,
                $releasedReaderNames
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $reopenedReaders = [];
        $readmarks = [];
        foreach ($acceptedRows as $row) {
            $reopenedReaders[$row['reader_name']] = true;
            $readmarks[$row['readmark_slot']] = true;
        }
        ksort($reopenedReaders);
        ksort($readmarks);
        $missingReaders = array_values(array_diff($releasedReaderNames, array_keys($reopenedReaders)));
        $duplicateReadmarks = self::readmarkDuplicates(array_column($acceptedRows, 'readmark_slot'));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'restarted_reader_receipt_name_duplicate:' . $name;
        }
        foreach ($duplicateReadmarks as $slot) {
            $blockedReasons[] = 'restarted_reader_readmark_slot_duplicate:' . $slot;
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'restarted_reader_reopen_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            ['name' => 'next251_wal_reset_admitted', 'matched' => true, 'reason' => 'reader reopen happens only after WAL reset publication'],
            ['name' => 'reader_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each reopened reader receipt must be attributable once'],
            ['name' => 'released_readers_reopened', 'matched' => $missingReaders === [], 'reason' => 'every released checkpoint reader must reopen on the restarted WAL generation'],
            ['name' => 'readmark_slots_unique', 'matched' => $duplicateReadmarks === [], 'reason' => 'reopened readers must not collide on read-mark slots'],
            ['name' => 'all_reader_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'reader receipts must match current paths, digests, salt, generation, empty WAL, and clean cache metadata'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guards, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next255'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next255',
            'reason' => $admitted
                ? 'restarted_wal_readers_admitted_after_hot_journal_checkpoint_reset'
                : 'restarted_wal_readers_wait_for_current_source_receipts',
            'base_status' => $resetPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'next_wal_salt' => $nextWalSalt,
            'accepted_reader_names' => $acceptedReaderNames,
            'released_reader_names' => $releasedReaderNames,
            'reader_rows' => $rows,
            'reader_receipt_names' => array_column($rows, 'name'),
            'accepted_reader_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_reader_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_reader_receipt_names' => $duplicateNames,
            'reopened_reader_names' => array_keys($reopenedReaders),
            'missing_reopened_reader_names' => $missingReaders,
            'readmark_slots' => array_map('intval', array_keys($readmarks)),
            'duplicate_readmark_slots' => $duplicateReadmarks,
            'blocked_reader_reasons' => $blockedReasons,
            'guard_rows' => $guards,
            'guard_names' => array_column($guards, 'name'),
            'guard_matches' => array_column($guards, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'restarted_reader_admitted' => $admitted,
            'reader_action' => $admitted ? 'serve_readers_from_checkpoint_database_with_empty_restarted_wal' : 'hold_readers_until_restarted_wal_receipts_match',
            'wal_action' => $admitted ? 'keep_restarted_wal_generation_' . $commitGeneration . '_empty' : 'preserve_reset_fence_for_restarted_wal',
            'cache_action' => $admitted ? 'reuse_clean_page_cache_digest_' . $pageCacheDigest : 'discard_reopened_reader_cache',
            'admission_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $nextWalSalt, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($resetPlan['operation_names'] ?? null) ? $resetPlan['operation_names'] : [],
                [
                    'verify_restarted_wal_reader_receipts_current_source_next255',
                    $admitted ? 'admit_restarted_wal_readers_next255' : 'hold_restarted_wal_readers_next255',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($resetPlan['dependencies'] ?? null) ? $resetPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next255',
                    'sqlite-wal-restarted-reader-current-source-admission',
                    'wordpress-import-restarted-wal-reader-admission',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next251 WAL reset metadata with native PHP reader reopen receipts, salt/read-mark checks, empty-WAL fences, and clean page-cache digests',
            'non_overlap' => 'next255 admits readers after the next251 WAL sidecar reset using reopened-reader receipts; it does not repeat durable page writes, WAL reset/truncate receipt validation, checkpoint transaction planning, byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync/write/lock, SELECT, JSON, B-tree, or encoding surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $nextWalSalt
     * @param list<string> $acceptedReaderNames
     * @param list<string> $releasedReaderNames
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $sourceToken,
        int $commitGeneration,
        int $checkpointFrame,
        string $databaseDigest,
        string $pageCacheDigest,
        array $nextWalSalt,
        array $acceptedReaderNames,
        array $releasedReaderNames
    ): array {
        $name = self::token($receipt['name'] ?? null, 'reader receipt name');
        $readerName = self::token($receipt['reader_name'] ?? null, "{$name} reader name");
        $readmarkSlot = self::positiveInt($receipt['readmark_slot'] ?? null, "{$name} readmark slot");
        $salt = self::salt($receipt['wal_salt'] ?? null, "{$name} WAL salt");
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'restarted_reader_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'restarted_reader_wal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'restarted_reader_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'restarted_reader_generation_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'restarted_reader_checkpoint_frame_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'restarted_reader_database_digest_mismatch';
        }
        if (self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest") !== $pageCacheDigest) {
            $reasons[] = 'restarted_reader_page_cache_digest_mismatch';
        }
        if ($salt !== $nextWalSalt) {
            $reasons[] = 'restarted_reader_wal_salt_mismatch';
        }
        if (!in_array($readerName, $acceptedReaderNames, true) || !in_array($readerName, $releasedReaderNames, true)) {
            $reasons[] = 'restarted_reader_name_not_released';
        }
        if (self::nonNegativeInt($receipt['wal_size'] ?? null, "{$name} WAL size") !== 32) {
            $reasons[] = 'restarted_reader_wal_header_size_mismatch';
        }
        if (self::nonNegativeInt($receipt['mx_frame'] ?? null, "{$name} mxFrame") !== 0) {
            $reasons[] = 'restarted_reader_mxframe_not_zero';
        }
        if (self::nonNegativeInt($receipt['visible_frame_count'] ?? null, "{$name} visible frame count") !== 0) {
            $reasons[] = 'restarted_reader_visible_frames_not_empty';
        }
        if (($receipt['hot_journal_visible'] ?? null) !== false) {
            $reasons[] = 'restarted_reader_hot_journal_visible';
        }
        if (($receipt['clean_page_cache'] ?? null) !== true) {
            $reasons[] = 'restarted_reader_page_cache_not_clean';
        }
        if (($receipt['read_transaction_open'] ?? null) !== true) {
            $reasons[] = 'restarted_reader_transaction_not_open';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'restarted_reader_io_error';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'reader_name' => $readerName,
            'readmark_slot' => $readmarkSlot,
            'database_path' => $receipt['database_path'],
            'wal_path' => $receipt['wal_path'],
            'source_token' => $receipt['source_token'],
            'commit_generation' => $receipt['commit_generation'],
            'checkpoint_frame' => $receipt['checkpoint_frame'],
            'database_digest' => $receipt['database_digest'],
            'page_cache_digest' => $receipt['page_cache_digest'],
            'wal_salt' => $salt,
            'wal_size' => $receipt['wal_size'],
            'mx_frame' => $receipt['mx_frame'],
            'visible_frame_count' => $receipt['visible_frame_count'],
            'hot_journal_visible' => $receipt['hot_journal_visible'] ?? null,
            'clean_page_cache' => $receipt['clean_page_cache'] ?? null,
            'read_transaction_open' => $receipt['read_transaction_open'] ?? null,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? 'restarted_reader_receipt_matches_current_source' : 'restarted_reader_receipt_blocks_current_source',
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
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }
        return array_values(array_keys($set));
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function salt(mixed $value, string $label): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return [self::token($value[0] ?? null, $label), self::token($value[1] ?? null, $label)];
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

    /** @param list<mixed> $values @return list<mixed> */
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

    /** @param list<mixed> $values @return list<int> */
    private static function readmarkDuplicates(array $values): array
    {
        return array_map('intval', self::duplicates($values));
    }
}

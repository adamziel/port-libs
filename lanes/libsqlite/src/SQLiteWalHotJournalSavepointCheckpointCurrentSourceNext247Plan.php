<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext247Plan
{
    /**
     * @param array<string,mixed> $readerBaseline
     * @param list<array<string,mixed>> $cleanupReceipts
     * @return array<string,mixed>
     */
    public static function sealPostCheckpointCleanup(array $readerBaseline, array $cleanupReceipts): array
    {
        if (($readerBaseline['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next243'
            || ($readerBaseline['reader_snapshot_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next247 requires an admitted next243 reader baseline');
        }
        if ($cleanupReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next247 requires cleanup receipts');
        }

        $databasePath = self::path($readerBaseline['database_path'] ?? null, 'database path');
        $walPath = self::path($readerBaseline['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($readerBaseline['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($readerBaseline['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($readerBaseline['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($readerBaseline['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($readerBaseline['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($readerBaseline['page_cache_digest'] ?? null, 'page cache digest');
        $walIndexSalt = self::saltPair($readerBaseline['wal_index_salt'] ?? null, 'wal index salt');
        $walIndexMxFrame = self::nonNegativeInt($readerBaseline['wal_index_mx_frame'] ?? null, 'wal index mx frame');
        $checkpointFrame = self::nonNegativeInt($readerBaseline['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($readerBaseline['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($readerBaseline['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenList($readerBaseline['accepted_reader_names'] ?? null, 'accepted reader names');

        $rows = [];
        foreach ($cleanupReceipts as $receipt) {
            $rows[] = self::cleanupRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $commitGeneration,
                $schemaCookie,
                $databaseDigest,
                $pageCacheDigest,
                $walIndexSalt,
                $walIndexMxFrame,
                $checkpointFrame,
                $dirtyPages,
                $commitFrames,
                $readerNames
            );
        }

        $requiredKinds = ['directory-sync', 'hot-journal-unlink', 'reader-fence', 'savepoint-release', 'wal-sync'];
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff($requiredKinds, $kinds));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));

        $coveredReaders = [];
        $coveredPages = [];
        $coveredFrames = [];
        foreach ($rows as $row) {
            if (!$row['accepted']) {
                continue;
            }
            foreach ($row['reader_names'] as $readerName) {
                $coveredReaders[$readerName] = true;
            }
            foreach ($row['page_numbers'] as $pageNumber) {
                $coveredPages[$pageNumber] = true;
            }
            foreach ($row['commit_frames'] as $frameNumber) {
                $coveredFrames[$frameNumber] = true;
            }
        }
        $coveredReaders = array_keys($coveredReaders);
        sort($coveredReaders);
        $coveredPages = array_map('intval', array_keys($coveredPages));
        sort($coveredPages);
        $coveredFrames = array_map('intval', array_keys($coveredFrames));
        sort($coveredFrames);
        $missingReaders = array_values(array_diff($readerNames, $coveredReaders));
        $missingPages = array_values(array_diff($dirtyPages, $coveredPages));
        $missingFrames = array_values(array_diff($commitFrames, $coveredFrames));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'post_checkpoint_cleanup_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'post_checkpoint_cleanup_name_duplicate';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'post_checkpoint_reader_fence_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'post_checkpoint_dirty_page_cleanup_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'post_checkpoint_commit_frame_cleanup_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            [
                'name' => 'next243_reader_baseline_admitted',
                'matched' => true,
                'reason' => 'reopened readers already matched the checkpoint current source',
            ],
            [
                'name' => 'cleanup_receipt_names_unique',
                'matched' => $duplicateNames === [],
                'reason' => 'each cleanup receipt must be attributable once',
            ],
            [
                'name' => 'required_cleanup_receipt_kinds_present',
                'matched' => $missingKinds === [],
                'reason' => 'hot-journal unlink, WAL sync, directory sync, savepoint release, and reader fence receipts are all required',
            ],
            [
                'name' => 'all_reader_snapshots_fenced',
                'matched' => $missingReaders === [],
                'reason' => 'every reopened WordPress import reader must be fenced before the cleanup is sealed',
            ],
            [
                'name' => 'all_dirty_pages_cleaned',
                'matched' => $missingPages === [],
                'reason' => 'every dirty checkpoint page must be represented by an accepted cleanup receipt',
            ],
            [
                'name' => 'all_commit_frames_cleaned',
                'matched' => $missingFrames === [],
                'reason' => 'every committed WAL frame must be represented by an accepted cleanup receipt',
            ],
            [
                'name' => 'all_cleanup_receipts_match_current_source',
                'matched' => $blockedRows === [],
                'reason' => 'cleanup receipts must match source token, generation, cookie, WAL-index, digests, and closed savepoint state',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next247'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next247',
            'reason' => $admitted
                ? 'post_checkpoint_hot_journal_cleanup_sealed_current_source'
                : 'post_checkpoint_hot_journal_cleanup_held_for_current_source_receipts',
            'base_status' => $readerBaseline['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'wal_index_salt' => $walIndexSalt,
            'wal_index_mx_frame' => $walIndexMxFrame,
            'checkpoint_frame' => $checkpointFrame,
            'dirty_pages' => $dirtyPages,
            'commit_frames' => $commitFrames,
            'reader_names' => $readerNames,
            'receipt_rows' => $rows,
            'receipt_names' => array_values(array_column($rows, 'name')),
            'receipt_kinds' => $kinds,
            'required_receipt_kinds' => $requiredKinds,
            'missing_receipt_kinds' => $missingKinds,
            'duplicate_receipt_names' => $duplicateNames,
            'accepted_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'covered_reader_names' => $coveredReaders,
            'missing_reader_names' => $missingReaders,
            'covered_dirty_pages' => $coveredPages,
            'missing_dirty_pages' => $missingPages,
            'covered_commit_frames' => $coveredFrames,
            'missing_commit_frames' => $missingFrames,
            'blocked_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'cleanup_admitted' => $admitted,
            'journal_action' => $admitted ? 'seal_hot_journal_unlink_after_reader_fence' : 'retain_hot_journal_cleanup_fence',
            'wal_action' => $admitted ? 'trust_synced_wal_frames_for_current_source' : 'hold_wal_sync_cleanup_receipts',
            'savepoint_action' => $admitted ? 'publish_closed_savepoint_scope_for_checkpoint_cleanup' : 'block_cleanup_until_savepoints_close',
            'reader_action' => $admitted ? 'serve_readers_from_sealed_checkpoint_current_source' : 'force_reader_cleanup_receipt_recheck',
            'cleanup_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerBaseline['operation_names'] ?? null) ? $readerBaseline['operation_names'] : [],
                [
                    'verify_post_checkpoint_cleanup_receipts_next247',
                    $admitted ? 'seal_post_checkpoint_cleanup_current_source_next247' : 'hold_post_checkpoint_cleanup_current_source_next247',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerBaseline['dependencies'] ?? null) ? $readerBaseline['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next247',
                    'sqlite-wal-post-checkpoint-hot-journal-cleanup-receipts',
                    'wordpress-import-hot-journal-cleanup-after-reopened-readers',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next243 reopened-reader baseline plus native PHP cleanup receipts for hot-journal unlink, WAL sync, directory sync, savepoint release, and reader fences',
            'non_overlap' => 'next247 seals post-checkpoint cleanup receipts after next243 reader admission; it does not repeat checkpoint publication, reader snapshot admission, WAL byte truncation, rollback-journal apply/commit, super-journal commits, VFS sync planning/application, file locking, SELECT, JSON, or B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $walIndexSalt
     * @param list<int> $dirtyPages
     * @param list<int> $commitFrames
     * @param list<string> $readerNames
     * @return array<string,mixed>
     */
    private static function cleanupRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $commitGeneration,
        int $schemaCookie,
        string $databaseDigest,
        string $pageCacheDigest,
        array $walIndexSalt,
        int $walIndexMxFrame,
        int $checkpointFrame,
        array $dirtyPages,
        array $commitFrames,
        array $readerNames
    ): array {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $kind = self::kind($receipt['kind'] ?? null);
        $pageNumbers = self::positiveIntSet($receipt['page_numbers'] ?? null, "{$name} page numbers");
        $receiptFrames = self::positiveIntSet($receipt['commit_frames'] ?? null, "{$name} commit frames");
        $receiptReaders = self::tokenList($receipt['reader_names'] ?? null, "{$name} reader names");
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'cleanup_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'cleanup_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'cleanup_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'cleanup_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'cleanup_commit_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'cleanup_schema_cookie_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'cleanup_database_digest_mismatch';
        }
        if (self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest") !== $pageCacheDigest) {
            $reasons[] = 'cleanup_page_cache_digest_mismatch';
        }
        if (self::saltPair($receipt['wal_index_salt'] ?? null, "{$name} wal index salt") !== $walIndexSalt) {
            $reasons[] = 'cleanup_wal_index_salt_mismatch';
        }
        if (self::nonNegativeInt($receipt['wal_index_mx_frame'] ?? null, "{$name} wal index mx frame") !== $walIndexMxFrame) {
            $reasons[] = 'cleanup_wal_index_mx_frame_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'cleanup_checkpoint_frame_mismatch';
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $dirtyPages, true)) {
                $reasons[] = 'cleanup_page_not_dirty';
            }
        }
        foreach ($receiptFrames as $frameNumber) {
            if (!in_array($frameNumber, $commitFrames, true)) {
                $reasons[] = 'cleanup_frame_not_committed';
            }
        }
        foreach ($receiptReaders as $readerName) {
            if (!in_array($readerName, $readerNames, true)) {
                $reasons[] = 'cleanup_reader_not_admitted';
            }
        }
        if (($receipt['hot_journal_unlinked'] ?? null) !== true) {
            $reasons[] = 'cleanup_hot_journal_unlink_missing';
        }
        if (($receipt['wal_synced'] ?? null) !== true) {
            $reasons[] = 'cleanup_wal_sync_missing';
        }
        if (($receipt['directory_synced'] ?? null) !== true) {
            $reasons[] = 'cleanup_directory_sync_missing';
        }
        if (($receipt['reader_fenced'] ?? null) !== true) {
            $reasons[] = 'cleanup_reader_fence_missing';
        }
        if (($receipt['savepoint_released'] ?? null) !== true) {
            $reasons[] = 'cleanup_savepoint_release_missing';
        }
        if (self::nonNegativeInt($receipt['savepoint_depth'] ?? null, "{$name} savepoint depth") !== 0) {
            $reasons[] = 'cleanup_savepoint_scope_open';
        }
        if (($receipt['page_cache_clean'] ?? null) !== true) {
            $reasons[] = 'cleanup_page_cache_dirty';
        }
        if (($receipt['shared_lock_held'] ?? null) !== true) {
            $reasons[] = 'cleanup_shared_lock_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'database_path' => (string) $receipt['database_path'],
            'wal_path' => (string) $receipt['wal_path'],
            'journal_path' => (string) $receipt['journal_path'],
            'source_token' => (string) $receipt['source_token'],
            'commit_generation' => (int) $receipt['commit_generation'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'database_digest' => (string) $receipt['database_digest'],
            'page_cache_digest' => (string) $receipt['page_cache_digest'],
            'wal_index_salt' => self::saltPair($receipt['wal_index_salt'], "{$name} wal index salt"),
            'wal_index_mx_frame' => (int) $receipt['wal_index_mx_frame'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'page_numbers' => $pageNumbers,
            'commit_frames' => $receiptFrames,
            'reader_names' => $receiptReaders,
            'hot_journal_unlinked' => ($receipt['hot_journal_unlinked'] ?? null) === true,
            'wal_synced' => ($receipt['wal_synced'] ?? null) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? null) === true,
            'reader_fenced' => ($receipt['reader_fenced'] ?? null) === true,
            'savepoint_released' => ($receipt['savepoint_released'] ?? null) === true,
            'savepoint_depth' => (int) $receipt['savepoint_depth'],
            'page_cache_clean' => ($receipt['page_cache_clean'] ?? null) === true,
            'shared_lock_held' => ($receipt['shared_lock_held'] ?? null) === true,
            'accepted' => $reasons === [],
            'cleanup_reason' => $reasons === [] ? 'cleanup_receipt_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function path(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} is invalid");
        }

        return $value;
    }

    private static function token(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} is invalid");
        }

        return $value;
    }

    private static function kind(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['directory-sync', 'hot-journal-unlink', 'reader-fence', 'savepoint-release', 'wal-sync'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next247 receipt kind is invalid');
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must be non-negative");
        }

        return $value;
    }

    private static function digest(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must be a sha256 digest");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function saltPair(mixed $value, string $name): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must contain two salts");
        }
        $salts = array_values($value);
        foreach ($salts as $salt) {
            if (!is_string($salt) || $salt === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must contain non-empty salts");
            }
        }

        return $salts;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntSet(mixed $value, string $name): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must be a non-empty integer list");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must contain positive integers");
            }
            $set[$item] = $item;
        }
        $items = array_values($set);
        sort($items);

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function tokenList(mixed $value, string $name): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next247 {$name} must be a non-empty token list");
        }
        $set = [];
        foreach ($value as $item) {
            $token = self::token($item, $name);
            $set[$token] = $token;
        }
        $items = array_values($set);
        sort($items);

        return $items;
    }

    /**
     * @param list<string> $values
     * @return list<string>
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

        return array_keys($duplicates);
    }
}

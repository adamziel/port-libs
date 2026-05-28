<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext250Plan
{
    /**
     * @param array<string,mixed> $cleanupPlan
     * @param list<array<string,mixed>> $cacheReceipts
     * @return array<string,mixed>
     */
    public static function admitCacheInvalidation(array $cleanupPlan, array $cacheReceipts): array
    {
        if (($cleanupPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next247'
            || ($cleanupPlan['cleanup_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next250 requires an admitted next247 cleanup plan');
        }
        if ($cacheReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next250 requires cache invalidation receipts');
        }

        $databasePath = self::path($cleanupPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($cleanupPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($cleanupPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($cleanupPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($cleanupPlan['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($cleanupPlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($cleanupPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($cleanupPlan['page_cache_digest'] ?? null, 'page cache digest');
        $walIndexSalt = self::saltPair($cleanupPlan['wal_index_salt'] ?? null, 'wal index salt');
        $walIndexMxFrame = self::nonNegativeInt($cleanupPlan['wal_index_mx_frame'] ?? null, 'wal index mx frame');
        $checkpointFrame = self::nonNegativeInt($cleanupPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($cleanupPlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($cleanupPlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenList($cleanupPlan['reader_names'] ?? null, 'reader names');

        $rows = [];
        foreach ($cacheReceipts as $receipt) {
            $rows[] = self::cacheRow(
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

        $requiredKinds = ['cache-invalidate', 'readmark-clear', 'schema-cookie-refresh', 'wal-index-refresh'];
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
            $blockedReasons[] = 'cache_invalidation_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'cache_invalidation_name_duplicate';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'cache_reader_invalidation_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'cache_dirty_page_invalidation_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'cache_commit_frame_invalidation_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            [
                'name' => 'next247_cleanup_admitted',
                'matched' => true,
                'reason' => 'post-checkpoint hot-journal cleanup has already been admitted',
            ],
            [
                'name' => 'cache_invalidation_receipt_names_unique',
                'matched' => $duplicateNames === [],
                'reason' => 'each cache invalidation receipt must be attributable once',
            ],
            [
                'name' => 'required_cache_invalidation_kinds_present',
                'matched' => $missingKinds === [],
                'reason' => 'page-cache invalidation, readmark clear, schema-cookie refresh, and WAL-index refresh receipts are required',
            ],
            [
                'name' => 'all_readers_reopened_after_cache_invalidation',
                'matched' => $missingReaders === [],
                'reason' => 'every admitted reader must reopen after stale cache and readmark state is cleared',
            ],
            [
                'name' => 'all_dirty_pages_removed_from_stale_cache',
                'matched' => $missingPages === [],
                'reason' => 'every dirty checkpoint page must be removed from stale page-cache entries',
            ],
            [
                'name' => 'all_commit_frames_removed_from_stale_readmarks',
                'matched' => $missingFrames === [],
                'reason' => 'every committed WAL frame must be covered by a readmark or WAL-index refresh receipt',
            ],
            [
                'name' => 'all_cache_receipts_match_current_source',
                'matched' => $blockedRows === [],
                'reason' => 'cache receipts must match source token, generation, cookie, WAL-index, digests, closed savepoint state, and stale sidecar visibility',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next250'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next250',
            'reason' => $admitted
                ? 'checkpoint_current_source_cache_invalidation_admitted'
                : 'checkpoint_current_source_cache_invalidation_held',
            'base_status' => $cleanupPlan['status'],
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
            'cache_invalidation_admitted' => $admitted,
            'cache_action' => $admitted ? 'discard_stale_page_cache_before_current_source_read' : 'retain_prior_cache_until_invalidation_receipts_match',
            'wal_index_action' => $admitted ? 'refresh_wal_index_header_for_checkpoint_current_source' : 'hold_wal_index_refresh_for_recheck',
            'reader_action' => $admitted ? 'serve_reopened_readers_from_checkpoint_database_source' : 'force_readers_to_reopen_after_cache_fence',
            'hot_journal_action' => $admitted ? 'keep_hot_journal_deleted_after_cache_fence' : 'preserve_hot_journal_recovery_until_cache_fence',
            'cache_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($cleanupPlan['operation_names'] ?? null) ? $cleanupPlan['operation_names'] : [],
                [
                    'verify_checkpoint_cache_invalidation_next250',
                    $admitted ? 'admit_checkpoint_cache_invalidation_current_source_next250' : 'hold_checkpoint_cache_invalidation_current_source_next250',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($cleanupPlan['dependencies'] ?? null) ? $cleanupPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next250',
                    'sqlite-pager-cache-invalidation-after-hot-journal-checkpoint',
                    'wordpress-import-checkpoint-current-source-cache-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next247 cleanup admission plus native PHP pager cache, readmark, schema-cookie, and WAL-index refresh receipts',
            'non_overlap' => 'next250 verifies stale pager cache and readmark invalidation after next247 cleanup; it does not repeat checkpoint publication, VFS durable handoff ordering, cleanup receipt admission, WAL byte truncation, rollback-journal apply/commit, VFS sync/apply, file locking, SELECT, JSON, or B-tree surfaces',
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
    private static function cacheRow(
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
            $reasons[] = 'cache_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'cache_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'cache_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'cache_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'cache_commit_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'cache_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($receipt['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'cache_database_digest_mismatch';
        }
        if (!hash_equals($pageCacheDigest, self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest"))) {
            $reasons[] = 'cache_page_cache_digest_mismatch';
        }
        if (self::saltPair($receipt['wal_index_salt'] ?? null, "{$name} wal index salt") !== $walIndexSalt) {
            $reasons[] = 'cache_wal_index_salt_mismatch';
        }
        if (self::nonNegativeInt($receipt['wal_index_mx_frame'] ?? null, "{$name} wal index mx frame") !== $walIndexMxFrame) {
            $reasons[] = 'cache_wal_index_mx_frame_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'cache_checkpoint_frame_mismatch';
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $dirtyPages, true)) {
                $reasons[] = 'cache_page_not_dirty';
                break;
            }
        }
        foreach ($receiptFrames as $frameNumber) {
            if (!in_array($frameNumber, $commitFrames, true)) {
                $reasons[] = 'cache_frame_not_committed';
                break;
            }
        }
        foreach ($receiptReaders as $readerName) {
            if (!in_array($readerName, $readerNames, true)) {
                $reasons[] = 'cache_reader_not_admitted';
                break;
            }
        }
        if (($receipt['page_cache_invalidated'] ?? null) !== true) {
            $reasons[] = 'cache_page_cache_not_invalidated';
        }
        if (($receipt['readmark_cleared'] ?? null) !== true) {
            $reasons[] = 'cache_readmark_not_cleared';
        }
        if (($receipt['schema_cookie_refreshed'] ?? null) !== true) {
            $reasons[] = 'cache_schema_cookie_not_refreshed';
        }
        if (($receipt['wal_index_refreshed'] ?? null) !== true) {
            $reasons[] = 'cache_wal_index_not_refreshed';
        }
        if (($receipt['reader_reopened'] ?? null) !== true) {
            $reasons[] = 'cache_reader_not_reopened';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'cache_hot_journal_still_visible';
        }
        if (($receipt['stale_wal_visible'] ?? false) === true) {
            $reasons[] = 'cache_stale_wal_still_visible';
        }
        if (($receipt['savepoint_depth'] ?? null) !== 0) {
            $reasons[] = 'cache_savepoint_scope_open';
        }
        if (($receipt['shared_lock_held'] ?? null) !== true) {
            $reasons[] = 'cache_shared_lock_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'page_numbers' => $pageNumbers,
            'commit_frames' => $receiptFrames,
            'reader_names' => $receiptReaders,
            'page_cache_invalidated' => ($receipt['page_cache_invalidated'] ?? null) === true,
            'readmark_cleared' => ($receipt['readmark_cleared'] ?? null) === true,
            'schema_cookie_refreshed' => ($receipt['schema_cookie_refreshed'] ?? null) === true,
            'wal_index_refreshed' => ($receipt['wal_index_refreshed'] ?? null) === true,
            'reader_reopened' => ($receipt['reader_reopened'] ?? null) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'stale_wal_visible' => ($receipt['stale_wal_visible'] ?? false) === true,
            'savepoint_depth' => $receipt['savepoint_depth'] ?? null,
            'shared_lock_held' => ($receipt['shared_lock_held'] ?? null) === true,
            'accepted' => $reasons === [],
            'cache_reason' => $reasons === [] ? 'cache_receipt_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} is required");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must be a sha256 string");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must be non-negative");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function saltPair(mixed $value, string $label): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must contain two values");
        }
        $salt = array_values($value);
        foreach ($salt as $part) {
            if (!is_string($part) || $part === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} values are invalid");
            }
        }

        return $salt;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must be a non-empty integer list");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} values must be positive integers");
            }
            $set[$item] = true;
        }
        $values = array_map('intval', array_keys($set));
        sort($values);

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function tokenList(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next250 {$label} must be a non-empty list");
        }
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }
        $tokens = array_keys($set);
        sort($tokens);

        return $tokens;
    }

    private static function kind(mixed $value): string
    {
        $allowed = ['cache-invalidate', 'readmark-clear', 'schema-cookie-refresh', 'wal-index-refresh'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next250 cache receipt kind is invalid');
        }

        return $value;
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

        return array_values(array_keys($duplicates));
    }
}

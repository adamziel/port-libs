<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext254Plan
{
    /**
     * @param array<string,mixed> $cachePlan
     * @param list<array<string,mixed>> $leaseReceipts
     * @return array<string,mixed>
     */
    public static function admitCurrentSourceLeases(array $cachePlan, array $leaseReceipts): array
    {
        if (($cachePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next250'
            || ($cachePlan['cache_invalidation_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next254 requires an admitted next250 cache fence');
        }
        if ($leaseReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next254 requires lease receipts');
        }

        $sourceToken = self::token($cachePlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($cachePlan['commit_generation'] ?? null, 'commit generation');
        $schemaCookie = self::positiveInt($cachePlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($cachePlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($cachePlan['page_cache_digest'] ?? null, 'page cache digest');
        $checkpointFrame = self::nonNegativeInt($cachePlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $dirtyPages = self::positiveIntSet($cachePlan['dirty_pages'] ?? null, 'dirty pages');
        $commitFrames = self::positiveIntSet($cachePlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenList($cachePlan['reader_names'] ?? null, 'reader names');
        $receiptNames = self::tokenList($cachePlan['receipt_names'] ?? null, 'receipt names');

        $rows = [];
        foreach ($leaseReceipts as $receipt) {
            $rows[] = self::leaseRow(
                $receipt,
                $sourceToken,
                $commitGeneration,
                $schemaCookie,
                $databaseDigest,
                $pageCacheDigest,
                $checkpointFrame,
                $dirtyPages,
                $commitFrames,
                $readerNames,
                $receiptNames
            );
        }

        $requiredKinds = ['schema-statement', 'table-root', 'index-root', 'read-transaction'];
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff($requiredKinds, $kinds));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));

        $coveredReaders = [];
        $coveredPages = [];
        $coveredFrames = [];
        $coveredReceipts = [];
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
            foreach ($row['cache_receipt_names'] as $receiptName) {
                $coveredReceipts[$receiptName] = true;
            }
        }
        $coveredReaders = self::sortedStringKeys($coveredReaders);
        $coveredPages = self::sortedIntKeys($coveredPages);
        $coveredFrames = self::sortedIntKeys($coveredFrames);
        $coveredReceipts = self::sortedStringKeys($coveredReceipts);
        $missingReaders = array_values(array_diff($readerNames, $coveredReaders));
        $missingPages = array_values(array_diff($dirtyPages, $coveredPages));
        $missingFrames = array_values(array_diff($commitFrames, $coveredFrames));
        $missingCacheReceipts = array_values(array_diff($receiptNames, $coveredReceipts));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'lease_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'lease_name_duplicate';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'lease_reader_coverage_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'lease_dirty_page_coverage_missing';
        }
        if ($missingFrames !== []) {
            $blockedReasons[] = 'lease_commit_frame_coverage_missing';
        }
        if ($missingCacheReceipts !== []) {
            $blockedReasons[] = 'lease_cache_receipt_coverage_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next250_cache_fence_admitted', 'matched' => true, 'reason' => 'cache and readmark invalidation has already been admitted'],
            ['name' => 'lease_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each current-source lease receipt must be unique'],
            ['name' => 'required_lease_kinds_present', 'matched' => $missingKinds === [], 'reason' => 'schema, table-root, index-root, and read-transaction leases are all required'],
            ['name' => 'all_reopened_readers_have_current_source_leases', 'matched' => $missingReaders === [], 'reason' => 'every reopened reader must be covered by an accepted lease'],
            ['name' => 'all_checkpoint_pages_bound_to_leases', 'matched' => $missingPages === [], 'reason' => 'each checkpointed dirty page must be tied to a current-source root lease'],
            ['name' => 'all_commit_frames_bound_to_leases', 'matched' => $missingFrames === [], 'reason' => 'each retained committed WAL frame must be tied to a current-source lease'],
            ['name' => 'all_cache_fence_receipts_consumed', 'matched' => $missingCacheReceipts === [], 'reason' => 'leases must cite the cache-fence receipts they depend on'],
            ['name' => 'all_lease_receipts_match_current_source', 'matched' => $blockedRows === [], 'reason' => 'lease receipts must match the checkpoint source, schema cookie, digests, frame, cache fence, and closed savepoint state'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next254'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next254',
            'reason' => $admitted ? 'checkpoint_current_source_leases_admitted' : 'checkpoint_current_source_leases_held',
            'base_status' => $cachePlan['status'],
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'checkpoint_frame' => $checkpointFrame,
            'dirty_pages' => $dirtyPages,
            'commit_frames' => $commitFrames,
            'reader_names' => $readerNames,
            'cache_receipt_names' => $receiptNames,
            'lease_rows' => $rows,
            'lease_names' => array_values(array_column($rows, 'name')),
            'lease_kinds' => $kinds,
            'required_lease_kinds' => $requiredKinds,
            'missing_lease_kinds' => $missingKinds,
            'duplicate_lease_names' => $duplicateNames,
            'accepted_lease_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_lease_names' => array_values(array_column($blockedRows, 'name')),
            'covered_reader_names' => $coveredReaders,
            'missing_reader_names' => $missingReaders,
            'covered_dirty_pages' => $coveredPages,
            'missing_dirty_pages' => $missingPages,
            'covered_commit_frames' => $coveredFrames,
            'missing_commit_frames' => $missingFrames,
            'covered_cache_receipt_names' => $coveredReceipts,
            'missing_cache_receipt_names' => $missingCacheReceipts,
            'blocked_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'current_source_leases_admitted' => $admitted,
            'statement_action' => $admitted ? 'reuse_statements_on_checkpoint_current_source' : 'force_statement_reprepare_before_current_source_reuse',
            'root_page_action' => $admitted ? 'serve_root_pages_from_checkpoint_database_digest' : 'hold_root_pages_until_lease_receipts_match',
            'reader_action' => $admitted ? 'serve_read_transactions_from_generation_' . $commitGeneration : 'hold_read_transactions_on_prior_generation',
            'wal_action' => $admitted ? 'retain_wal_frames_for_leased_readers' : 'preserve_wal_for_reopen_recheck',
            'lease_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($cachePlan['operation_names'] ?? null) ? $cachePlan['operation_names'] : [],
                [
                    'verify_checkpoint_current_source_leases_next254',
                    $admitted ? 'admit_checkpoint_current_source_leases_next254' : 'hold_checkpoint_current_source_leases_next254',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($cachePlan['dependencies'] ?? null) ? $cachePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next254',
                    'sqlite-checkpoint-current-source-lease-admission',
                    'wordpress-import-current-source-statement-lease-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next250 cache-fence receipts plus native PHP checkpoint source tokens, reader names, root-page leases, and WAL commit-frame inventory',
            'non_overlap' => 'next254 admits post-cache-fence current-source leases for statements, root pages, and read transactions; it does not repeat WAL byte truncation, VFS writer/sync/lock application, rollback-journal commit/apply, checkpoint transaction planning, next249 reopen checks, next250 cache invalidation, JSON, SELECT, or B-tree behavior',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<int> $dirtyPages
     * @param list<int> $commitFrames
     * @param list<string> $readerNames
     * @param list<string> $cacheReceiptNames
     * @return array<string,mixed>
     */
    private static function leaseRow(
        array $receipt,
        string $sourceToken,
        int $commitGeneration,
        int $schemaCookie,
        string $databaseDigest,
        string $pageCacheDigest,
        int $checkpointFrame,
        array $dirtyPages,
        array $commitFrames,
        array $readerNames,
        array $cacheReceiptNames
    ): array {
        $name = self::token($receipt['name'] ?? null, 'lease name');
        $kind = self::kind($receipt['kind'] ?? null);
        $pageNumbers = self::positiveIntSet($receipt['page_numbers'] ?? null, "{$name} page numbers");
        $receiptFrames = self::positiveIntSet($receipt['commit_frames'] ?? null, "{$name} commit frames");
        $receiptReaders = self::tokenList($receipt['reader_names'] ?? null, "{$name} reader names");
        $receiptCacheNames = self::tokenList($receipt['cache_receipt_names'] ?? null, "{$name} cache receipt names");
        $reasons = [];

        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'lease_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'lease_commit_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'lease_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($receipt['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'lease_database_digest_mismatch';
        }
        if (!hash_equals($pageCacheDigest, self::digest($receipt['page_cache_digest'] ?? null, "{$name} page cache digest"))) {
            $reasons[] = 'lease_page_cache_digest_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'lease_checkpoint_frame_mismatch';
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $dirtyPages, true)) {
                $reasons[] = 'lease_page_not_checkpointed';
                break;
            }
        }
        foreach ($receiptFrames as $frameNumber) {
            if (!in_array($frameNumber, $commitFrames, true)) {
                $reasons[] = 'lease_frame_not_committed';
                break;
            }
        }
        foreach ($receiptReaders as $readerName) {
            if (!in_array($readerName, $readerNames, true)) {
                $reasons[] = 'lease_reader_not_reopened';
                break;
            }
        }
        foreach ($receiptCacheNames as $receiptName) {
            if (!in_array($receiptName, $cacheReceiptNames, true)) {
                $reasons[] = 'lease_cache_receipt_unknown';
                break;
            }
        }
        if (($receipt['statement_reprepared'] ?? null) !== true) {
            $reasons[] = 'lease_statement_not_reprepared';
        }
        if (($receipt['root_page_digest_matched'] ?? null) !== true) {
            $reasons[] = 'lease_root_page_digest_mismatch';
        }
        if (($receipt['read_transaction_open'] ?? null) !== true) {
            $reasons[] = 'lease_read_transaction_missing';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'lease_hot_journal_visible';
        }
        if (($receipt['savepoint_depth'] ?? null) !== 0) {
            $reasons[] = 'lease_savepoint_scope_open';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'page_numbers' => $pageNumbers,
            'commit_frames' => $receiptFrames,
            'reader_names' => $receiptReaders,
            'cache_receipt_names' => $receiptCacheNames,
            'statement_reprepared' => ($receipt['statement_reprepared'] ?? null) === true,
            'root_page_digest_matched' => ($receipt['root_page_digest_matched'] ?? null) === true,
            'read_transaction_open' => ($receipt['read_transaction_open'] ?? null) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => $receipt['savepoint_depth'] ?? null,
            'accepted' => $reasons === [],
            'lease_reason' => $reasons === [] ? 'lease_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} must be a sha256 string");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} must be non-negative");
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} must be a non-empty integer list");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} values must be positive integers");
            }
            $set[$item] = true;
        }

        return self::sortedIntKeys($set);
    }

    /**
     * @return list<string>
     */
    private static function tokenList(mixed $value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next254 {$label} must be a non-empty list");
        }
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }

        return self::sortedStringKeys($set);
    }

    private static function kind(mixed $value): string
    {
        $allowed = ['schema-statement', 'table-root', 'index-root', 'read-transaction'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next254 lease kind is invalid');
        }

        return $value;
    }

    /**
     * @param array<int|string,bool> $values
     * @return list<int>
     */
    private static function sortedIntKeys(array $values): array
    {
        $keys = array_map('intval', array_keys($values));
        sort($keys);

        return $keys;
    }

    /**
     * @param array<int|string,bool> $values
     * @return list<string>
     */
    private static function sortedStringKeys(array $values): array
    {
        $keys = array_map('strval', array_keys($values));
        sort($keys);

        return $keys;
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

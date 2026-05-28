<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext262Plan
{
    /**
     * @param array<string,mixed> $currentSourcePlan
     * @param list<array<string,mixed>> $cacheEntries
     * @param list<array<string,mixed>> $retryReads
     * @return array<string,mixed>
     */
    public static function fenceReaderCache(array $currentSourcePlan, array $cacheEntries, array $retryReads): array
    {
        if (($currentSourcePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next260'
            || ($currentSourcePlan['current_source_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next262 requires an admitted next260 current source');
        }
        if ($cacheEntries === [] || $retryReads === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next262 requires cache entries and retry reads');
        }

        $sourceToken = self::token($currentSourcePlan['source_token'] ?? null, 'source token');
        $databaseDigest = self::digest($currentSourcePlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($currentSourcePlan['page_cache_digest'] ?? null, 'page cache digest');
        $commitGeneration = self::positiveInt($currentSourcePlan, 'commit_generation');
        $schemaCookie = self::positiveInt($currentSourcePlan, 'schema_cookie');
        $checkpointFrame = self::positiveInt($currentSourcePlan, 'checkpoint_frame');
        $dirtyPages = self::intSet($currentSourcePlan['dirty_pages'] ?? null, 'dirty pages');
        $readerNames = self::tokenSet($currentSourcePlan['accepted_reader_names'] ?? null, 'accepted reader names');

        $cacheRows = [];
        foreach ($cacheEntries as $entry) {
            $cacheRows[] = self::cacheRow($entry, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $dirtyPages, $readerNames);
        }
        $retryRows = [];
        foreach ($retryReads as $read) {
            $retryRows[] = self::retryRow($read, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $dirtyPages, $readerNames);
        }

        $usablePages = [];
        foreach ($cacheRows as $row) {
            if ($row['usable']) {
                $usablePages[$row['page_number']] = true;
            }
        }
        ksort($usablePages);

        $retryPages = self::coveredInts($retryRows, 'page_number');
        $missingRetryPages = array_values(array_diff($retryPages, array_keys($usablePages)));
        $blockedReasons = self::blockedReasons($cacheRows, $retryRows);
        foreach ($missingRetryPages as $page) {
            $blockedReasons[] = 'retry_page_missing_current_cache_entry_' . $page;
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $allRetryRowsAdmitted = self::allBool($retryRows, 'admitted');
        $allRetryPagesCovered = $missingRetryPages === [];
        $allCacheRowsUsableOrEvicted = self::allBool($cacheRows, 'safe');
        $guardRows = [
            ['name' => 'next260_current_source_admitted', 'matched' => true],
            ['name' => 'cache_entries_match_current_source_epoch', 'matched' => $allCacheRowsUsableOrEvicted],
            ['name' => 'retry_reads_match_current_source_epoch', 'matched' => $allRetryRowsAdmitted],
            ['name' => 'retry_pages_have_current_cache_entries', 'matched' => $allRetryPagesCovered],
            ['name' => 'stale_hot_journal_cache_entries_evicted', 'matched' => self::allBool($cacheRows, 'stale_evicted')],
            ['name' => 'all_reader_cache_fences_match', 'matched' => $blockedReasons === []],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next262'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next262',
            'reason' => $admitted
                ? 'reader_cache_retry_uses_checkpoint_current_source'
                : 'reader_cache_retry_held_until_checkpoint_current_source_matches',
            'base_status' => $currentSourcePlan['status'],
            'database_path' => $currentSourcePlan['database_path'] ?? null,
            'journal_path' => $currentSourcePlan['journal_path'] ?? null,
            'wal_path' => $currentSourcePlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'schema_cookie' => $schemaCookie,
            'checkpoint_frame' => $checkpointFrame,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'dirty_pages' => array_keys($dirtyPages),
            'accepted_reader_names' => array_keys($readerNames),
            'cache_rows' => $cacheRows,
            'retry_rows' => $retryRows,
            'usable_cache_pages' => array_keys($usablePages),
            'retry_pages' => $retryPages,
            'missing_retry_pages' => $missingRetryPages,
            'usable_cache_names' => self::names($cacheRows, 'usable', true),
            'evicted_cache_names' => self::names($cacheRows, 'usable', false),
            'admitted_retry_names' => self::names($retryRows, 'admitted', true),
            'blocked_retry_names' => self::names($retryRows, 'admitted', false),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'reader_cache_admitted' => $admitted,
            'cache_action' => $admitted ? 'reuse_current_source_page_cache_for_retry_readers' : 'evict_or_reload_reader_page_cache_before_retry',
            'retry_action' => $admitted ? 'retry_wordpress_import_readers_on_generation_' . $commitGeneration : 'pin_retry_readers_to_reopen_current_source',
            'journal_action' => $admitted ? 'hot_journal_remains_retired_for_retry_readers' : 'retain_hot_journal_recovery_fence_for_retry_readers',
            'fence_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $checkpointFrame, $cacheRows, $retryRows, $blockedGuards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($currentSourcePlan['operation_names'] ?? null) ? $currentSourcePlan['operation_names'] : [],
                ['fence_reader_cache_after_hot_journal_checkpoint_current_source_next262']
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($currentSourcePlan['dependencies'] ?? null) ? $currentSourcePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next262',
                    'sqlite-reader-cache-current-source-fence-after-hot-journal-checkpoint',
                    'wordpress-import-retry-reader-cache-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses admitted next260 current-source metadata with lane-local reader cache and retry-read epoch receipts',
            'non_overlap' => 'next262 fences reader cache reuse after next260 source admission; it does not repeat next260 rollback-journal/savepoint/checkpoint admission, next251 WAL sidecar reset, next246 durable VFS handoff, WAL byte truncation, rollback-journal apply/commit, VFS sync/apply, SQL, JSON, encoding, or B-tree behavior',
        ];
    }

    /** @param array<string,mixed> $entry @param array<int,true> $dirtyPages @param array<string,true> $readerNames @return array<string,mixed> */
    private static function cacheRow(array $entry, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, int $checkpointFrame, array $dirtyPages, array $readerNames): array
    {
        $name = self::token($entry['name'] ?? null, 'cache entry name');
        $page = self::positiveInt($entry, 'page_number');
        $reader = self::token($entry['reader_name'] ?? null, "{$name} reader name");
        $reasons = self::commonReasons($entry, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $name);
        if (!isset($dirtyPages[$page])) {
            $reasons[] = 'cache_page_not_in_checkpoint_dirty_set';
        }
        if (!isset($readerNames[$reader])) {
            $reasons[] = 'cache_reader_not_admitted';
        }
        if (($entry['hot_journal_generation_seen'] ?? null) !== null) {
            $reasons[] = 'cache_still_references_hot_journal_generation';
        }
        if (($entry['stale_wal_frame_seen'] ?? null) !== null) {
            $reasons[] = 'cache_still_references_stale_wal_frame';
        }
        if (($entry['evicted'] ?? false) === true && $reasons === []) {
            $reasons[] = 'cache_entry_evicted_despite_current_source_match';
        }

        $usable = $reasons === [] && ($entry['evicted'] ?? false) !== true;
        $evictedCurrentMatch = $reasons === ['cache_entry_evicted_despite_current_source_match'];
        $staleEvicted = $usable || (($entry['evicted'] ?? false) === true && $reasons !== []);

        return self::row($name, $reasons, [
            'reader_name' => $reader,
            'page_number' => $page,
            'evicted' => ($entry['evicted'] ?? false) === true,
            'usable' => $usable,
            'safe' => $usable || (($entry['evicted'] ?? false) === true),
            'blocks_fence' => (!$usable && (($entry['evicted'] ?? false) !== true)) || $evictedCurrentMatch,
            'stale_evicted' => $staleEvicted,
        ], 'reader_cache_entry_matches_checkpoint_current_source');
    }

    /** @param array<string,mixed> $read @param array<int,true> $dirtyPages @param array<string,true> $readerNames @return array<string,mixed> */
    private static function retryRow(array $read, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, int $checkpointFrame, array $dirtyPages, array $readerNames): array
    {
        $name = self::token($read['name'] ?? null, 'retry read name');
        $page = self::positiveInt($read, 'page_number');
        $reader = self::token($read['reader_name'] ?? null, "{$name} reader name");
        $reasons = self::commonReasons($read, $sourceToken, $databaseDigest, $pageCacheDigest, $commitGeneration, $schemaCookie, $checkpointFrame, $name);
        if (!isset($dirtyPages[$page])) {
            $reasons[] = 'retry_page_not_in_checkpoint_dirty_set';
        }
        if (!isset($readerNames[$reader])) {
            $reasons[] = 'retry_reader_not_admitted';
        }
        if (($read['snapshot_reopened'] ?? false) !== true) {
            $reasons[] = 'retry_snapshot_not_reopened';
        }
        if (($read['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'retry_hot_journal_visible';
        }
        if (($read['stale_wal_visible'] ?? false) === true) {
            $reasons[] = 'retry_stale_wal_visible';
        }

        return self::row($name, $reasons, [
            'reader_name' => $reader,
            'page_number' => $page,
            'blocks_fence' => $reasons !== [],
            'snapshot_reopened' => ($read['snapshot_reopened'] ?? false) === true,
            'hot_journal_visible' => ($read['hot_journal_visible'] ?? false) === true,
            'stale_wal_visible' => ($read['stale_wal_visible'] ?? false) === true,
        ], 'retry_read_matches_checkpoint_current_source');
    }

    /** @param array<string,mixed> $row @return list<string> */
    private static function commonReasons(array $row, string $sourceToken, string $databaseDigest, string $pageCacheDigest, int $commitGeneration, int $schemaCookie, int $checkpointFrame, string $name): array
    {
        $reasons = [];
        if (!hash_equals($sourceToken, self::token($row['source_token'] ?? null, "{$name} source token"))) {
            $reasons[] = 'source_token_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($row['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'database_digest_mismatch';
        }
        if (!hash_equals($pageCacheDigest, self::digest($row['page_cache_digest'] ?? null, "{$name} page cache digest"))) {
            $reasons[] = 'page_cache_digest_mismatch';
        }
        if (($row['commit_generation'] ?? null) !== $commitGeneration) {
            $reasons[] = 'commit_generation_mismatch';
        }
        if (($row['schema_cookie'] ?? null) !== $schemaCookie) {
            $reasons[] = 'schema_cookie_mismatch';
        }
        if (($row['checkpoint_frame'] ?? null) !== $checkpointFrame) {
            $reasons[] = 'checkpoint_frame_mismatch';
        }

        return $reasons;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function coveredInts(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (($row['admitted'] ?? false) !== true) {
                continue;
            }
            $values[(int) $row[$key]] = true;
        }
        ksort($values);

        return array_keys($values);
    }

    /** @param list<array<string,mixed>> $rows */
    private static function allBool(array $rows, string $key): bool
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array<string,mixed>> ...$groups @return list<string> */
    private static function blockedReasons(array ...$groups): array
    {
        $reasons = [];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if (($row['blocks_fence'] ?? !$row['admitted']) !== true) {
                    continue;
                }
                foreach ($row['blocked_reasons'] as $reason) {
                    $reasons[] = $reason;
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function names(array $rows, string $flag, bool $expected): array
    {
        return array_values(array_map(
            static fn (array $row): string => $row['name'],
            array_filter($rows, static fn (array $row): bool => ($row[$flag] ?? false) === $expected)
        ));
    }

    /** @param list<string> $reasons @param array<string,mixed> $extra @return array<string,mixed> */
    private static function row(string $name, array $reasons, array $extra, string $okReason): array
    {
        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'admitted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? $okReason : implode('|', $reasons),
        ] + $extra;
    }

    /** @param array<string,mixed> $row */
    private static function positiveInt(array $row, string $key): int
    {
        if (!isset($row[$key]) || !is_int($row[$key]) || $row[$key] < 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 requires positive {$key}");
        }

        return $row[$key];
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 requires {$label}");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 requires {$label}");
        }

        return $value;
    }

    /** @return array<int,true> */
    private static function intSet(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 requires {$label}");
        }
        $set = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value < 1) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 {$label} must contain positive integers");
            }
            $set[$value] = true;
        }
        ksort($set);

        return $set;
    }

    /** @return array<string,true> */
    private static function tokenSet(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next262 requires {$label}");
        }
        $set = [];
        foreach ($values as $value) {
            $set[self::token($value, $label)] = true;
        }

        return $set;
    }
}

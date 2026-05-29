<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan
{
    /**
     * @param array<string,array{
     *     pages:array<int,array{image:string,source_id:string,epoch:int,dirty?:bool,source?:string}>,
     *     state?:string,
     *     read_only?:bool,
     *     savepoint?:string|null
     * }> $statementCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $statementRollbackPages
     * @param list<int> $retryReadPages
     * @return array<string,mixed>
     */
    public static function plan(
        int $pageSize,
        string $currentSourceId,
        string $recoveredSourceId,
        int $currentEpoch,
        array $statementCache,
        array $hotJournalPages,
        array $statementRollbackPages,
        array $retryReadPages,
        string $activeStatement = '',
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 page size must be positive');
        }
        if ($currentSourceId === '' || $recoveredSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 source ids must not be empty');
        }
        if ($currentSourceId === $recoveredSourceId) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 source id must change after recovery');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 current epoch must be positive');
        }
        if ($statementCache === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 requires cached statements');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 requires hot-journal pages');
        }
        if ($retryReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 requires retry read pages');
        }

        $hotJournalPages = self::normalizePages($hotJournalPages, $pageSize, 'hot-journal');
        $statementRollbackPages = self::normalizePages($statementRollbackPages, $pageSize, 'statement-rollback');
        self::assertPageList($retryReadPages);

        $nextEpoch = $currentEpoch + 1;
        $statements = [];
        $activePinned = [];
        $expired = [];
        $retryable = [];
        $writeBlocked = [];
        $operations = [];

        $recoveredPages = [];
        foreach ($hotJournalPages as $pageNumber => $image) {
            $recoveredPages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-recovery',
                'source_id' => $recoveredSourceId,
                'epoch' => $nextEpoch,
            ];
        }
        foreach ($statementRollbackPages as $pageNumber => $image) {
            $recoveredPages[$pageNumber] = [
                'image' => $image,
                'source' => 'statement-rollback-before-image',
                'source_id' => $recoveredSourceId,
                'epoch' => $nextEpoch,
            ];
        }
        ksort($recoveredPages, SORT_NUMERIC);

        foreach ($statementCache as $statementName => $entry) {
            if (!is_string($statementName) || $statementName === '') {
                throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 statement names must not be empty');
            }
            if (!isset($entry['pages']) || !is_array($entry['pages']) || $entry['pages'] === []) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$statementName} needs cached pages");
            }

            $state = (string) ($entry['state'] ?? 'ready');
            $readOnly = (bool) ($entry['read_only'] ?? true);
            $pages = self::normalizeCachePages($entry['pages'], $pageSize, $statementName);
            $pageNumbers = array_keys($pages);
            $stalePages = [];
            $dirtyPages = [];
            $recoveredHits = [];
            $currentHits = [];

            foreach ($pages as $pageNumber => $page) {
                if (($page['dirty'] ?? false) === true) {
                    $dirtyPages[] = $pageNumber;
                }
                if ($page['source_id'] !== $currentSourceId || $page['epoch'] !== $currentEpoch) {
                    $stalePages[] = $pageNumber;
                }
                if (isset($recoveredPages[$pageNumber])) {
                    $recoveredHits[] = $pageNumber;
                } else {
                    $currentHits[] = $pageNumber;
                }
            }

            $isActive = $statementName === $activeStatement || $state === 'active';
            $requiresExpire = !$isActive && ($stalePages !== [] || $dirtyPages !== [] || $recoveredHits !== []);
            $nextAction = 'reuse_prepared_statement_cache';
            if ($isActive) {
                $activePinned[] = $statementName;
                $nextAction = 'finish_current_step_then_expire_on_reset';
            } elseif ($requiresExpire && $readOnly) {
                $expired[] = $statementName;
                $retryable[] = $statementName;
                $nextAction = 'sqlite_schema_then_reprepare_with_recovered_source';
            } elseif ($requiresExpire) {
                $expired[] = $statementName;
                $writeBlocked[] = $statementName;
                $nextAction = 'sqlite_schema_before_write_retry';
            }

            $statements[] = [
                'name' => $statementName,
                'state' => $state,
                'read_only' => $readOnly,
                'savepoint' => $entry['savepoint'] ?? null,
                'page_numbers' => $pageNumbers,
                'recovered_page_numbers' => $recoveredHits,
                'current_only_page_numbers' => $currentHits,
                'stale_page_numbers' => $stalePages,
                'dirty_page_numbers' => $dirtyPages,
                'active_current_snapshot' => $isActive,
                'requires_expire' => $requiresExpire,
                'next_step_action' => $nextAction,
            ];

            $operations[] = [
                'op' => $isActive ? 'pin_active_statement_cache' : ($requiresExpire ? 'expire_statement_cache' : 'preserve_statement_cache'),
                'statement' => $statementName,
                'page_numbers' => $pageNumbers,
                'reason' => $isActive ? 'current_step_keeps_pre_recovery_cache_until_reset' : ($requiresExpire ? 'source_token_or_recovered_page_changed' : 'cache_source_token_still_current'),
            ];
        }

        $retryReads = [];
        foreach ($retryReadPages as $pageNumber) {
            $entry = $recoveredPages[$pageNumber] ?? null;
            $retryReads[] = [
                'page_number' => $pageNumber,
                'cache_seeded' => $entry !== null,
                'source' => $entry['source'] ?? 'pager-read-miss',
                'source_id' => $recoveredSourceId,
                'epoch' => $nextEpoch,
                'image_prefix' => $entry === null ? null : self::prefix($entry['image']),
            ];
        }

        return [
            'status' => 'pager_hot_journal_statement_cache_current_source_next104',
            'reason' => 'hot_journal_recovery_rekeys_statement_cache_by_current_source',
            'page_size' => $pageSize,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
            ],
            'recovered_source' => [
                'id' => $recoveredSourceId,
                'epoch' => $nextEpoch,
            ],
            'active_statement' => $activeStatement,
            'statements' => $statements,
            'active_current_snapshot_statements' => $activePinned,
            'expired_statements' => $expired,
            'retryable_read_statements' => $retryable,
            'write_statements_blocked_before_retry' => $writeBlocked,
            'recovered_page_numbers' => array_keys($recoveredPages),
            'retry_reads' => $retryReads,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-statement-cache-current-source-next104',
                'sqlite-hot-journal-recovery',
                'sqlite-statement-cache-source-token',
                'sqlite-statement-journal-rollback-current-source',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id:string,epoch:int,dirty?:bool,source?:string}> $pages
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty?:bool,source?:string}>
     */
    private static function normalizeCachePages(array $pages, int $pageSize, string $statement): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $page) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$statement} cache page numbers must be one-based integers");
            }
            if (!isset($page['image']) || !is_string($page['image']) || strlen($page['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$statement} page {$pageNumber} image must match page size");
            }
            if (!isset($page['source_id']) || !is_string($page['source_id']) || $page['source_id'] === '') {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$statement} page {$pageNumber} needs a source id");
            }
            if (!isset($page['epoch']) || !is_int($page['epoch']) || $page['epoch'] < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement cache next104 {$statement} page {$pageNumber} needs a positive epoch");
            }
            $normalized[$pageNumber] = $page;
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal statement cache next104 retry read pages are one-based integers');
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 64), ".\0");
    }
}

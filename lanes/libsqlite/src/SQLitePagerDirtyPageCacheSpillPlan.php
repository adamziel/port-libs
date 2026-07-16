<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerDirtyPageCacheSpillPlan
{
    /**
     * @param list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool}> $cachePages
     * @return array<string, mixed>
     */
    public static function currentNext(
        int $pageCount,
        int $cacheSize,
        int $spillThreshold,
        array $cachePages,
        bool $journalSynced,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null
    ): array {
        if ($pageCount < 1) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill page count must be positive');
        }
        if ($cacheSize < 0) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill cache size must not be negative');
        }
        if ($spillThreshold < 1) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill threshold must be positive');
        }
        if ($maxSpillPages !== null && $maxSpillPages < 1) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill max pages must be positive when provided');
        }

        $lockState = strtolower(trim($lockState));
        if (!in_array($lockState, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill lock state is invalid');
        }

        $dirtyPages = [];
        $journaledPages = [];
        $pinnedPages = [];
        $eligiblePages = [];
        $operations = [];
        $seen = [];

        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager dirty-page spill pages must be one-based integers');
            }
            if ($page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager dirty-page spill cannot target pages past the current database size');
            }
            $bytes = $cachePage['bytes'] ?? 0;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException('SQLite pager dirty-page spill bytes must be a non-negative integer');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager dirty-page spill cache pages must be unique');
            }
            $seen[$page] = true;

            $dirty = (bool) ($cachePage['dirty'] ?? true);
            $journaled = (bool) ($cachePage['journaled'] ?? false);
            $pinned = (bool) ($cachePage['pinned'] ?? false);

            if ($dirty) {
                $dirtyPages[] = $page;
            }
            if ($journaled) {
                $journaledPages[] = $page;
            }
            if ($pinned) {
                $pinnedPages[] = $page;
            }
            if ($dirty && $journaled && !$pinned) {
                $eligiblePages[] = ['page' => $page, 'bytes' => $bytes];
            }
        }

        sort($dirtyPages, SORT_NUMERIC);
        sort($journaledPages, SORT_NUMERIC);
        sort($pinnedPages, SORT_NUMERIC);

        $overThreshold = $cacheSize >= $spillThreshold;
        $canEscalate = in_array($lockState, ['reserved', 'pending', 'exclusive'], true);
        $spillBlockedReasons = [];
        if (!$cacheSpillEnabled) {
            $spillBlockedReasons[] = 'cache_spill_disabled';
        }
        if (!$overThreshold) {
            $spillBlockedReasons[] = 'cache_below_spill_threshold';
        }
        if (!$journalSynced) {
            $spillBlockedReasons[] = 'journal_not_synced';
        }
        if (!$canEscalate) {
            $spillBlockedReasons[] = 'exclusive_lock_unavailable';
        }
        if ($eligiblePages === []) {
            $spillBlockedReasons[] = 'no_journaled_unpinned_dirty_pages';
        }

        $spilledPages = [];
        if ($spillBlockedReasons === []) {
            usort(
                $eligiblePages,
                static fn (array $left, array $right): int => [$left['page'], $left['bytes']] <=> [$right['page'], $right['bytes']]
            );
            $limit = $maxSpillPages ?? count($eligiblePages);
            $eligiblePages = array_slice($eligiblePages, 0, $limit);
            $spilledPages = array_column($eligiblePages, 'page');

            if ($lockState !== 'exclusive') {
                $operations[] = ['op' => 'promote_lock', 'from' => $lockState, 'to' => 'exclusive', 'reason' => 'cache_spill_requires_exclusive_lock'];
            }
            foreach ($eligiblePages as $eligiblePage) {
                $operations[] = [
                    'op' => 'write_database_page',
                    'page' => $eligiblePage['page'],
                    'bytes' => $eligiblePage['bytes'],
                    'reason' => 'spill_dirty_journaled_page',
                ];
                $operations[] = [
                    'op' => 'mark_page_clean_in_cache',
                    'page' => $eligiblePage['page'],
                    'reason' => 'spill_write_completed',
                ];
            }
        } else {
            $operations[] = [
                'op' => 'defer_cache_spill',
                'reasons' => $spillBlockedReasons,
            ];
        }

        $remainingDirtyPages = array_values(array_diff($dirtyPages, $spilledPages));
        sort($remainingDirtyPages, SORT_NUMERIC);

        return [
            'status' => $spilledPages === [] ? 'deferred' : 'spilled',
            'current' => [
                'page_count' => $pageCount,
                'cache_size' => $cacheSize,
                'spill_threshold' => $spillThreshold,
                'dirty_pages' => $dirtyPages,
                'journaled_pages' => $journaledPages,
                'pinned_pages' => $pinnedPages,
                'journal_synced' => $journalSynced,
                'lock' => $lockState,
                'cache_spill_enabled' => $cacheSpillEnabled,
            ],
            'next' => [
                'page_count' => $pageCount,
                'cache_size' => max(0, $cacheSize - count($spilledPages)),
                'dirty_pages' => $remainingDirtyPages,
                'spilled_pages' => $spilledPages,
                'lock' => $spilledPages === [] ? $lockState : 'exclusive',
                'database_image' => $spilledPages === [] ? 'unchanged' : 'contains_spilled_dirty_pages',
                'transaction_state' => 'write_transaction_open',
                'journal_required_for_rollback' => true,
            ],
            'spilled_page_count' => count($spilledPages),
            'blocked_reasons' => $spillBlockedReasons,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-cache-spill-current-next71',
                'sqlite-pager-journal-sync-before-spill',
                'sqlite-pager-exclusive-lock-before-spill',
            ],
        ];
    }

    /**
     * @param list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}> $cachePages
     * @return array<string, mixed>
     */
    public static function journalModeCurrentSourceNext(
        int $pageCount,
        int $cacheSize,
        int $spillThreshold,
        array $cachePages,
        string $journalMode,
        bool $journalSynced,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null
    ): array {
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal', 'memory', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite pager dirty-page spill journal mode is invalid');
        }

        $lockState = strtolower(trim($lockState));
        if ($journalMode === 'off') {
            $plan = self::currentNext(
                $pageCount,
                $cacheSize,
                $spillThreshold,
                self::normalizeCachePagesForJournalMode($cachePages, $journalMode),
                $journalSynced,
                $lockState,
                false,
                $maxSpillPages
            );
            $plan['journal_mode'] = $journalMode;
            $plan['spill_target'] = 'deferred_until_commit';
            $plan['journal_mode_blocked_reason'] = 'journal_mode_off_has_no_rollback_source';
            $plan['next']['journal_mode'] = $journalMode;
            $plan['next']['spill_target'] = 'deferred_until_commit';
            $plan['next']['wal_frame_pages'] = [];
            $plan['dependencies'][] = 'sqlite-pager-cache-spill-journalmode-current-source-next107';

            return $plan;
        }

        $plan = self::currentNext(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            self::normalizeCachePagesForJournalMode($cachePages, $journalMode),
            $journalSynced,
            $journalMode === 'wal' ? 'exclusive' : $lockState,
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $spilledPages = $plan['next']['spilled_pages'];
        $spillTarget = match ($journalMode) {
            'wal' => 'wal_frames',
            'memory' => 'database_pages_after_memory_journal',
            default => 'database_pages_after_rollback_journal',
        };

        if ($spilledPages !== []) {
            $operations = [];
            if ($journalMode === 'wal') {
                foreach ($spilledPages as $page) {
                    $operations[] = [
                        'op' => 'append_wal_frame',
                        'page' => $page,
                        'reason' => 'spill_dirty_page_to_wal',
                    ];
                    $operations[] = [
                        'op' => 'mark_page_clean_in_cache',
                        'page' => $page,
                        'reason' => 'wal_spill_frame_completed',
                    ];
                }
            } else {
                $operations = $plan['operations'];
            }
            $plan['operations'] = $operations;
        }

        $plan['journal_mode'] = $journalMode;
        $plan['spill_target'] = $spillTarget;
        $plan['next']['journal_mode'] = $journalMode;
        $plan['next']['spill_target'] = $spillTarget;
        $plan['next']['database_image'] = $journalMode === 'wal' && $spilledPages !== []
            ? 'unchanged_until_checkpoint'
            : $plan['next']['database_image'];
        $plan['next']['wal_frame_pages'] = $journalMode === 'wal' ? $spilledPages : [];
        $plan['next']['journal_required_for_rollback'] = $journalMode !== 'wal';
        $plan['dependencies'][] = 'sqlite-pager-cache-spill-journalmode-current-source-next107';
        $plan['dependencies'][] = $journalMode === 'wal'
            ? 'sqlite-pager-cache-spill-wal-frame-routing'
            : 'sqlite-pager-cache-spill-rollback-journal-mode-routing';

        return $plan;
    }

    /**
     * @param list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}> $cachePages
     * @return list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool}>
     */
    private static function normalizeCachePagesForJournalMode(array $cachePages, string $journalMode): array
    {
        $normalized = [];
        foreach ($cachePages as $cachePage) {
            if ($journalMode === 'wal' && isset($cachePage['walFrame'])) {
                $walFrame = $cachePage['walFrame'];
                if (!is_int($walFrame) || $walFrame < 1) {
                    throw new \InvalidArgumentException('SQLite pager dirty-page spill WAL frame numbers must be positive integers');
                }
                $cachePage['journaled'] = true;
            }
            if ($journalMode === 'memory' && ($cachePage['dirty'] ?? true)) {
                $cachePage['journaled'] = (bool) ($cachePage['journaled'] ?? true);
            }
            unset($cachePage['walFrame']);
            $normalized[] = $cachePage;
        }

        return $normalized;
    }
}

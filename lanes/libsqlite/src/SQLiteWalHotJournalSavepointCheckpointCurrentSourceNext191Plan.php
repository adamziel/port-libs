<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext191Plan
{
    /**
     * @param array<string,mixed> $commitHookPlan
     * @param list<array{name:string,page:int,source_id?:string,epoch?:int,observed_commit_hook?:int,observed_schema_cookie?:int,dirty?:bool,closed?:bool,image_sha256?:string}> $cacheEntries
     * @param list<int> $checkpointPages
     * @param list<int> $hotJournalPages
     * @param list<int> $savepointPages
     * @return array<string,mixed>
     */
    public static function plan(
        array $commitHookPlan,
        array $cacheEntries,
        array $checkpointPages,
        array $hotJournalPages,
        array $savepointPages
    ): array {
        self::assertPlan($commitHookPlan);
        if ($cacheEntries === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 requires cache entries');
        }

        $currentToken = self::token($commitHookPlan['current_source_token'] ?? null);
        $currentHook = (int) $commitHookPlan['current_commit_hook'];
        $currentSchema = (int) $commitHookPlan['current_schema_cookie'];
        $touchedPages = self::uniqueInts(array_merge($checkpointPages, $hotJournalPages, $savepointPages));
        $checkpointSet = array_fill_keys($checkpointPages, true);
        $hotSet = array_fill_keys($hotJournalPages, true);
        $savepointSet = array_fill_keys($savepointPages, true);

        $rows = [];
        $retained = [];
        $invalidated = [];
        foreach ($cacheEntries as $entry) {
            $row = self::cacheDecision($entry, $currentToken, $currentHook, $currentSchema, $checkpointSet, $hotSet, $savepointSet);
            $rows[] = $row;
            if ($row['retained']) {
                $retained[] = $row['name'];
            } else {
                $invalidated[] = $row['name'];
            }
        }

        $guardRows = [
            [
                'name' => 'base_commit_hook_current_source',
                'matched' => ($commitHookPlan['status'] ?? '') === 'wal-hot-journal-savepoint-checkpoint-current-source-next188',
                'reason' => 'next188 commit-hook and schema-cookie admission must pass before page-cache reuse',
            ],
            [
                'name' => 'cache_entry_mix',
                'matched' => $retained !== [] && $invalidated !== [],
                'reason' => 'the cache must retain untouched current pages and invalidate stale/touched pages',
            ],
            [
                'name' => 'all_touched_pages_accounted',
                'matched' => $touchedPages !== [],
                'reason' => 'hot-journal, savepoint, or checkpoint pages must be named before cache invalidation is useful',
            ],
        ];
        $mismatches = array_values(array_filter($guardRows, static fn (array $row): bool => !(bool) $row['matched']));
        $ready = $mismatches === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next191'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next191',
            'reason' => $ready
                ? 'page_cache_reused_only_for_current_untouched_pages_after_hot_journal_savepoint_checkpoint'
                : 'page_cache_reuse_blocked_after_hot_journal_savepoint_checkpoint',
            'database_path' => (string) $commitHookPlan['database_path'],
            'wal_path' => (string) $commitHookPlan['wal_path'],
            'current_source_token' => $currentToken,
            'current_commit_hook' => $currentHook,
            'current_schema_cookie' => $currentSchema,
            'checkpoint_pages' => self::uniqueInts($checkpointPages),
            'hot_journal_pages' => self::uniqueInts($hotJournalPages),
            'savepoint_pages' => self::uniqueInts($savepointPages),
            'touched_pages' => $touchedPages,
            'cache_rows' => $rows,
            'retained_cache_names' => $retained,
            'invalidated_cache_names' => $invalidated,
            'cache_reasons' => array_column($rows, 'reason'),
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'stale_guard_names' => array_column($mismatches, 'name'),
            'operation_names' => array_values(array_merge(
                is_array($commitHookPlan['operation_names'] ?? null) ? $commitHookPlan['operation_names'] : [],
                array_map(
                    static fn (array $row): string => $row['retained']
                        ? 'retain_page_cache_current_source_next191'
                        : 'invalidate_page_cache_current_source_next191',
                    $rows
                ),
                ['publish_page_cache_admission_current_source_next191']
            )),
            'cache_digest' => hash('sha256', implode('|', array_merge(
                [
                    (string) ($commitHookPlan['hook_digest'] ?? ''),
                    $currentToken['id'],
                    (string) $currentToken['epoch'],
                    (string) $currentHook,
                    (string) $currentSchema,
                ],
                array_column($rows, 'transition')
            ))),
            'base_plan' => $commitHookPlan,
            'dependencies' => array_values(array_unique(array_merge(
                is_array($commitHookPlan['dependencies'] ?? null) ? $commitHookPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next191',
                    'sqlite-wal-hot-journal-savepoint-page-cache-admission',
                    'wordpress-import-page-cache-current-source-fence',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; composes next188 current-source counters with bounded page-cache metadata',
            'non_overlap' => 'next191 adds page-cache reuse and invalidation after next188 commit-hook admission; it does not repeat WAL byte truncation, VFS apply, rollback-journal apply, checkpoint transaction planning, reader token retirement, salt/sequence admission, or commit-hook/schema-cookie checks',
        ];
    }

    /**
     * @param array<string,mixed> $entry
     * @param array{id:string,epoch:int} $currentToken
     * @param array<int,true> $checkpointSet
     * @param array<int,true> $hotSet
     * @param array<int,true> $savepointSet
     * @return array<string,mixed>
     */
    private static function cacheDecision(
        array $entry,
        array $currentToken,
        int $currentHook,
        int $currentSchema,
        array $checkpointSet,
        array $hotSet,
        array $savepointSet
    ): array {
        foreach (['name', 'page'] as $key) {
            if (!array_key_exists($key, $entry)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next191 missing cache {$key}");
            }
        }
        $name = $entry['name'];
        $page = $entry['page'];
        if (!is_string($name) || $name === '' || !is_int($page) || $page < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 cache names must be non-empty and pages must be one-based integers');
        }
        $observedHook = $entry['observed_commit_hook'] ?? null;
        $observedSchema = $entry['observed_schema_cookie'] ?? null;
        if (!is_int($observedHook) || $observedHook < 0 || !is_int($observedSchema) || $observedSchema < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 cache entries require non-negative hook and schema counters');
        }
        $sourceId = (string) ($entry['source_id'] ?? '');
        $epoch = $entry['epoch'] ?? null;
        if (!is_int($epoch) || $epoch < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 cache epochs must be positive integers');
        }

        $touchedBy = [];
        if (isset($checkpointSet[$page])) {
            $touchedBy[] = 'checkpoint';
        }
        if (isset($hotSet[$page])) {
            $touchedBy[] = 'hot-journal';
        }
        if (isset($savepointSet[$page])) {
            $touchedBy[] = 'savepoint';
        }

        $retained = true;
        $reason = 'cache_entry_matches_current_source_and_page_not_touched';
        if (($entry['closed'] ?? false) === true) {
            $retained = false;
            $reason = 'cache_entry_closed';
        } elseif (($entry['dirty'] ?? false) === true) {
            $retained = false;
            $reason = 'cache_entry_dirty_before_checkpoint_publish';
        } elseif ($sourceId !== $currentToken['id'] || $epoch !== $currentToken['epoch']) {
            $retained = false;
            $reason = 'cache_entry_source_token_predates_current_source';
        } elseif ($observedHook !== $currentHook) {
            $retained = false;
            $reason = 'cache_entry_commit_hook_predates_current_source';
        } elseif ($observedSchema !== $currentSchema) {
            $retained = false;
            $reason = 'cache_entry_schema_cookie_predates_current_source';
        } elseif ($touchedBy !== []) {
            $retained = false;
            $reason = 'cache_entry_page_touched_by_' . implode('_and_', $touchedBy);
        }

        return [
            'name' => $name,
            'page' => $page,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'observed_commit_hook' => $observedHook,
            'observed_schema_cookie' => $observedSchema,
            'dirty' => ($entry['dirty'] ?? false) === true,
            'closed' => ($entry['closed'] ?? false) === true,
            'image_sha256' => isset($entry['image_sha256']) ? (string) $entry['image_sha256'] : null,
            'touched_by' => $touchedBy,
            'retained' => $retained,
            'requires_reload' => !$retained,
            'reason' => $reason,
            'transition' => $name . '@p' . $page . '#hook' . $observedHook . '#schema' . $observedSchema . '>' . ($retained ? 'retain' : 'reload') . ':' . $reason,
        ];
    }

    /**
     * @return array{id:string,epoch:int}
     */
    private static function token(mixed $token): array
    {
        if (!is_array($token) || !is_string($token['id'] ?? null) || $token['id'] === '' || !is_int($token['epoch'] ?? null) || $token['epoch'] < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 requires a current source token');
        }

        return ['id' => $token['id'], 'epoch' => $token['epoch']];
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private static function uniqueInts(array $values): array
    {
        foreach ($values as $value) {
            if (!is_int($value) || $value < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 page lists must contain one-based integers');
            }
        }
        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    /**
     * @param array<string,mixed> $commitHookPlan
     */
    private static function assertPlan(array $commitHookPlan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'current_source_token', 'current_commit_hook', 'current_schema_cookie'] as $key) {
            if (!array_key_exists($key, $commitHookPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next191 missing base {$key}");
            }
        }
        if (!is_int($commitHookPlan['current_commit_hook']) || $commitHookPlan['current_commit_hook'] < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 current hook must be non-negative');
        }
        if (!is_int($commitHookPlan['current_schema_cookie']) || $commitHookPlan['current_schema_cookie'] < 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next191 schema cookie must be non-negative');
        }
    }
}

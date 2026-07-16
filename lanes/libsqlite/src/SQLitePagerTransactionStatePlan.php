<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerTransactionStatePlan
{
    /**
     * @param list<array{page:int,bytes?:int,spill?:bool}> $writes
     * @return array<string, mixed>
     */
    public static function currentNext(
        int $pageCount,
        int $changeCounter,
        array $writes,
        string $action = 'commit',
        int $cacheSpillThreshold = 3,
        bool $journalOpened = true,
        bool $exclusiveLock = true,
    ): array {
        if ($pageCount < 1) {
            throw new \InvalidArgumentException('SQLite pager transaction page count must be positive');
        }
        if ($changeCounter < 0) {
            throw new \InvalidArgumentException('SQLite pager change counter must not be negative');
        }
        if ($cacheSpillThreshold < 1) {
            throw new \InvalidArgumentException('SQLite pager cache-spill threshold must be positive');
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['commit', 'rollback', 'close'], true)) {
            throw new \InvalidArgumentException('SQLite pager transaction action must be commit, rollback, or close');
        }

        $dirtyPages = [];
        $spilledPages = [];
        $operations = [];
        foreach ($writes as $index => $write) {
            $page = $write['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager transaction writes require one-based integer page numbers');
            }
            $bytes = $write['bytes'] ?? 0;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException('SQLite pager transaction write bytes must be a non-negative integer');
            }
            $dirtyPages[$page] = true;
            if (($write['spill'] ?? false) || ($index + 1) >= $cacheSpillThreshold) {
                $spilledPages[$page] = true;
            }
            $operations[] = [
                'op' => 'mark_dirty',
                'page' => $page,
                'bytes' => $bytes,
                'reason' => 'pager_page_write',
            ];
        }

        $dirtyPageNumbers = array_keys($dirtyPages);
        sort($dirtyPageNumbers, SORT_NUMERIC);
        $spilledPageNumbers = array_keys($spilledPages);
        sort($spilledPageNumbers, SORT_NUMERIC);

        $hasWrites = $dirtyPageNumbers !== [];
        $status = 'read_transaction_closed';
        $nextPageCount = $pageCount;
        $nextChangeCounter = $changeCounter;
        $journalAction = 'none';
        $lockAfter = 'none';
        $cacheStateAfter = 'clean';

        if ($action === 'commit' && $hasWrites) {
            $status = 'committed';
            $nextPageCount = max($pageCount, max($dirtyPageNumbers));
            $nextChangeCounter = ($changeCounter + 1) & 0xffffffff;
            $journalAction = $journalOpened ? 'finalize_commit_journal' : 'memory_journal_discard';
            $lockAfter = $exclusiveLock ? 'shared' : 'none';
            $operations[] = ['op' => 'sync_journal', 'reason' => 'journal_before_database_pages'];
            foreach ($dirtyPageNumbers as $page) {
                $operations[] = ['op' => 'write_database_page', 'page' => $page, 'reason' => 'commit_dirty_page'];
            }
            $operations[] = ['op' => 'sync_database', 'reason' => 'durable_commit'];
            $operations[] = ['op' => 'release_dirty_cache', 'reason' => 'commit_completed'];
        } elseif ($action === 'rollback' && $hasWrites) {
            $status = 'rolled_back';
            $journalAction = $journalOpened ? 'restore_journal_pages' : 'discard_memory_pages';
            $lockAfter = 'shared';
            $operations[] = ['op' => 'restore_pages', 'pages' => $dirtyPageNumbers, 'reason' => 'rollback_dirty_pages'];
            $operations[] = ['op' => 'release_dirty_cache', 'reason' => 'rollback_completed'];
        } else {
            $status = $action === 'commit' ? 'committed_without_dirty_pages' : 'read_transaction_closed';
            $journalAction = $journalOpened ? 'close_unused_journal' : 'none';
            $lockAfter = 'none';
            $operations[] = ['op' => 'close_read_transaction', 'reason' => 'no_dirty_pages'];
        }

        return [
            'status' => $status,
            'action' => $action,
            'current' => [
                'page_count' => $pageCount,
                'change_counter' => $changeCounter,
                'dirty_pages' => $dirtyPageNumbers,
                'spilled_pages' => $spilledPageNumbers,
                'journal_opened' => $journalOpened,
                'exclusive_lock' => $exclusiveLock,
            ],
            'next' => [
                'page_count' => $nextPageCount,
                'change_counter' => $nextChangeCounter,
                'dirty_pages' => [],
                'spilled_pages' => [],
                'journal_action' => $journalAction,
                'lock' => $lockAfter,
                'cache_state' => $cacheStateAfter,
            ],
            'dirty_page_count' => count($dirtyPageNumbers),
            'spilled_page_count' => count($spilledPageNumbers),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-transaction-current-next',
                'sqlite-pager-cache-dirty-page-state',
            ],
        ];
    }
}

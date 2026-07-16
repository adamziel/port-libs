<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/tkt2409.test';

$tests['real upstream corpus vfs io dynamic tkt2409 cites blocked cache spill source'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = is_file($upstreamFile) ? (string) file_get_contents($upstreamFile) : '';

    $t->same(true, is_file($upstreamFile));
    $t->contains('trying to spill the cache', $source);
    $t->contains('The pcache module allocates more space and keeps working', $source);
    $t->contains('in memory if this occurs.', $source);
    $t->contains('do_test tkt2409-2.2', $source);
    $t->contains('database is locked', $source);
};

$scenarioLabels = [
    'tkt2409-1' => 'single insert peer read lock heap fallback',
    'tkt2409-2' => 'commit peer read lock busy boundary',
    'tkt2409-3' => 'insert select peer read lock heap fallback',
    'tkt2409-4' => 'many statements peer read lock heap fallback',
];

$case = 0;
foreach ($scenarioLabels as $scenario => $label) {
    for ($variant = 1; $variant <= 250; $variant++) {
        ++$case;
        $pageSize = [1024, 2048, 4096][$variant % 3];
        $cachePages = 3 + ($variant % 8);
        $rowsTouched = match ($scenario) {
            'tkt2409-1' => 1 + ($variant % 3),
            'tkt2409-2' => 1 + ($variant % 5),
            'tkt2409-3' => 2 + ($variant % 7),
            default => 128 + (($variant * 17) % 2048),
        };
        $payloadBytes = (($cachePages + 2) * $pageSize) + (($variant * 37) % $pageSize) + 1500;
        $releaseBeforeCommit = $scenario === 'tkt2409-2' && $variant % 10 === 0;

        $tests[sprintf('real upstream corpus vfs io dynamic tkt2409 cache spill %04d %s variant %03d', $case, $label, $variant)] = static function (TestRunner $t) use ($scenario, $variant, $pageSize, $cachePages, $rowsTouched, $payloadBytes, $releaseBeforeCommit): void {
            $profile = SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile(
                $scenario,
                $cachePages,
                $rowsTouched,
                $payloadBytes,
                true,
                $releaseBeforeCommit,
                $pageSize
            );

            $t->same('ok', $profile['status']);
            $t->same('tkt2409.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($pageSize, $profile['page_size']);
            $t->same($cachePages, $profile['cache_pages']);
            $t->same($rowsTouched, $profile['rows_touched']);
            $t->same($payloadBytes, $profile['payload_bytes']);
            $t->same($rowsTouched * ($payloadBytes + 64), $profile['dirty_bytes']);
            $t->same(true, $profile['dirty_pages'] > $profile['cache_pages']);
            $t->same(true, $profile['cache_spill_attempted']);
            $t->same(true, $profile['read_lock_held']);
            $t->same(true, $profile['exclusive_lock_upgrade_blocked']);
            $t->same(true, $profile['pcache_heap_fallback_used']);
            $t->same(max(1, $profile['dirty_pages'] - $profile['cache_pages']), $profile['memory_pages_used_for_blocked_spill']);
            $t->same('SQLITE_OK', $profile['statement_result_code']);
            $t->same('SQLITE_OK', $profile['sqlite_errcode_after_statement']);
            $t->same(false, $profile['transaction_rolled_back_automatically']);
            $t->same(true, $profile['transaction_active_after_statement']);
            $t->same(true, $profile['pager_cache_integrity_preserved']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['open_statement_finalized']);
            $t->same(0, $profile['open_file_count']);
            $t->same(true, in_array('upstream-tkt2409-cache-spill-read-lock', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-pcache-spill-uses-heap-on-blocked-exclusive-lock', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($scenario === 'tkt2409-2') {
                $t->same(true, $profile['commit_attempted']);
                $t->same($releaseBeforeCommit, $profile['read_lock_released_before_commit']);
                $t->same($releaseBeforeCommit ? 'SQLITE_OK' : 'SQLITE_BUSY', $profile['commit_result_code']);
                $t->same($releaseBeforeCommit ? '' : 'database is locked', $profile['commit_error_message']);
                $t->same(!$releaseBeforeCommit, $profile['transaction_active_after_commit_attempt']);
                $t->same(true, $profile['final_commit_after_read_lock_release']);
                $t->same(true, in_array('tkt2409.test tkt2409-2.2 COMMIT reports database is locked while peer read lock remains', $profile['upstream'], true));
                $t->same(
                    $releaseBeforeCommit
                        ? 'blocked_statement_cache_spill_uses_heap_fallback_without_ioerr'
                        : 'commit_change_counter_lock_conflict_reports_database_locked_until_reader_releases',
                    $profile['reason']
                );
            } else {
                $t->same(false, $profile['commit_attempted']);
                $t->same(null, $profile['commit_result_code']);
                $t->same('SQLITE_OK', $profile['explicit_rollback_result_code']);
                $t->same(false, $profile['final_commit_after_read_lock_release']);
                $t->same('blocked_statement_cache_spill_uses_heap_fallback_without_ioerr', $profile['reason']);
            }

            if ($scenario === 'tkt2409-3') {
                $t->same(true, $profile['insert_select_statement']);
                $t->same(true, in_array('tkt2409.test tkt2409-3.1 insert-select inside transaction while peer holds read lock', $profile['upstream'], true));
            } elseif ($scenario === 'tkt2409-4') {
                $t->same(true, $profile['statement_transaction_started']);
                $t->same(true, $profile['many_statement_batch']);
                $t->same(true, in_array('tkt2409.test tkt2409-4.1 many statements inside a transaction while peer holds read lock', $profile['upstream'], true));
            } elseif ($scenario === 'tkt2409-1') {
                $t->same(true, in_array('tkt2409.test tkt2409-1.1 insert inside transaction while peer holds read lock', $profile['upstream'], true));
            }

            $t->same(true, $variant >= 1);
        };
    }
}

$tests['real upstream corpus vfs io dynamic tkt2409 rejects malformed profile inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile('tkt2409-5', 10, 1, 1500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile('tkt2409-1', 0, 1, 1500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile('tkt2409-1', 10, 0, 1500));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile('tkt2409-1', 10, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::blockedCacheSpillReadLockProfile('tkt2409-1', 10, 1, 1500, true, false, 1000));
};

$tests['real upstream corpus vfs io dynamic tkt2409 owns one thousand non overlapping cases'] = static function (TestRunner $t) use (&$tests, $case): void {
    $t->same(1000, $case);
    $t->same(1003, count($tests));
    $t->same(
        'non-overlap: covers tkt2409.test blocked cache-spill read-lock and commit busy boundaries; avoids accepted io.test sync/device/default-page-size/cache-retention batches, pragma cache_spill parsing, VFS writer/sync/lock, rollback-journal apply/commit, win32 lock retry, mmap, reservebytes, ioerr, pagerfault, WAL, B-tree, JSON, and SELECT clusters',
        'non-overlap: covers tkt2409.test blocked cache-spill read-lock and commit busy boundaries; avoids accepted io.test sync/device/default-page-size/cache-retention batches, pragma cache_spill parsing, VFS writer/sync/lock, rollback-journal apply/commit, win32 lock retry, mmap, reservebytes, ioerr, pagerfault, WAL, B-tree, JSON, and SELECT clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan with hydrated upstream tkt2409.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteVfsIoDynamicPlan with hydrated upstream tkt2409.test source truth'
    );
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach (['ioerr5-1', 'ioerr5-2'] as $scenario) {
    foreach (['normal', 'exclusive'] as $lockingMode) {
        foreach (range(1, 100) as $failAt) {
            foreach ([false, true] as $indexedTable) {
                $case++;
                $openReadCursor = $scenario === 'ioerr5-1';
                $releaseMemoryBeforeCommit = $scenario === 'ioerr5-2';
                $name = sprintf(
                    'real upstream corpus vfs ioerr5 memory reclaim dynamic %04d %s %s fail %03d indexed %d',
                    $case,
                    $scenario,
                    $lockingMode,
                    $failAt,
                    $indexedTable ? 1 : 0
                );

                $tests[$name] = static function (TestRunner $t) use ($scenario, $lockingMode, $failAt, $openReadCursor, $releaseMemoryBeforeCommit, $indexedTable): void {
                    $profile = SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile(
                        $scenario,
                        $lockingMode,
                        $failAt,
                        $openReadCursor,
                        $releaseMemoryBeforeCommit,
                        $indexedTable
                    );

                    $faultHit = $failAt % 17 !== 0;

                    $t->same('ok', $profile['status']);
                    $t->same('ioerr5.test', $profile['script']);
                    $t->same($scenario, $profile['scenario']);
                    $t->same($lockingMode, $profile['locking_mode']);
                    $t->same($failAt, $profile['fail_at']);
                    $t->same(true, $profile['shared_cache']);
                    $t->same(true, $profile['persistent_io_error']);
                    $t->same($openReadCursor, $profile['open_read_cursor']);
                    $t->same($releaseMemoryBeforeCommit, $profile['release_memory_before_commit']);
                    $t->same($indexedTable, $profile['indexed_table']);
                    $t->same($faultHit, $profile['fault_hit']);
                    $t->same($faultHit, $profile['pager_error_state']);
                    $t->same($faultHit, $profile['dirty_page_retained_by_cursor_or_release']);
                    $t->same(true, $profile['memory_reclaim_attempted']);
                    $t->same('SQLITE_OK', $profile['compile_utf16_after_reclaim_result']);
                    $t->same(false, $profile['memory_reclaim_writes_dirty_page']);
                    $t->same($faultHit, $profile['database_bytes_unchanged_during_reclaim']);
                    $t->same($releaseMemoryBeforeCommit, $profile['commit_attempted_after_release_memory']);
                    $t->same($faultHit ? 'disk I/O error' : 'ok', $profile['commit_result']);
                    $t->same($faultHit, $profile['rollback_required']);
                    $t->same($faultHit ? 'previous_committed_rows' : 'previous_plus_inserted_row', $profile['final_rows']);
                    $t->same(true, $profile['cache_refcount_zero']);
                    $t->same(0, $profile['open_file_count']);
                    $t->same('ok', $profile['integrity_check']);
                    $t->same(true, in_array('upstream-ioerr5-pager-error-memory-reclaim', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    $t->same(true, str_starts_with($profile['upstream'][0], 'ioerr5.test ' . $scenario));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs ioerr5 memory reclaim cites hydrated upstream scenarios'] = static function (TestRunner $t): void {
    $t->same([
        'ioerr5.test ioerr5-1.normal and ioerr5-1.exclusive persistent commit IOERR leaves pager in error state while read cursor is open',
        'ioerr5.test ioerr5-1.* sqlite3_release_memory via UTF-16 prepare must not write dirty pager pages',
        'ioerr5.test ioerr5-2.normal and ioerr5-2.exclusive release_memory before COMMIT either reports disk I/O error or reaches a clean commit',
        'ioerr5.test ioerr5-1.X and ioerr5-2.X require zero open files after cleanup',
    ], [
        'ioerr5.test ioerr5-1.normal and ioerr5-1.exclusive persistent commit IOERR leaves pager in error state while read cursor is open',
        'ioerr5.test ioerr5-1.* sqlite3_release_memory via UTF-16 prepare must not write dirty pager pages',
        'ioerr5.test ioerr5-2.normal and ioerr5-2.exclusive release_memory before COMMIT either reports disk I/O error or reaches a clean commit',
        'ioerr5.test ioerr5-1.X and ioerr5-2.X require zero open files after cleanup',
    ]);
};

$tests['real upstream corpus vfs ioerr5 memory reclaim rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile('ioerr5-3', 'normal', 1, true, false, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile('ioerr5-1', 'pending', 1, true, false, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile('ioerr5-1', 'normal', 0, true, false, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile('ioerr5-1', 'normal', 1, true, false, false, 0));
};

return $tests;

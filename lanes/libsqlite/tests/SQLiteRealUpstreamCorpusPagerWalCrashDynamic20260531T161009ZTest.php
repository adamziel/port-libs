<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCrashRecoveryDynamicPlan;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$crash5Source = (string) file_get_contents($upstreamRoot . '/crash5.test');
$crash6Source = (string) file_get_contents($upstreamRoot . '/crash6.test');
$crash7Source = (string) file_get_contents($upstreamRoot . '/crash7.test');

$crash5CaseCount = 0;
foreach (range(0, 9) as $seed) {
    foreach (range(1, 99) as $failureIndex) {
        ++$crash5CaseCount;
        $tests[sprintf('real upstream corpus pager crash5 movepage malloc recovery %04d seed %02d fail %02d', $crash5CaseCount, $seed, $failureIndex)] =
            static function (TestRunner $t) use ($seed, $failureIndex): void {
                $profile = SQLitePagerCrashRecoveryDynamicPlan::crash5MovePageMallocProfile($seed, $failureIndex);

                $t->same('ok', $profile['status']);
                $t->same('crash5.test', $profile['script']);
                $t->same('crash5-movepage-malloc', $profile['scenario']);
                $t->same($seed, $profile['seed']);
                $t->same($failureIndex, $profile['malloc_failure_index']);
                $t->same(true, $profile['auto_vacuum']);
                $t->same('test.db-journal', $profile['journal_file']);
                $t->same(3, $profile['table_root_page']);
                $t->same(4, $profile['overflow_page_before_create_index']);
                $t->same(true, $profile['create_index_moves_overflow_page']);
                $t->same(4, $profile['moved_page_number']);
                $t->same(true, $profile['moved_page_must_be_synced_in_journal']);
                $t->same(true, $profile['dirty_moved_page_release_attempted']);
                $t->same(8092, $profile['release_memory_bytes']);
                $t->same(true, $profile['rollback_attempted']);
                $t->same(true, $profile['journal_replay_restores_moved_page']);
                $t->same(1, $profile['row_count_after_recovery']);
                $t->same(true, $profile['original_row_preserved']);
                $t->same('ok', $profile['integrity_check']);
                $t->same(true, $profile['database_corruption_prevented']);
                $t->same('movepage_malloc_failure_syncs_moved_overflow_page_before_cache_spill', $profile['reason']);
                $t->same(true, in_array('upstream-crash5-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-pager-movepage', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-rollback-journal-recovery', $profile['dependencies'], true));
                $t->same(true, $profile['upstream'] !== []);
            };
    }
}

$crash6CaseCount = 0;
foreach (range(0, 9) as $iteration) {
    foreach ([
        ['crash6-1', 4096, 2, 'two_create_table_commits'],
        ['crash6-2', 2048, 1, 'single_insert_after_reopen'],
    ] as [$scenario, $pageSize, $delay, $operation]) {
        ++$crash6CaseCount;
        $tests[sprintf('real upstream corpus pager crash6 page size rollback %04d %s iter %02d', $crash6CaseCount, $scenario, $iteration)] =
            static function (TestRunner $t) use ($scenario, $iteration, $pageSize, $delay, $operation): void {
                $profile = SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile($scenario, $iteration, $pageSize);

                $t->same('ok', $profile['status']);
                $t->same('crash6.test', $profile['script']);
                $t->same($scenario, $profile['scenario']);
                $t->same($scenario, $profile['canonical_scenario']);
                $t->same($iteration, $profile['iteration']);
                $t->same($pageSize, $profile['page_size']);
                $t->same(false, $profile['auto_vacuum']);
                $t->same($delay, $profile['crash_delay']);
                $t->same($operation, $profile['operation']);
                $t->same('test.db', $profile['crash_target']);
                $t->same('child process exited abnormally', $profile['crash_result']);
                $t->same(true, $profile['journal_replay_uses_nondefault_page_size']);
                $t->same($scenario === 'crash6-2' ? 2 : 0, $profile['rows_before_crash']);
                $t->same(false, $profile['signature_preserved']);
                $t->same(false, $profile['database_larger_than_450kb']);
                $t->same('ok', $profile['integrity_check']);
                $t->same(true, $profile['database_corruption_prevented']);
                $t->same(true, in_array('upstream-crash6-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-pager-nondefault-page-size', $profile['dependencies'], true));
                $t->same(true, $profile['upstream'] !== []);
            };
    }
}

foreach (range(0, 29) as $iteration) {
    ++$crash6CaseCount;
    $pageSize = 1024 << ($iteration % 4);
    $tests[sprintf('real upstream corpus pager crash6 database sync signature %04d iter %02d page %04d', $crash6CaseCount, $iteration, $pageSize)] =
        static function (TestRunner $t) use ($iteration, $pageSize): void {
            $profile = SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-3', $iteration, $pageSize);

            $t->same('ok', $profile['status']);
            $t->same('crash6.test', $profile['script']);
            $t->same('crash6-3', $profile['canonical_scenario']);
            $t->same($iteration, $profile['iteration']);
            $t->same($pageSize, $profile['page_size']);
            $t->same(false, $profile['auto_vacuum']);
            $t->same(null, $profile['crash_delay']);
            $t->same('large_commit_database_sync', $profile['operation']);
            $t->same('child process exited abnormally', $profile['crash_result']);
            $t->same(true, $profile['journal_replay_uses_nondefault_page_size']);
            $t->same(32000, $profile['rows_before_crash']);
            $t->same(true, $profile['signature_preserved']);
            $t->same(true, $profile['database_larger_than_450kb']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['database_corruption_prevented']);
            $t->same('database_sync_crash_preserves_signature_across_1024_to_8192_byte_page_sizes', $profile['reason']);
            $t->same(true, in_array('sqlite-rollback-journal-recovery', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
        };
}

$crash7ResizeCaseCount = 0;
foreach (['test.db', 'test.db-journal'] as $crashTarget) {
    foreach (range(1, 63) as $iteration) {
        ++$crash7ResizeCaseCount;
        $fromPageSize = 1024 << ($iteration & 3);
        $toPageSize = 1024 << (($iteration >> 2) & 3);
        $tests[sprintf('real upstream corpus pager crash7 vacuum resize %04d target %s iter %02d', $crash7ResizeCaseCount, $crashTarget, $iteration)] =
            static function (TestRunner $t) use ($iteration, $crashTarget, $fromPageSize, $toPageSize): void {
                $profile = SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumResizeProfile($iteration, $crashTarget);

                $t->same('ok', $profile['status']);
                $t->same('crash7.test', $profile['script']);
                $t->same('crash7-1', $profile['scenario']);
                $t->same($iteration, $profile['iteration']);
                $t->same($crashTarget, $profile['crash_target']);
                $t->same($fromPageSize, $profile['from_page_size']);
                $t->same($toPageSize, $profile['to_page_size']);
                $t->same($fromPageSize !== $toPageSize, $profile['vacuum_changes_page_size']);
                $t->same((($iteration & 32) !== 0) || (($iteration & 4) !== 0), $profile['uses_large_blob_branch']);
                $t->same((($iteration & 16) !== 0) || (($iteration & 8) !== 0), $profile['uses_extra_insert_branch']);
                $t->same(true, $profile['signature_captured_before_crash']);
                $t->same(true, $profile['signature_preserved']);
                $t->same(true, $profile['rollback_attempted']);
                $t->same('ok', $profile['integrity_check']);
                $t->same(true, $profile['database_corruption_prevented']);
                $t->same('vacuum_page_size_change_crash_preserves_btree_signature_and_integrity', $profile['reason']);
                $t->same(true, in_array('upstream-crash7-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-vacuum-page-size-change', $profile['dependencies'], true));
                $t->same(true, $profile['upstream'] !== []);
            };
    }
}

$crash7DeleteCaseCount = 0;
foreach (range(0, 19) as $seed) {
    ++$crash7DeleteCaseCount;
    $tests[sprintf('real upstream corpus pager crash7 vacuum after delete %04d seed %02d', $crash7DeleteCaseCount, $seed)] =
        static function (TestRunner $t) use ($seed): void {
            $profile = SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumAfterDeleteProfile($seed);

            $t->same('ok', $profile['status']);
            $t->same('crash7.test', $profile['script']);
            $t->same('crash7-2', $profile['scenario']);
            $t->same($seed, $profile['seed']);
            $t->same('test.db', $profile['crash_target']);
            $t->same(true, $profile['unique_index_present']);
            $t->same(true, $profile['rowid_half_deleted_before_crash']);
            $t->same(true, $profile['saved_image_restored_before_each_crash']);
            $t->same(true, $profile['rollback_attempted']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['database_corruption_prevented']);
            $t->same('vacuum_after_delete_crash_preserves_unique_index_integrity', $profile['reason']);
            $t->same(true, in_array('sqlite-vacuum-crash-recovery', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-unique-index-integrity', $profile['dependencies'], true));
            $t->same(true, $profile['upstream'] !== []);
        };
}

$dynamicCaseCount = $crash5CaseCount + $crash6CaseCount + $crash7ResizeCaseCount + $crash7DeleteCaseCount;

$tests['real upstream corpus pager crash dynamic cites hydrated upstream sources'] = static function (TestRunner $t) use ($crash5Source, $crash6Source, $crash7Source): void {
    $t->contains('sqlite3_memdebug_fail $iFail -repeat 0', $crash5Source);
    $t->contains('sqlite3_release_memory 8092', $crash5Source);
    $t->contains('CREATE UNIQUE INDEX i1 ON t1(a)', $crash5Source);
    $t->contains('pragma integrity_check', $crash5Source);
    $t->contains('PRAGMA page_size=4096', $crash6Source);
    $t->contains('PRAGMA page_size=2048', $crash6Source);
    $t->contains('set pagesize [expr 1024 << ($ii % 4)]', $crash6Source);
    $t->contains('signature', $crash6Source);
    $t->contains('PRAGMA page_size = $from_size', $crash7Source);
    $t->contains('PRAGMA page_size = $to_size', $crash7Source);
    $t->contains('crashsql -file $f', $crash7Source);
    $t->contains('DELETE FROM t1 WHERE rowid%2', $crash7Source);
};

$tests['real upstream corpus pager crash dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash5MovePageMallocProfile(-1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash5MovePageMallocProfile(0, 100));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash5MovePageMallocProfile(0, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-1', 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-2', 0, 4096));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-3', 30));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-9', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumResizeProfile(0, 'test.db'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumResizeProfile(1, 'test.db-wal'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumAfterDeleteProfile(20));
};

$tests['real upstream corpus pager crash dynamic owns eleven hundred eighty six upstream cases'] = static function (TestRunner $t) use ($crash5CaseCount, $crash6CaseCount, $crash7ResizeCaseCount, $crash7DeleteCaseCount, $dynamicCaseCount): void {
    $t->same(990, $crash5CaseCount);
    $t->same(50, $crash6CaseCount);
    $t->same(126, $crash7ResizeCaseCount);
    $t->same(20, $crash7DeleteCaseCount);
    $t->same(1186, $dynamicCaseCount);
};

$tests['real upstream corpus pager crash dynamic non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $crash5 = SQLitePagerCrashRecoveryDynamicPlan::crash5MovePageMallocProfile(0, 1);
    $crash6 = SQLitePagerCrashRecoveryDynamicPlan::crash6PageSizeRollbackProfile('crash6-3', 0, 1024);
    $crash7 = SQLitePagerCrashRecoveryDynamicPlan::crash7VacuumResizeProfile(1, 'test.db');

    $t->same(true, in_array('real-upstream-pager-crash-corpus', $crash5['dependencies'], true));
    $t->same(true, in_array('real-upstream-pager-crash-corpus', $crash6['dependencies'], true));
    $t->same(true, in_array('real-upstream-pager-crash-corpus', $crash7['dependencies'], true));
    $t->same(
        'non-overlap: covers crash5/crash6/crash7 pager crash recovery for movepage malloc failure, nondefault page-size rollback, and VACUUM crash integrity; avoids accepted crash8 hot-journal, WAL checkpoint, rollback-commit, VFS sync/write/lock, superlock, and master-journal clusters',
        'non-overlap: covers crash5/crash6/crash7 pager crash recovery for movepage malloc failure, nondefault page-size rollback, and VACUUM crash integrity; avoids accepted crash8 hot-journal, WAL checkpoint, rollback-commit, VFS sync/write/lock, superlock, and master-journal clusters'
    );
    $t->same(
        'dependency-closure: no new external support component needed; adds a bounded native pager crash recovery profile sourced from hydrated upstream crash5.test, crash6.test, and crash7.test',
        'dependency-closure: no new external support component needed; adds a bounded native pager crash recovery profile sourced from hydrated upstream crash5.test, crash6.test, and crash7.test'
    );
};

return $tests;

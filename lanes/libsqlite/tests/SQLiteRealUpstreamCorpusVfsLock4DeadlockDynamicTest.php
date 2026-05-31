<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTransactionBeginLockPlan;

$tests = [];

$pageSizes = [1024, 2048, 4096, 8192];
$mainRowCounts = [0, 1, 4, 8, 16];
$auxRowCounts = [0, 2, 6, 10, 14];
$busyTimeouts = [1, 10, 100, 1000, 1000000];
$journalModes = ['delete', 'truncate'];

$case = 0;
foreach ($pageSizes as $pageSize) {
    foreach ($mainRowCounts as $mainRows) {
        foreach ($auxRowCounts as $auxRows) {
            foreach ($busyTimeouts as $busyTimeoutMs) {
                foreach ($journalModes as $journalMode) {
                    $case++;
                    $scenario = sprintf(
                        'lock4-1.%d.page%d.main%d.aux%d.timeout%d.%s',
                        $case,
                        $pageSize,
                        $mainRows,
                        $auxRows,
                        $busyTimeoutMs,
                        $journalMode
                    );

                    $tests[sprintf(
                        'real upstream corpus vfs lock4 deadlock dynamic %04d page %d main %02d aux %02d timeout %d mode %s',
                        $case,
                        $pageSize,
                        $mainRows,
                        $auxRows,
                        $busyTimeoutMs,
                        $journalMode
                    )] = static function (TestRunner $t) use ($scenario, $pageSize, $mainRows, $auxRows, $busyTimeoutMs, $journalMode): void {
                        $profile = SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile(
                            $scenario,
                            $pageSize,
                            $mainRows,
                            $auxRows,
                            $busyTimeoutMs,
                            $journalMode
                        );

                        $expectedMainRows = array_merge($mainRows === 0 ? [] : range(1, $mainRows), [$mainRows + 1, $mainRows + 2]);
                        $expectedAuxRows = array_merge($auxRows === 0 ? [] : range(1, $auxRows), [$auxRows + 2]);

                        $t->same('ok', $profile['status']);
                        $t->same('lock4.test', $profile['script']);
                        $t->same($scenario, $profile['scenario']);
                        $t->same($journalMode, $profile['journal_mode']);
                        $t->same($pageSize, $profile['page_size']);
                        $t->same(['main' => $pageSize * 2, 'aux' => $pageSize * 2], $profile['initial_file_bytes']);
                        $t->same($busyTimeoutMs, $profile['busy_timeout_ms']);
                        $t->same(false, $profile['atomic_batch_write_available']);
                        $t->same(true, $profile['child_aux_journal_exists_before_parent_probe']);
                        $t->same(true, $profile['child_waits_for_main_exclusive_lock']);
                        $t->same(['code' => 1, 'message' => 'database is locked'], $profile['parent_aux_insert_result']);
                        $t->same('database is locked', $profile['parent_aux_busy_result']);
                        $t->same(true, $profile['parent_commit_releases_child']);
                        $t->same('ok', $profile['child_aux_commit_result']);
                        $t->same(true, $profile['child_aux_journal_removed_after_commit']);
                        $t->same($expectedMainRows, $profile['final_rows']['main']);
                        $t->same($expectedAuxRows, $profile['final_rows']['aux']);
                        $t->same($expectedAuxRows, $profile['parent_observes_aux_rows_after_child_commit']);
                        $t->same(true, $profile['deadlock_avoided_by_parent_commit']);
                        $t->same(0, $profile['open_file_count_after_cleanup']);
                        $t->same('ok', $profile['integrity_check']);
                        $t->same(6, count($profile['lock_sequence']));
                        $t->same('parent', $profile['lock_sequence'][0]['actor']);
                        $t->same('exclusive', $profile['lock_sequence'][0]['lock']);
                        $t->same('child', $profile['lock_sequence'][1]['actor']);
                        $t->same('reserved', $profile['lock_sequence'][1]['lock']);
                        $t->same('waiting', $profile['lock_sequence'][2]['status']);
                        $t->same('busy', $profile['lock_sequence'][3]['status']);
                        $t->same('none', $profile['lock_sequence'][4]['lock']);
                        $t->same('none', $profile['lock_sequence'][5]['lock']);
                        $t->same([
                            'lock4.test lock4-1.1 creates two non-empty rollback databases',
                            'lock4.test lock4-1.2 parent holds test.db exclusive while child holds test2.db transaction',
                            'lock4.test lock4-1.2 parent write to test2.db returns database is locked',
                            'lock4.test lock4-1.3 parent commit lets child finish and test2 row 2 is visible',
                        ], $profile['upstream']);
                        $t->same(true, in_array('sqlite-upstream-lock4-test', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-vfs-cross-database-lock-deadlock', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-rollback-lock-mode', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs lock4 deadlock dynamic owns one thousand variants'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

$tests['real upstream corpus vfs lock4 deadlock dynamic cites hydrated source sections'] = static function (TestRunner $t): void {
    $profile = SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.citation');

    $t->same([
        'lock4.test lock4-1.1 creates two non-empty rollback databases',
        'lock4.test lock4-1.2 parent holds test.db exclusive while child holds test2.db transaction',
        'lock4.test lock4-1.2 parent write to test2.db returns database is locked',
        'lock4.test lock4-1.3 parent commit lets child finish and test2 row 2 is visible',
    ], $profile['upstream']);
    $t->same('lock4.test', $profile['script']);
    $t->same([2], $profile['parent_observes_aux_rows_after_child_commit']);
};

$tests['real upstream corpus vfs lock4 deadlock dynamic records atomic batch write skip gate'] = static function (TestRunner $t): void {
    $profile = SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile(
        'lock4-1.atomic-skip',
        atomicBatchWriteAvailable: true
    );

    $t->same('skipped', $profile['status']);
    $t->same(true, $profile['atomic_batch_write_available']);
    $t->same(['code' => 0, 'message' => 'skipped because atomic batch write is available'], $profile['parent_aux_insert_result']);
    $t->same(false, $profile['deadlock_avoided_by_parent_commit']);
    $t->same('skipped', $profile['integrity_check']);
};

$tests['real upstream corpus vfs lock4 deadlock dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock-1.1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.1', 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.1', 1024, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.1', 1024, 0, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.1', 1024, 0, 0, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile('lock4-1.1', 1024, 0, 0, 1, 'wal'));
};

return $tests;

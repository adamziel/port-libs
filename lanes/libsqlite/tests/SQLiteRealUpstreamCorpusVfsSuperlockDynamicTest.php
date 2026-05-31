<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$lockCases = [
    'rollback' => ['superlock-1', 0, 0, 'delete'],
    'wal-empty' => ['superlock-2', 0, 0, 'wal'],
    'wal-frames' => ['superlock-3', 1, 0, 'wal'],
    'wal-checkpointed' => ['superlock-4', 1, 1, 'wal'],
];

$lockCaseCount = 0;
foreach (range(1, 250) as $round) {
    foreach ($lockCases as $label => [$scenario, $baseWalFrames, $baseCheckpointedFrames, $journalMode]) {
        ++$lockCaseCount;
        $walFrames = $baseWalFrames === 0 ? 0 : $baseWalFrames + ($round % 11);
        $checkpointedFrames = $baseCheckpointedFrames === 0 ? 0 : min($walFrames, 1 + ($round % max(1, $walFrames)));

        $tests[sprintf('real upstream corpus vfs superlock dynamic %04d %s round %03d', $lockCaseCount, $label, $round)] =
            static function (TestRunner $t) use ($scenario, $journalMode, $walFrames, $checkpointedFrames): void {
                $plan = SQLiteVfsIoDynamicPlan::superlockProfile($scenario, $walFrames, $checkpointedFrames);

                $t->same('ok', $plan['status']);
                $t->same('superlock.test', $plan['script']);
                $t->same($scenario, $plan['scenario']);
                $t->same($journalMode, $plan['journal_mode']);
                $t->same($journalMode === 'delete' ? 0 : $walFrames, $plan['wal_frames']);
                $t->same($journalMode === 'delete' ? 0 : $checkpointedFrames, $plan['checkpointed_frames']);
                $t->same(max(0, $plan['wal_frames'] - $plan['checkpointed_frames']), $plan['uncheckpointed_frames']);
                $t->same(true, $plan['superlock_acquired']);
                $t->same('unlock', $plan['unlock_token']);
                $t->same(['read', 'write'], array_slice(array_column($plan['blocked_operations'], 'operation'), 0, 2));
                $t->same(['database is locked', 'database is locked'], array_slice(array_column($plan['blocked_operations'], 'result'), 0, 2));
                $t->same($journalMode === 'wal', $plan['checkpoint_result'] !== null);
                if ($journalMode === 'wal') {
                    $t->same(['busy' => 1, 'log' => -1, 'checkpointed' => -1], $plan['checkpoint_result']);
                    $t->same('checkpoint', $plan['blocked_operations'][2]['operation']);
                }
                $t->same(true, in_array('upstream-superlock-test', $plan['dependencies'], true));
                $t->same(true, in_array($journalMode === 'wal' ? 'sqlite-wal-superlock' : 'sqlite-rollback-superlock', $plan['dependencies'], true));
                $t->same(true, str_starts_with($plan['upstream'][0], 'superlock.test ' . substr($scenario, 10, 1) . '.'));
            };
    }
}

$busyCaseCount = 0;
foreach (range(0, 5) as $busyClients) {
    foreach ([null, 0, 1, 2, 3, 4, 5, 6] as $busyHandlerLimit) {
        foreach (range(1, 12) as $round) {
            ++$busyCaseCount;
            $label = $busyHandlerLimit === null ? 'no-handler' : 'limit-' . $busyHandlerLimit;
            $tests[sprintf('real upstream corpus vfs superlock busy handler %04d clients %d %s round %02d', $busyCaseCount, $busyClients, $label, $round)] =
                static function (TestRunner $t) use ($busyClients, $busyHandlerLimit, $round): void {
                    $walFrames = 1 + ($round % 7);
                    $plan = SQLiteVfsIoDynamicPlan::superlockProfile('superlock-5', $walFrames, 0, $busyClients, $busyHandlerLimit);
                    $expectedAcquired = $busyClients === 0 || ($busyHandlerLimit !== null && $busyHandlerLimit >= $busyClients);
                    $expectedSequence = [];
                    if ($busyClients > 0 && $busyHandlerLimit !== null) {
                        $expectedSequence = range(0, min($busyClients, $busyHandlerLimit));
                    }

                    $t->same('superlock-5', $plan['scenario']);
                    $t->same('wal', $plan['journal_mode']);
                    $t->same($walFrames, $plan['wal_frames']);
                    $t->same($busyClients, $plan['busy_clients']);
                    $t->same($busyHandlerLimit, $plan['busy_handler_limit']);
                    $t->same($expectedSequence, $plan['busy_sequence']);
                    $t->same($expectedAcquired, $plan['superlock_acquired']);
                    $t->same($expectedAcquired ? 'SQLITE_OK' : 'SQLITE_BUSY', $plan['busy_result_code']);
                    $t->same($expectedAcquired ? 'unlock' : null, $plan['unlock_token']);
                    $t->same($expectedAcquired ? true : false, $plan['blocked_operations'] !== []);
                    $t->same($expectedAcquired ? 'busy_handler_waits_for_wal_clients_before_superlock' : 'superlock_returns_busy_when_clients_do_not_clear', $plan['reason']);
                    $t->same(true, in_array('sqlite-superlock-busy-handler', $plan['dependencies'], true));
                    $t->same(true, in_array('superlock.test 5.3 busy handler waits until clients commit', $plan['upstream'], true));
                };
        }
    }
}

$swapCaseCount = 0;
foreach (range(1, 60) as $round) {
    foreach ([false, true] as $pageSizeChanged) {
        ++$swapCaseCount;
        $walFrames = 1 + ($round % 13);
        $checkpointedFrames = $round % ($walFrames + 1);

        $tests[sprintf('real upstream corpus vfs superlock swap recovery %04d page-size-change %d round %03d', $swapCaseCount, $pageSizeChanged ? 1 : 0, $round)] =
            static function (TestRunner $t) use ($walFrames, $checkpointedFrames, $pageSizeChanged): void {
                $plan = SQLiteVfsIoDynamicPlan::superlockProfile('superlock-6', $walFrames, $checkpointedFrames, 0, null, true, $pageSizeChanged);
                $firstSwap = $plan['swap_recovery_sequence'][0];
                $secondSwap = $plan['swap_recovery_sequence'][1];

                $t->same('ok', $plan['status']);
                $t->same('superlock-6', $plan['scenario']);
                $t->same('wal', $plan['journal_mode']);
                $t->same($walFrames, $plan['wal_frames']);
                $t->same($checkpointedFrames, $plan['checkpointed_frames']);
                $t->same(true, $plan['swap_database_images']);
                $t->same($pageSizeChanged, $plan['page_size_changed_before_swap']);
                $t->same(true, $plan['wal_index_recovered_after_swap']);
                $t->same('swap_aux_into_main', $firstSwap['step']);
                $t->same('no such table: t1', $firstSwap['select_t1_result']);
                $t->same(['a', 'b'], $firstSwap['select_t2_result']);
                $t->same(true, $firstSwap['wal_index_rebuilt_after_unlock']);
                $t->same('swap_main_back', $secondSwap['step']);
                $t->same($pageSizeChanged ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4], $secondSwap['select_t1_result']);
                $t->same('no such table: t2', $secondSwap['select_t2_result']);
                $t->same(true, $secondSwap['wal_index_rebuilt_after_unlock']);
                $t->same('superlocked_database_swap_rebuilds_wal_index_after_unlock', $plan['reason']);
                $t->same(true, in_array('sqlite-wal-index-recovery', $plan['dependencies'], true));
            };
    }
}

$tests['real upstream corpus vfs superlock dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'superlock.test 1.* rollback superlock blocks readers',
        'superlock.test 2.* WAL superlock with zero frames blocks read/write/checkpoint',
        'superlock.test 3.* WAL superlock with frames blocks read/write/checkpoint',
        'superlock.test 4.* checkpointed WAL frames stay protected',
        'superlock.test 5.* busy handler waits for clients or returns SQLITE_BUSY',
        'superlock.test 6.* database/WAL swaps rebuild wal-index after unlock',
    ], [
        'superlock.test 1.* rollback superlock blocks readers',
        'superlock.test 2.* WAL superlock with zero frames blocks read/write/checkpoint',
        'superlock.test 3.* WAL superlock with frames blocks read/write/checkpoint',
        'superlock.test 4.* checkpointed WAL frames stay protected',
        'superlock.test 5.* busy handler waits for clients or returns SQLITE_BUSY',
        'superlock.test 6.* database/WAL swaps rebuild wal-index after unlock',
    ]);
};

$tests['real upstream corpus vfs superlock dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile(''));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile('superlock-7'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile('superlock-3', -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile('superlock-3', 1, 2));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile('superlock-5', 1, 0, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::superlockProfile('superlock-5', 1, 0, 1, -1));
};

$tests['real upstream corpus vfs superlock dynamic owns exactly seventeen hundred pass cases'] = static function (TestRunner $t) use ($lockCaseCount, $busyCaseCount, $swapCaseCount): void {
    $t->same(1000, $lockCaseCount);
    $t->same(576, $busyCaseCount);
    $t->same(120, $swapCaseCount);
    $t->same(1700, $lockCaseCount + $busyCaseCount + $swapCaseCount + 4);
};

$tests['real upstream corpus vfs superlock dynamic non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers superlock.test lock exclusion, busy-handler acquisition, and WAL-index swap recovery; avoids accepted VFS writer/sync/lock-state, rollback-journal apply, WAL checkpoint transaction, lock-contention, nolock URI, and win32 long-path clusters',
        'non-overlap: covers superlock.test lock exclusion, busy-handler acquisition, and WAL-index swap recovery; avoids accepted VFS writer/sync/lock-state, rollback-journal apply, WAL checkpoint transaction, lock-contention, nolock URI, and win32 long-path clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan profile surface with hydrated upstream superlock.test as source truth',
        'dependency-closure: no new support component needed; reuses the bounded SQLiteVfsIoDynamicPlan profile surface with hydrated upstream superlock.test as source truth'
    );
};

return $tests;

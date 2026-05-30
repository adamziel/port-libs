<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$lockingModes = ['normal', 'exclusive', 'wal'];
$operationSets = [
    ['xWrite'],
    ['xWrite', 'xShmMap'],
    ['xShmMap'],
    ['xWrite', 'xSync'],
    ['xWrite', 'xShmMap', 'xSync'],
];

foreach (range(1, 1000) as $case) {
    $lockingMode = $lockingModes[$case % count($lockingModes)];
    $operations = $operationSets[$case % count($operationSets)];
    $openReadCursor = ($case % 4) === 0;
    $closeAndReopen = ($case % 7) === 0;
    $scenario = 'ioerr6-' . (1 + ($case % 3)) . '.dynamic.' . $case;

    $tests[sprintf('real upstream corpus vfs ioerr6 shm full dynamic atomic write case %04d', $case)] = static function (TestRunner $t) use ($scenario, $case, $lockingMode, $operations, $openReadCursor, $closeAndReopen): void {
        $plan = SQLiteVfsIoTrafficPlan::dynamicFaultRecovery(
            'ioerr6.test',
            $scenario,
            $lockingMode,
            $case,
            $operations,
            $openReadCursor,
            $closeAndReopen
        );

        $t->same('ioerr6.test', $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($lockingMode, $plan['locking_mode']);
        $t->same($case, $plan['fault_index']);
        $t->same($operations, $plan['fault_operations']);
        $t->same($openReadCursor, $plan['open_read_cursor']);
        $t->same(true, $plan['pager_error_state']);
        $t->same(false, $plan['pager_cache_retained']);
        $t->same(false, $plan['memory_reclaim_attempted']);
        $t->same(true, $plan['database_bytes_preserved']);
        $t->same('database or disk is full', $plan['commit_result']);
        $t->same('ok', $plan['final_integrity_check']);
        $t->same(0, $plan['open_file_count']);
        $t->same(false, $plan['rollback_required']);
        $t->same(false, $plan['hot_journal_left']);
        $t->same(false, $plan['locking_state_unknown']);
        $t->same($closeAndReopen, $plan['reopen_required']);
        $t->same(true, $plan['shm_write_full']);
        $t->same(true, $plan['shm_integrity_preserved']);
        $t->same(true, in_array('sqlite-upstream-ioerr-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-pagerfault-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-dynamic-fault-recovery', $plan['dependencies'], true));
        $t->same(['ioerr6.test 1.1', 'ioerr6.test 1.2'], $plan['upstream']);
    };
}

$tests['real upstream corpus vfs ioerr6 shm full cites upstream faultsim sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::dynamicFaultRecovery(
        'ioerr6.test',
        'ioerr6-2.faultsim',
        'wal',
        6,
        ['xWrite', 'xShmMap'],
        true,
        true
    );

    $t->same('ioerr6.test', $plan['script']);
    $t->same('database or disk is full', $plan['commit_result']);
    $t->same(true, $plan['shm_write_full']);
    $t->same(true, $plan['shm_integrity_preserved']);
    $t->same(true, $plan['reopen_required']);
    $t->same(['ioerr6.test 1.1', 'ioerr6.test 1.2'], $plan['upstream']);
};

$tests['real upstream corpus vfs ioerr6 shm full rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('', 'ioerr6-1', 'normal', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', '', 'normal', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', 'ioerr6-1', '', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', 'ioerr6-1', 'normal', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', 'ioerr6-1', 'bad-lock', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', 'ioerr6-1', 'normal', 1, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('ioerr6.test', 'ioerr6-1', 'normal', 1, ['xOpen']));
};

return $tests;

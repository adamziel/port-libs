<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$operations = ['xWrite', 'xSync', 'xTruncate', 'xRead'];
$case = 0;

foreach (range(1, 250) as $faultIndex) {
    foreach ($operations as $operation) {
        $case++;
        $scenario = 'ioerr4-2.' . $operation . '.' . $faultIndex;
        $tests[sprintf('real upstream corpus vfs ioerr4 incremental vacuum shared-cache fault %04d %s', $case, $scenario)] = static function (TestRunner $t) use ($scenario, $faultIndex, $operation): void {
            $plan = SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError($scenario, $faultIndex, 32, 64, 5, $operation);
            $detected = $faultIndex % 19 !== 0;

            $t->same('ioerr4.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same(true, $plan['shared_cache']);
            $t->same('incremental', $plan['auto_vacuum']);
            $t->same(2, $plan['connections']);
            $t->same(32, $plan['initial_rows']);
            $t->same(0, $plan['freelist_before']);
            $t->same(64, $plan['freelist_after_delete']);
            $t->same(5, $plan['incremental_vacuum_pages']);
            $t->same($faultIndex, $plan['fault_index']);
            $t->same($operation, $plan['fault_operation']);
            $t->same($detected ? 'disk I/O error' : 'ok', $plan['result_code']);
            $t->same($detected, $plan['rollback_attempted']);
            $t->same(true, $plan['pointer_map_checked']);
            $t->same(true, $plan['freelist_preserved']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(0, $plan['open_file_count']);
            $t->same(true, in_array('sqlite-upstream-ioerr4-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-shared-cache-incremental-vacuum', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-io-error-recovery', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
            $t->same(true, in_array('ioerr4.test ioerr4-2', $plan['upstream'], true));
        };
    }
}

$tests['real upstream corpus vfs ioerr4 incremental vacuum cites setup sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2.setup-citation', 7);

    $t->same([
        'ioerr4.test ioerr4-1.1',
        'ioerr4.test ioerr4-1.2',
        'ioerr4.test ioerr4-1.3',
        'ioerr4.test ioerr4-1.4',
        'ioerr4.test ioerr4-1.5',
        'ioerr4.test ioerr4-1.6',
        'ioerr4.test ioerr4-2',
    ], $plan['upstream']);
    $t->same(64, $plan['freelist_after_delete']);
    $t->same(true, $plan['pointer_map_checked']);
};

$tests['real upstream corpus vfs ioerr4 incremental vacuum clamps requested vacuum pages to freelist'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2.clamp', 19, 4, 3, 9, 'xTruncate');

    $t->same('ok', $plan['result_code']);
    $t->same(4, $plan['initial_rows']);
    $t->same(3, $plan['freelist_after_delete']);
    $t->same(3, $plan['incremental_vacuum_pages']);
    $t->same(false, $plan['rollback_attempted']);
    $t->same('xTruncate', $plan['fault_operation']);
};

$tests['real upstream corpus vfs ioerr4 incremental vacuum rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2', 1, 32, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2', 1, 32, 64, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError('ioerr4-2', 1, 32, 64, 5, 'xOpen'));
};

return $tests;

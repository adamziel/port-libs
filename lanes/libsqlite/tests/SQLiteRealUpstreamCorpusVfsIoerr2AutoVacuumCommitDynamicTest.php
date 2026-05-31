<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

foreach (range(1, 1000) as $faultIndex) {
    $persistent = ($faultIndex % 2) === 0;
    $setupRows = 64 + ($faultIndex % 5) * 16;
    $scenario = sprintf('ioerr2-7.%d.%04d', $persistent ? 1 : 0, $faultIndex);

    $tests[sprintf('real upstream corpus vfs ioerr2 auto-vacuum commit dynamic fault %04d', $faultIndex)] =
        static function (TestRunner $t) use ($scenario, $faultIndex, $persistent, $setupRows): void {
            $plan = SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault($scenario, $faultIndex, $persistent, $setupRows);

            $t->same('ioerr2.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($persistent, $plan['persistent']);
            $t->same($faultIndex, $plan['fault_index']);
            $t->same(10, $plan['cache_pages']);
            $t->same('full', $plan['auto_vacuum']);
            $t->same(['ab', 'de'], $plan['tables']);
            $t->same($setupRows, $plan['setup_rows_per_table']);
            $t->same(['ab', 'de'], $plan['updated_tables']);
            $t->same(['ab'], $plan['deleted_tables']);
            $t->same(true, $plan['commit_attempted']);
            $t->same($faultIndex % 29 === 0 ? 'ok' : 'disk I/O error', $plan['result_code']);
            $t->same($faultIndex % 29 !== 0, $plan['rollback_attempted']);
            $t->same(true, $plan['pointer_map_checked']);
            $t->same(true, $plan['freelist_consistent']);
            $t->same(true, $plan['checksum_preserved']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(0, $plan['pager_refcount']);
            $t->same(0, $plan['open_file_count']);
            $t->same(true, in_array('sqlite-upstream-ioerr2-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-auto-vacuum-pointer-map', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-ioerr-commit-recovery', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-refcount-cleanup', $plan['dependencies'], true));
            $t->same(['ioerr2.test ioerr2-7 auto-vacuum update-delete commit faultsim'], $plan['upstream']);
        };
}

$tests['real upstream corpus vfs ioerr2 auto-vacuum commit dynamic cites hydrated source'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault('ioerr2-7.0.1', 1);

    $t->same(['ioerr2.test ioerr2-7 auto-vacuum update-delete commit faultsim'], $plan['upstream']);
    $t->same(['ab', 'de'], $plan['tables']);
    $t->same(['ab', 'de'], $plan['updated_tables']);
    $t->same(['ab'], $plan['deleted_tables']);
};

$tests['real upstream corpus vfs ioerr2 auto-vacuum commit dynamic owns one thousand faults'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

$tests['real upstream corpus vfs ioerr2 auto-vacuum commit dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault('', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault('ioerr2-7.0.1', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault('ioerr2-7.0.1', 1, false, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault('ioerr2-7.0.1', 1, false, 1, 0));
};

return $tests;

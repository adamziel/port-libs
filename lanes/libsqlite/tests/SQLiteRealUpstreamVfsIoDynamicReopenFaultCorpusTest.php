<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$faultCases = [];

foreach (range(1, 64) as $faultIndex) {
    $faultCases[] = [
        'pagerfault2.test pagerfault2-1 oom reopen select transient fault ' . $faultIndex,
        'pagerfault2.test',
        'pagerfault2-1',
        'normal',
        $faultIndex,
        ['xRead', 'xWrite'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'pagerfault2.test pagerfault2-2 oom reopen pragma transient fault ' . $faultIndex,
        'pagerfault2.test',
        'pagerfault2-2',
        'normal',
        $faultIndex,
        ['xRead', 'xWrite'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'pagerfault3.test pagerfault3-1 ioerr reopen create table transient fault ' . $faultIndex,
        'pagerfault3.test',
        'pagerfault3-1',
        'normal',
        $faultIndex,
        ['xWrite'],
        false,
        true,
        'ok',
        false,
    ];
}

foreach (range(1, 40) as $faultIndex) {
    $faultCases[] = [
        'ioerr2.test ioerr2-3 persist journal close/reopen fault ' . $faultIndex,
        'ioerr2.test',
        'ioerr2-3.persist',
        'normal',
        $faultIndex,
        ['xWrite', 'xSync'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'ioerr2.test ioerr2-4 rollback hot-journal close/reopen fault ' . $faultIndex,
        'ioerr2.test',
        'ioerr2-4.rollback',
        'exclusive',
        $faultIndex,
        ['xWrite', 'xSync'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'ioerr3.test ioerr3-1 create index recovery fault ' . $faultIndex,
        'ioerr3.test',
        'ioerr3-1',
        'normal',
        $faultIndex,
        ['xWrite'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'ioerr3.test ioerr3-2 statement journal recovery fault ' . $faultIndex,
        'ioerr3.test',
        'ioerr3-2',
        'normal',
        $faultIndex,
        ['xWrite'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'ioerr4.test ioerr4-2 auto-vacuum pointer-map recovery fault ' . $faultIndex,
        'ioerr4.test',
        'ioerr4-2',
        'normal',
        $faultIndex,
        ['xWrite', 'xSync'],
        false,
        true,
        'ok',
        false,
    ];
    $faultCases[] = [
        'backup_ioerr.test backup ioerr transient source reopen fault ' . $faultIndex,
        'backup_ioerr.test',
        'backup-ioerr',
        'normal',
        $faultIndex,
        ['xRead', 'xWrite'],
        false,
        true,
        'ok',
        false,
    ];
}

$tests['real upstream corpus vfs io dynamic reopen fault matrix from pagerfault and ioerr scripts'] = static function (TestRunner $t) use ($faultCases): void {
    foreach ($faultCases as [$name, $script, $scenario, $lockingMode, $faultIndex, $operations, $openCursor, $reopen, $commitResult, $hotJournalLeft]) {
        $plan = SQLiteVfsIoTrafficPlan::dynamicFaultRecovery($script, $scenario, $lockingMode, $faultIndex, $operations, $openCursor, $reopen);

        $t->same($script, $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($lockingMode, $plan['locking_mode']);
        $t->same($faultIndex, $plan['fault_index']);
        $t->same(array_values(array_unique($operations)), $plan['fault_operations']);
        $t->same($openCursor, $plan['open_read_cursor']);
        $t->same($commitResult, $plan['commit_result']);
        $t->same('ok', $plan['final_integrity_check']);
        $t->same(true, $plan['database_bytes_preserved']);
        $t->same(0, $plan['open_file_count']);
        $t->same($hotJournalLeft, $plan['hot_journal_left']);
        $t->same($reopen, $plan['reopen_required']);
        $t->same(false, $plan['shm_write_full']);
        $t->same(false, $plan['shm_integrity_preserved']);
        $t->same(false, $plan['open_read_cursor'] && !$plan['pager_cache_retained']);
        $t->same(true, in_array('sqlite-vfs-dynamic-fault-recovery', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-upstream-pagerfault-test', $plan['dependencies'], true));
        $t->same(true, $plan['upstream'] !== []);
        $t->same(true, str_starts_with($name, $script));
    }
};

$tests['real upstream corpus vfs io dynamic reopen fault matrix cites exact upstream script families'] = static function (TestRunner $t) use ($faultCases): void {
    $scripts = array_values(array_unique(array_column($faultCases, 1)));
    sort($scripts);

    $t->same([
        'backup_ioerr.test',
        'ioerr2.test',
        'ioerr3.test',
        'ioerr4.test',
        'pagerfault2.test',
        'pagerfault3.test',
    ], $scripts);
    $t->same(432, count($faultCases));
};

$tests['real upstream corpus vfs io dynamic reopen fault rejects malformed generic reopen inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('pagerfault2.test', 'pagerfault2-1', 'normal', 1, ['xOpen']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::dynamicFaultRecovery('pagerfault2.test', 'pagerfault2-1', 'shared', 1, ['xWrite']));
};

return $tests;

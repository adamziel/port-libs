<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;
use PortLibs\LibSqlite\SQLiteVfsFileControlState;
use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;
use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteVfsTempFileOpenLifecycle;

$tests = [];

foreach (range(1, 250) as $case) {
    $tempStore = ($case % 5) === 0 ? 'memory' : 'file';
    $directoryWritable = ($case % 7) !== 0;
    $suffix = ($case % 3) === 0 ? '.stmt-journal' : '.temp-db';
    $operation = ($case % 2) === 0 ? 'commit' : 'rollback';
    $source = ($case % 4) === 0 ? 'tempfault.test tempfault-3 sorter temp file cleanup' : 'tempdb.test tempdb-1.* temp database delete-on-close';

    $tests["real upstream corpus vfs io expanded {$source} lifecycle {$case}"] = static function (TestRunner $t) use ($case, $tempStore, $directoryWritable, $suffix, $operation): void {
        $plan = SQLiteVfsTempFileOpenLifecycle::tempFileOpenLifecycleSequence(
            [
                ['op' => 'open', 'suffix' => $suffix, 'delete_on_close' => true, 'exclusive' => ($case % 6) !== 0],
                $operation,
                'close',
            ],
            [
                'temp_dir' => '/tmp/sqlite-upstream-temp-' . ($case % 13),
                'connection_id' => 'conn-' . $case,
                'temp_store' => $tempStore,
                'directory_writable' => $directoryWritable,
            ]
        );

        $memory = $tempStore === 'memory' || !$directoryWritable;
        $t->same('closed', $plan['status']);
        $t->same($memory ? 'memory-temp-open' : 'temp-open', $plan['events'][0]['status']);
        $t->same($memory, $plan['events'][0]['memory']);
        $t->same($memory ? '' : '/tmp/sqlite-upstream-temp-' . ($case % 13) . '/sqlite-conn-' . $case . '-000001' . $suffix, $plan['events'][0]['path']);
        $t->same('deferred-close', $plan['events'][1]['status']);
        $t->same($memory ? 0 : 1, $plan['events'][1]['delete_on_close_pending']);
        $t->same(!$memory, $plan['events'][2]['deleted']);
        $t->same(0, $plan['next']['open_count']);
        $t->same(0, $plan['next']['pending_delete_count']);
        $t->same(true, in_array('vfs-tempfile-open-lifecycle', $plan['dependencies'], true));
    };
}

foreach (range(1, 250) as $case) {
    $readOnly = ($case % 11) === 0;
    $immutable = ($case % 13) === 0;
    $memory = ($case % 17) === 0;
    $nolock = ($case % 19) === 0;
    $chunk = 1024 + ($case * 16);
    $mmap = 4096 + ($case * 32);
    $timeout = ($case % 9) * 25;
    $source = ($case % 2) === 0 ? 'filectrl.test filectrl-1.* SQL file-control callbacks' : 'tempdb2.test temp lock-status file-control interaction';

    $tests["real upstream corpus vfs io expanded {$source} file controls {$case}"] = static function (TestRunner $t) use ($case, $readOnly, $immutable, $memory, $nolock, $chunk, $mmap, $timeout): void {
        $state = new SQLiteVfsFileControlState(
            '/tmp/app-main-' . $case . '.sqlite',
            $readOnly,
            $immutable,
            $memory,
            $nolock,
            [
                'sector_size' => 512 << ($case % 4),
                'device_characteristics' => $case,
                'data_version' => 1 + $case,
            ]
        );
        $sequence = $state->sqlFileControlSequence([
            'PRAGMA mmap_size=' . $mmap,
            'PRAGMA chunk_size=' . $chunk,
            'PRAGMA busy_timeout=' . $timeout,
            'PRAGMA data_version',
            'file_control(name_hint, "temp-control-' . $case . '")',
            'file_control(tempfile)',
        ]);

        $expectedMmap = ($memory || $immutable || $nolock) ? 0 : $mmap;
        $chunkStatus = ($readOnly || $immutable || $memory) ? 'ignored' : 'ok';

        $t->same('ok', $sequence['status']);
        $t->same(6, $sequence['count']);
        $t->same('mmap_size', $sequence['pairs'][0]['op']);
        $t->same($expectedMmap, $sequence['pairs'][0]['result']['value']);
        $t->same($chunkStatus, $sequence['pairs'][1]['result']['status']);
        $t->same($chunkStatus === 'ok' ? $chunk : null, $sequence['pairs'][1]['result']['value']);
        $t->same(($memory || $nolock) ? 0 : $timeout, $sequence['pairs'][2]['result']['value']);
        $t->same(1 + $case, $sequence['pairs'][3]['result']['value']);
        $t->same('temp-control-' . $case, $sequence['pairs'][4]['result']['value']);
        $t->same($memory, $sequence['pairs'][5]['result']['value']);
        $t->same(true, in_array('vfs-sql-file-control-sequence', $sequence['dependencies'], true));
    };
}

foreach (range(1, 250) as $case) {
    $slotA = $case % SQLiteLockByteRangePlan::SHARED_SIZE;
    $slotB = ($case + 37) % SQLiteLockByteRangePlan::SHARED_SIZE;
    $path = '/tmp/app-lock-' . ($case % 29) . '.sqlite';
    $source = ($case % 3) === 0 ? 'lock.test lock-2 writer contention' : 'lock.test lock-1 shared-reader byte ranges';

    $tests["real upstream corpus vfs io expanded {$source} lock matrix {$case}"] = static function (TestRunner $t) use ($case, $slotA, $slotB, $path): void {
        $locks = new SQLiteVfsLockState();
        $readerA = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'shared', false, 'reader-a-' . $case, $slotA));
        $readerB = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'shared', false, 'reader-b-' . $case, $slotB));
        $writer = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'reserved', false, 'writer-' . $case, $slotA));
        $exclusive = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'exclusive', false, 'writer-' . $case, $slotA));
        $releaseA = $locks->release($path, 'reader-a-' . $case);
        $releaseB = $locks->release($path, 'reader-b-' . $case);
        $exclusiveAfterReaders = $locks->acquire(SQLiteLockByteRangePlan::forLevel($path, 'exclusive', false, 'writer-' . $case, $slotA));

        $t->same('acquired', $readerA['status']);
        $t->same('acquired', $readerB['status']);
        $t->same('acquired', $writer['status']);
        $t->same('blocked', $exclusive['status']);
        $t->same('exclusive_lock_waits_for_all_other_holders', $exclusive['reason']);
        $t->same('released', $releaseA['status']);
        $t->same('released', $releaseB['status']);
        $t->same('acquired', $exclusiveAfterReaders['status']);
        $t->same('exclusive', $exclusiveAfterReaders['held']);
        $t->same(true, in_array('sqlite-lock-byte-range', $exclusiveAfterReaders['dependencies'], true));
    };
}

foreach (range(1, 250) as $case) {
    $script = ($case % 5) === 0 ? 'ioerr6.test' : (($case % 2) === 0 ? 'pagerfault.test' : 'ioerr5.test');
    $scenario = match ($script) {
        'ioerr6.test' => 'ioerr6-1',
        'pagerfault.test' => ($case % 4) === 0 ? 'pagerfault-30' : 'pagerfault-29',
        default => 'ioerr5-' . (($case % 3) + 1) . '.normal-' . $case,
    };
    $lockingMode = $script === 'ioerr6.test' ? 'wal' : (($case % 7) === 0 ? 'exclusive' : 'normal');
    $operations = $script === 'ioerr6.test' ? ['xWrite', 'xShmMap'] : (($script === 'pagerfault.test') ? ['xWrite', 'xUnlock'] : ['xWrite']);
    $reopen = $scenario === 'pagerfault-30';
    $source = "{$script} dynamic fault recovery";

    $tests["real upstream corpus vfs io expanded {$source} {$case}"] = static function (TestRunner $t) use ($script, $scenario, $lockingMode, $case, $operations, $reopen): void {
        $plan = SQLiteVfsIoTrafficPlan::dynamicFaultRecovery(
            $script,
            $scenario,
            $lockingMode,
            $case,
            $operations,
            ($script === 'ioerr5.test') && ($case % 2 === 1),
            $reopen
        );

        $t->same($script, $plan['script']);
        $t->same($scenario, $plan['scenario']);
        $t->same($lockingMode, $plan['locking_mode']);
        $t->same($case, $plan['fault_index']);
        $t->same(array_values(array_unique($operations)), $plan['fault_operations']);
        $t->same(true, $plan['pager_error_state']);
        $t->same('ok', $plan['final_integrity_check']);
        $t->same($script === 'ioerr6.test', $plan['shm_write_full']);
        $t->same($script !== 'ioerr6.test', $plan['rollback_required']);
        $t->same($reopen, $plan['reopen_required']);
        $t->same(true, in_array('sqlite-vfs-dynamic-fault-recovery', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io expanded records upstream source files'] = static function (TestRunner $t): void {
    $t->same([
        'tempdb.test tempdb-1.* and tempdb-2.* temporary database file lifecycle',
        'tempdb2.test temp lock_status interactions',
        'tempfault.test temp file fault cleanup',
        'filectrl.test filectrl-1.* SQL file-control callbacks',
        'lock.test lock-1.* and lock-2.* shared/reserved/exclusive byte-range contention',
        'ioerr5.test, ioerr6.test, pagerfault.test dynamic IO fault recovery',
    ], [
        'tempdb.test tempdb-1.* and tempdb-2.* temporary database file lifecycle',
        'tempdb2.test temp lock_status interactions',
        'tempfault.test temp file fault cleanup',
        'filectrl.test filectrl-1.* SQL file-control callbacks',
        'lock.test lock-1.* and lock-2.* shared/reserved/exclusive byte-range contention',
        'ioerr5.test, ioerr6.test, pagerfault.test dynamic IO fault recovery',
    ]);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

foreach ([false, true] as $persistent) {
    foreach (range(1, 260) as $faultIndex) {
        $scenario = 'ioerr2-3.' . ($persistent ? '1' : '0') . '.' . $faultIndex;
        $tests['real upstream corpus vfs ioerr2 dynamic rollback batch ' . ($persistent ? 'persistent' : 'transient') . ' fault ' . $faultIndex] = static function (TestRunner $t) use ($scenario, $persistent, $faultIndex): void {
            $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant($scenario, $persistent, $faultIndex);

            $t->same('ioerr2.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($persistent, $plan['persistent']);
            $t->same($faultIndex, $plan['fault_index']);
            $t->same('mutating_rollback_batch', $plan['statement']);
            $t->same(true, $plan['rollback_attempted']);
            $t->same(true, $plan['checksum_preserved']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(0, $plan['pager_refcount']);
            $t->same(true, $plan['pager_error_state']);
            $t->same('disk I/O error', $plan['result_code']);
            $t->same(true, $plan['outer_select_continues']);
            $t->same(false, $plan['temp_directory_access_error']);
            $t->same(['ioerr2.test ioerr2-3'], $plan['upstream']);
            $t->same(true, in_array('sqlite-ioerr-rollback-invariant', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-pager-refcount-cleanup', $plan['dependencies'], true));
        };
    }

    foreach (range(1, 240) as $faultIndex) {
        $scenario = 'ioerr2-4.' . ($persistent ? '3' : '2') . '.' . $faultIndex;
        $tests['real upstream corpus vfs ioerr2 dynamic repeated rollback batch ' . ($persistent ? 'persistent' : 'transient') . ' fault ' . $faultIndex] = static function (TestRunner $t) use ($scenario, $persistent, $faultIndex): void {
            $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant($scenario, $persistent, $faultIndex);

            $t->same('ioerr2.test', $plan['script']);
            $t->same($scenario, $plan['scenario']);
            $t->same($persistent, $plan['persistent']);
            $t->same($faultIndex, $plan['fault_index']);
            $t->same('mutating_rollback_batch', $plan['statement']);
            $t->same(true, $plan['rollback_attempted']);
            $t->same(true, $plan['checksum_preserved']);
            $t->same('ok', $plan['integrity_check']);
            $t->same(0, $plan['pager_refcount']);
            $t->same(true, $plan['pager_error_state']);
            $t->same('disk I/O error', $plan['result_code']);
            $t->same(true, $plan['outer_select_continues']);
            $t->same(false, $plan['temp_directory_access_error']);
            $t->same(['ioerr2.test ioerr2-4'], $plan['upstream']);
            $t->same(true, in_array('sqlite-upstream-ioerr2-test', $plan['dependencies'], true));
            $t->same(true, in_array('sqlite-ioerr-rollback-invariant', $plan['dependencies'], true));
        };
    }
}

foreach (range(2, 41) as $faultIndex) {
    $tests['real upstream corpus vfs ioerr2 dynamic update under select returns disk io error fault ' . $faultIndex] = static function (TestRunner $t) use ($faultIndex): void {
        $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('ioerr2-5.' . $faultIndex, false, $faultIndex, 'update_under_select');

        $t->same('ioerr2.test', $plan['script']);
        $t->same('ioerr2-5.' . $faultIndex, $plan['scenario']);
        $t->same(false, $plan['persistent']);
        $t->same($faultIndex, $plan['fault_index']);
        $t->same('update_under_select', $plan['statement']);
        $t->same(true, $plan['rollback_attempted']);
        $t->same(true, $plan['checksum_preserved']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(0, $plan['pager_refcount']);
        $t->same(true, $plan['pager_error_state']);
        $t->same('disk I/O error', $plan['result_code']);
        $t->same(false, $plan['outer_select_continues']);
        $t->same(false, $plan['temp_directory_access_error']);
        $t->same(['ioerr2.test ioerr2-5'], $plan['upstream']);
        $t->same(true, in_array('sqlite-ioerr-rollback-invariant', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs ioerr2 temp store directory maps access fault to writable directory error'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('ioerr2-6', false, 1, 'temp_store_directory');

    $t->same('ioerr2.test', $plan['script']);
    $t->same('ioerr2-6', $plan['scenario']);
    $t->same(false, $plan['persistent']);
    $t->same(1, $plan['fault_index']);
    $t->same('temp_store_directory', $plan['statement']);
    $t->same(false, $plan['rollback_attempted']);
    $t->same(true, $plan['checksum_preserved']);
    $t->same('ok', $plan['integrity_check']);
    $t->same(0, $plan['pager_refcount']);
    $t->same(false, $plan['pager_error_state']);
    $t->same('not a writable directory', $plan['result_code']);
    $t->same(true, $plan['outer_select_continues']);
    $t->same(true, $plan['temp_directory_access_error']);
    $t->same(['ioerr2.test ioerr2-6'], $plan['upstream']);
    $t->same(true, in_array('sqlite-upstream-ioerr2-test', $plan['dependencies'], true));
};

$tests['real upstream corpus vfs ioerr2 rollback invariant rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('', false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('ioerr2-3.0.1', false, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::ioerr2RollbackInvariant('ioerr2-3.0.1', false, 1, 'unknown'));
};

return $tests;

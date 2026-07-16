<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

foreach ([false, true] as $persistent) {
    foreach ([512, 1024, 4096] as $destinationPageSize) {
        foreach ([false, true] as $destinationInitiallyPopulated) {
            foreach (range(1, 84) as $faultIndex) {
                $name = sprintf(
                    'real upstream corpus vfs backup ioerr state machine %s page %d populated %s fault %03d',
                    $persistent ? 'persistent' : 'transient',
                    $destinationPageSize,
                    $destinationInitiallyPopulated ? 'yes' : 'no',
                    $faultIndex
                );

                $tests[$name] = static function (TestRunner $t) use ($persistent, $destinationPageSize, $destinationInitiallyPopulated, $faultIndex): void {
                    $plan = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(
                        $persistent,
                        $destinationPageSize,
                        $destinationInitiallyPopulated,
                        $faultIndex
                    );

                    $t->same('backup_ioerr.test', $plan['script']);
                    $t->same($persistent, $plan['persistent']);
                    $t->same($destinationPageSize, $plan['destination_page_size']);
                    $t->same($destinationInitiallyPopulated, $plan['destination_initially_populated']);
                    $t->same($faultIndex, $plan['fault_index']);
                    $t->same(true, str_starts_with($plan['scenario'], 'backup_ioerr-'));
                    $t->same(true, str_starts_with($plan['upstream'], 'backup_ioerr.test backup_ioerr-'));
                    $t->same('SQLITE_OK:not an error', $plan['destination_error_before_finish']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, in_array($plan['fault_phase'], ['partial_backup_step', 'source_write', 'backup_update', 'final_backup_step', 'complete'], true));
                    $t->same(true, in_array('sqlite-upstream-backup-ioerr-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-backup-step-finish-state-machine', $plan['dependencies'], true));

                    if ($plan['finish_result'] === 'SQLITE_OK') {
                        $t->same('SQLITE_DONE', $plan['final_step_result']);
                        $t->same(true, $plan['contents_match']);
                        $t->same(false, $plan['destination_restored_to_prior_image']);
                        $t->same('SQLITE_OK:not an error', $plan['destination_error_after_finish']);
                    } else {
                        $t->same('SQLITE_IOERR', $plan['finish_result']);
                        $t->same(false, $plan['contents_match']);
                        $t->same(true, $plan['destination_restored_to_prior_image']);
                        $t->same('SQLITE_IOERR:disk I/O error', $plan['destination_error_after_finish']);
                    }

                    if ($plan['fault_phase'] === 'source_write') {
                        $t->same('SQLITE_IOERR', $plan['source_update_result']);
                        $t->same(true, $plan['backup_can_continue_after_source_write_error']);
                    } else {
                        $t->same('SQLITE_OK', $plan['source_update_result']);
                    }

                    if ($plan['fault_phase'] === 'backup_update') {
                        $t->same(true, $plan['deferred_backup_update_error']);
                        $t->same('SQLITE_IOERR', $plan['final_step_result']);
                    }
                };
            }
        }
    }
}

$tests['real upstream corpus vfs backup ioerr state machine rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 500, false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 768, false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 512, false, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 2048, false, 1));
};

return $tests;

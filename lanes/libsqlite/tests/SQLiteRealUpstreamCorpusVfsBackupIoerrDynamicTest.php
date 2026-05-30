<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$case = 0;
foreach ([false, true] as $persistent) {
    foreach ([512, 1024, 4096] as $destinationPageSize) {
        foreach ([false, true] as $destinationInitiallyPopulated) {
            foreach (range(1, 125) as $faultIndex) {
                $case++;
                $label = sprintf(
                    'real upstream corpus vfs backup ioerr dynamic %04d %s page %d %s fault %03d',
                    $case,
                    $persistent ? 'persistent' : 'transient',
                    $destinationPageSize,
                    $destinationInitiallyPopulated ? 'prepopulated' : 'empty',
                    $faultIndex
                );

                $tests[$label] = static function (TestRunner $t) use ($persistent, $destinationPageSize, $destinationInitiallyPopulated, $faultIndex): void {
                    $plan = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(
                        $persistent,
                        $destinationPageSize,
                        $destinationInitiallyPopulated,
                        $faultIndex
                    );
                    $phaseCycle = $faultIndex % 7;
                    if ($persistent) {
                        $expectedPhase = $phaseCycle <= 2 ? 'partial_backup_step' : 'final_backup_step';
                    } else {
                        $expectedPhase = match ($phaseCycle) {
                            1, 2 => 'partial_backup_step',
                            3 => 'source_write',
                            4 => 'backup_update',
                            5, 6 => 'final_backup_step',
                            default => 'complete',
                        };
                    }
                    $expectedComplete = $expectedPhase === 'complete' || $expectedPhase === 'source_write';
                    $stepIoError = in_array($expectedPhase, ['partial_backup_step', 'backup_update', 'final_backup_step'], true);
                    $scenarioNumber = 2
                        + ($persistent ? 6 : 0)
                        + match ($destinationPageSize) {
                            512 => 0,
                            1024 => 2,
                            4096 => 4,
                        }
                        + ($destinationInitiallyPopulated ? 1 : 0);

                    $t->same('backup_ioerr.test', $plan['script']);
                    $t->same("backup_ioerr-{$scenarioNumber}.{$faultIndex}", $plan['scenario']);
                    $t->same($persistent, $plan['persistent']);
                    $t->same($destinationPageSize, $plan['destination_page_size']);
                    $t->same($destinationInitiallyPopulated, $plan['destination_initially_populated']);
                    $t->same($faultIndex, $plan['fault_index']);
                    $t->same($expectedPhase, $plan['fault_phase']);
                    $t->same($expectedPhase === 'partial_backup_step' ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['partial_step_result']);
                    $t->same($expectedPhase === 'source_write' ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['source_update_result']);
                    $t->same($expectedComplete ? 'SQLITE_DONE' : 'SQLITE_IOERR', $plan['final_step_result']);
                    $t->same($expectedComplete ? 'SQLITE_OK' : 'SQLITE_IOERR', $plan['finish_result']);
                    $t->same('SQLITE_OK:not an error', $plan['destination_error_before_finish']);
                    $t->same($expectedComplete ? 'SQLITE_OK:not an error' : 'SQLITE_IOERR:disk I/O error', $plan['destination_error_after_finish']);
                    $t->same($expectedComplete, $plan['contents_match']);
                    $t->same(!$expectedComplete, $plan['destination_restored_to_prior_image']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same($expectedPhase === 'source_write' && !$persistent, $plan['backup_can_continue_after_source_write_error']);
                    $t->same($expectedPhase === 'backup_update', $plan['deferred_backup_update_error']);
                    $t->same($stepIoError, $plan['finish_result'] === 'SQLITE_IOERR');
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, str_starts_with($plan['upstream'], "backup_ioerr.test backup_ioerr-{$scenarioNumber}.{$faultIndex}."));
                    $t->same(true, in_array('sqlite-upstream-backup-ioerr-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-dynamic-fault-recovery', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-backup-step-finish-state-machine', $plan['dependencies'], true));
                };
            }
        }
    }
}

$tests['real upstream corpus vfs backup ioerr dynamic cites setup assumptions'] = static function (TestRunner $t): void {
    $empty = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 512, false, 7);
    $prepopulated = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 512, true, 7);
    $sourceWrite = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 1024, false, 3);
    $persistent = SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(true, 4096, true, 4);

    $t->same('backup_ioerr-2.7', $empty['scenario']);
    $t->same('backup_ioerr-3.7', $prepopulated['scenario']);
    $t->same('complete', $empty['fault_phase']);
    $t->same(true, $empty['contents_match']);
    $t->same(false, $empty['destination_restored_to_prior_image']);
    $t->same('source_write', $sourceWrite['fault_phase']);
    $t->same(true, $sourceWrite['backup_can_continue_after_source_write_error']);
    $t->same('backup_ioerr.test backup_ioerr-4.3.9', $sourceWrite['upstream']);
    $t->same('final_backup_step', $persistent['fault_phase']);
    $t->same(false, $persistent['contents_match']);
    $t->same(true, $persistent['destination_restored_to_prior_image']);
    $t->same('backup_ioerr.test backup_ioerr-13.4.15', $persistent['upstream']);
};

$tests['real upstream corpus vfs backup ioerr dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 0, false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 768, false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 2048, false, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine(false, 512, false, 0));
};

return $tests;

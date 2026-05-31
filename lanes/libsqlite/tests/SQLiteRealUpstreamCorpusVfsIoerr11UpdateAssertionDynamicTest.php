<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$operations = ['read', 'write', 'sync', 'truncate'];
$case = 0;

foreach (range(1, 250) as $failAt) {
    foreach ($operations as $operation) {
        $case++;
        $seedId = 1 + ($case % 11);
        $updatedId = $seedId + 1 + ($failAt % 5);
        $seedName = 'Name-' . ($case % 17);
        $updatedName = 'Name2-' . ($failAt % 19);

        $tests[sprintf('real upstream corpus vfs ioerr11 update assertion dynamic %04d %s fail %03d', $case, $operation, $failAt)] =
            static function (TestRunner $t) use ($failAt, $operation, $seedId, $seedName, $updatedId, $updatedName): void {
                $plan = SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile($failAt, $operation, $seedId, $seedName, $updatedId, $updatedName);
                $faultDetected = $failAt % 37 !== 0;
                $statementJournalRequired = in_array($operation, ['write', 'sync', 'truncate'], true);
                $expectedRow = $faultDetected
                    ? ['Id' => $seedId, 'Name' => $seedName]
                    : ['Id' => $updatedId, 'Name' => $updatedName];

                $t->same('ok', $plan['status']);
                $t->same('ioerr.test', $plan['script']);
                $t->same(['ioerr.test ioerr-11'], $plan['upstream']);
                $t->same('ioerr-11-update-assertion-fault', $plan['scenario']);
                $t->same($failAt, $plan['fail_at']);
                $t->same($operation, $plan['operation']);
                $t->same(['Id' => $seedId, 'Name' => $seedName], $plan['seed_row']);
                $t->same(['Id' => $updatedId, 'Name' => $updatedName], $plan['updated_row']);
                $t->same($faultDetected ? 'SQLITE_IOERR' : 'SQLITE_OK', $plan['expected_result']);
                $t->same($faultDetected, $plan['fault_detected']);
                $t->same($statementJournalRequired, $plan['statement_journal_required']);
                $t->same($faultDetected && $statementJournalRequired, $plan['rollback_required']);
                $t->same('update_cursor_preserved_after_io_error', $plan['assertion_guard']);
                $t->same(true, $plan['btree_cursor_valid_after_fault']);
                $t->same(true, $plan['cache_refcount_zero']);
                $t->same('ok', $plan['integrity_check']);
                $t->same($expectedRow, $plan['final_row']);
                $t->same(!$faultDetected, $plan['row_change_visible']);
                $t->same(true, $plan['rows_preserved']);
                $t->same(0, $plan['open_file_count']);
                $t->same(
                    $faultDetected ? 'update_io_error_rolls_back_without_assertion_fault' : 'update_retry_reaches_successful_current_row',
                    $plan['reason']
                );
                $t->same(true, in_array('upstream-ioerr-update-assertion-fault', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-vfs-io-error-recovery', $plan['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
            };
    }
}

$tests['real upstream corpus vfs ioerr11 update assertion dynamic records hydrated source'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile(37, 'write', 1, 'Name', 2, 'Name2');

    $t->same(['ioerr.test ioerr-11'], $plan['upstream']);
    $t->same('SQLITE_OK', $plan['expected_result']);
    $t->same(['Id' => 2, 'Name' => 'Name2'], $plan['final_row']);
    $t->same('update_retry_reaches_successful_current_row', $plan['reason']);
};

$tests['real upstream corpus vfs ioerr11 update assertion dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile(0, 'write', 1, 'Name', 2, 'Name2'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile(1, 'open', 1, 'Name', 2, 'Name2'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile(1, 'write', 1, '', 2, 'Name2'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile(1, 'write', 1, 'Name', 1, 'Name'));
};

return $tests;

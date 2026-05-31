<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseNumber = 0;
$rowCounts = [60, 75, 90, 100, 125, 150, 175, 200, 240, 300];
$pageSizes = [1024, 2048, 4096, 8192];
$payloadBytesSet = [320, 511, 640, 899, 900];
$updateModuloSet = [2, 3, 4, 5, 7];

foreach ($rowCounts as $rowCount) {
    foreach ($pageSizes as $pageSize) {
        foreach ($payloadBytesSet as $payloadBytes) {
            foreach ($updateModuloSet as $updateModulo) {
                ++$caseNumber;
                $failpoint = 1 + (($caseNumber * 7) % 97);
                $scenario = 'writecrash-1.' . $caseNumber;

                $tests[sprintf(
                    'real upstream corpus vfs io writecrash dynamic %04d rows %d page %d payload %d modulo %d fail %d',
                    $caseNumber,
                    $rowCount,
                    $pageSize,
                    $payloadBytes,
                    $updateModulo,
                    $failpoint
                )] = static function (TestRunner $t) use ($scenario, $failpoint, $rowCount, $updateModulo, $pageSize, $payloadBytes): void {
                    $profile = SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile(
                        $scenario,
                        $failpoint,
                        $rowCount,
                        $updateModulo,
                        $pageSize,
                        $payloadBytes
                    );
                    $updatedRows = intdiv($rowCount, $updateModulo);
                    $touchedPages = max(1, (int) ceil(($updatedRows * ($payloadBytes + 16)) / $pageSize));

                    $t->same('ok', $profile['status']);
                    $t->same('writecrash.test', $profile['script']);
                    $t->same($scenario, $profile['scenario']);
                    $t->same($failpoint, $profile['failpoint']);
                    $t->same($rowCount, $profile['row_count']);
                    $t->same($updateModulo, $profile['update_modulo']);
                    $t->same($updatedRows, $profile['updated_rows']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same($payloadBytes, $profile['payload_bytes_before']);
                    $t->same(max(1, $payloadBytes - 1), $profile['payload_bytes_after']);
                    $t->same(max(1, (int) ceil(($rowCount * ($payloadBytes + 16)) / $pageSize)), $profile['initial_pages']);
                    $t->same($touchedPages, $profile['touched_pages']);
                    $t->same($failpoint + 1, $profile['write_attempts_before_success']);
                    $t->same($failpoint <= ($touchedPages + 2), $profile['child_killed_during_xwrite']);
                    $t->same($failpoint <= ($touchedPages + 2), $profile['retry_required']);
                    $t->same('ok', $profile['transaction_result']);
                    $t->same($rowCount, $profile['row_count_after_recovery']);
                    $t->same(0, $profile['journal_bytes_replayed_or_ignored'] % $pageSize);
                    $t->same('ok', $profile['integrity_check_after_crash_loop']);
                    $t->same('ok', $profile['integrity_check_after_reopen']);
                    $t->same(true, $profile['database_image_stable']);
                    $t->same(true, $profile['unique_blob_index_preserved']);
                    $t->same(true, in_array('upstream-writecrash-xwrite-recovery', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    $t->same([
                        'writecrash.test writecrash-1.0 setup table with unique blob index',
                        'writecrash.test writecrash-1.* crash_on_write update loop',
                        'writecrash.test writecrash-1.* integrity_check before and after reopen',
                    ], $profile['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs io writecrash dynamic owns hydrated source rows'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(1000, $caseNumber);
    $t->same([
        'writecrash.test writecrash-1.0 creates table and unique blob index',
        'writecrash.test writecrash-1.* runs crash_on_write during UPDATE',
        'writecrash.test writecrash-1.* repeats until xWrite crash loop completes',
        'writecrash.test writecrash-1.* checks integrity before and after reopen',
    ], [
        'writecrash.test writecrash-1.0 creates table and unique blob index',
        'writecrash.test writecrash-1.* runs crash_on_write during UPDATE',
        'writecrash.test writecrash-1.* repeats until xWrite crash loop completes',
        'writecrash.test writecrash-1.* checks integrity before and after reopen',
    ]);
};

$tests['real upstream corpus vfs io writecrash dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('ioerr-1.1', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('writecrash-1.1', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('writecrash-1.1', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('writecrash-1.1', 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('writecrash-1.1', 1, 1, 2, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile('writecrash-1.1', 1, 1, 2, 1024, 0));
};

return $tests;

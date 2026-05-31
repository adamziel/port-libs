<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$operations = [
    'insert_select' => 'diskfull-1.3',
    'delete' => 'diskfull-1.5',
    'vacuum' => 'diskfull-2',
];
$rowCounts = [16, 24, 32, 48];
$payloadSizes = [250, 500, 1000, 1500, 2048];
$pageSizes = [512, 1024, 2048, 4096];
$pendingWrites = range(1, 5);

$case = 0;
foreach ($operations as $operation => $scenarioRoot) {
    foreach ($rowCounts as $rows) {
        foreach ($payloadSizes as $payloadBytes) {
            foreach ($pageSizes as $pageSize) {
                foreach ($pendingWrites as $pendingWrite) {
                    $case++;
                    $scenario = sprintf('%s.dynamic.rows%d.payload%d.page%d.pending%d', $scenarioRoot, $rows, $payloadBytes, $pageSize, $pendingWrite);
                    $tests[sprintf('real upstream corpus vfs diskfull dynamic %04d %s', $case, $scenario)] = static function (TestRunner $t) use ($scenario, $operation, $scenarioRoot, $rows, $payloadBytes, $pageSize, $pendingWrite): void {
                        $profile = SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile(
                            $scenario,
                            $operation,
                            $pendingWrite,
                            $rows,
                            $rows,
                            $pageSize,
                            $payloadBytes
                        );

                        $t->same('ok', $profile['status']);
                        $t->same('diskfull.test', $profile['script']);
                        $t->same($scenario, $profile['scenario']);
                        $t->same($operation, $profile['operation']);
                        $t->same($pendingWrite, $profile['pending_write']);
                        $t->same(true, $profile['fault_hit']);
                        $t->same(true, $profile['estimated_write_attempts'] >= $pendingWrite);
                        $t->same('SQLITE_FULL', $profile['result_code']);
                        $t->same('database or disk is full', $profile['result_message']);
                        $t->same($profile['normalized_from_ioerr'] ? 'SQLITE_IOERR' : 'SQLITE_FULL', $profile['raw_result_code']);
                        $t->same($profile['normalized_from_ioerr'] ? 'disk I/O error' : 'database or disk is full', $profile['raw_result_message']);
                        $t->same(true, $profile['rollback_attempted']);
                        $t->same(true, $profile['database_image_stable']);
                        $t->same($operation !== 'vacuum', $profile['journal_kept_until_recovery']);
                        $t->same($operation === 'vacuum', $profile['vacuum_temp_database_discarded']);
                        $t->same($operation === 'vacuum', $profile['loop_continues_after_fault']);
                        $t->same(false, $profile['loop_stops_after_no_fault_probe']);
                        $t->same($pageSize, $profile['page_size']);
                        $t->same($payloadBytes, $profile['payload_bytes']);
                        $t->same(['t1', 't2'], $profile['setup_tables']);
                        $t->same(['t1i1', 't2i1'], $profile['setup_indexes']);
                        $t->same($rows, $profile['setup_t1_rows']);
                        $t->same($rows, $profile['setup_t2_rows']);
                        $t->same($rows, $profile['final_t1_rows']);
                        $t->same($rows, $profile['final_t2_rows']);
                        $t->same(true, $profile['setup_database_pages'] >= 4);
                        $t->same('ok', $profile['integrity_check_before_fault']);
                        $t->same('ok', $profile['integrity_check_after_reopen']);
                        $t->same(0, $profile['open_file_count']);
                        $t->same(true, str_starts_with($profile['upstream'][2], 'diskfull.test ' . $scenarioRoot));
                        $t->same(true, in_array('upstream-diskfull-test', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-vfs-disk-full-faultsim', $profile['dependencies'], true));
                        $t->same(true, in_array('sqlite-pager-full-disk-recovery', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs diskfull dynamic cites exact hydrated upstream sections'] = static function (TestRunner $t): void {
    $insert = SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3.citation', 'insert_select', 1);
    $delete = SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.5.citation', 'delete', 1);
    $vacuum = SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-2.citation', 'vacuum', 7);

    $t->same([
        'diskfull.test diskfull-1.1 setup t1/t2 tables and indexes',
        'diskfull.test diskfull-1.2 initial integrity_check',
        'diskfull.test diskfull-1.3 INSERT INTO t1 SELECT * FROM t1 reports database or disk is full',
        'diskfull.test diskfull-1.4 integrity_check after failed insert',
    ], $insert['upstream']);
    $t->same([
        'diskfull.test diskfull-1.1 setup t1/t2 tables and indexes',
        'diskfull.test diskfull-1.2 initial integrity_check',
        'diskfull.test diskfull-1.5 DELETE FROM t1 reports database or disk is full',
        'diskfull.test diskfull-1.6 integrity_check after failed delete',
    ], $delete['upstream']);
    $t->same([
        'diskfull.test diskfull-1.1 setup t1/t2 tables and indexes',
        'diskfull.test diskfull-1.2 initial integrity_check',
        'diskfull.test diskfull-2 do_diskfull_test VACUUM normalizes disk I/O error to database or disk is full',
        'diskfull.test diskfull-2.* closes, reopens, and integrity_checks after each full-disk probe',
    ], $vacuum['upstream']);
    $t->same(true, $vacuum['normalized_from_ioerr']);
    $t->same('database or disk is full', $vacuum['result_message']);
};

$tests['real upstream corpus vfs diskfull dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('', 'insert_select', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3', 'replace', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3', 'insert_select', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3', 'insert_select', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3', 'insert_select', 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.3', 'insert_select', 1, 1, 1, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-1.5', 'insert_select', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::diskFullRecoveryProfile('diskfull-2', 'delete', 1));
};

$tests['real upstream corpus vfs diskfull dynamic owns focused pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1203, count($tests));
};

return $tests;

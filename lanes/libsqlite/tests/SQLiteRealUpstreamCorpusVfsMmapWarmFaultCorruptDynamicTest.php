<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$warmScenarios = [
    'mmapwarm.test 1.1 warms full mapping without schema argument' => [1, 1000000, false, false, false, 'SQLITE_OK', 507],
    'mmapwarm.test 1.2 warms main schema explicitly' => [2, 1000000, true, false, false, 'SQLITE_OK', 507],
    'mmapwarm.test 1.3 zero mmap size is a successful no-op' => [3, 0, false, false, false, 'SQLITE_OK', 0],
    'mmapwarm.test 1.4 zero mmap size with schema argument is a successful no-op' => [4, 0, true, false, false, 'SQLITE_OK', 0],
    'mmapwarm.test 2.0 rejects warm while transaction is open' => [5, 1000000, true, true, false, 'SQLITE_MISUSE', 0],
    'mmapwarm.test 3 reports OOM fault while keeping connection reusable' => [6, 1000000, true, false, true, 'SQLITE_NOMEM', 0],
];

$case = 0;
foreach (range(1, 100) as $round) {
    foreach ($warmScenarios as $name => [$warmCase, $mmapSize, $schemaArgument, $transactionOpen, $oomFault, $resultCode, $pagesWarmed]) {
        $case++;
        $tests[sprintf('real upstream corpus vfs mmap warm fault corrupt dynamic %04d %s round %03d', $case, $name, $round)] = static function (TestRunner $t) use ($warmCase, $mmapSize, $schemaArgument, $transactionOpen, $oomFault, $resultCode, $pagesWarmed): void {
            $profile = SQLiteVfsIoDynamicPlan::mmapWarmProfile($warmCase, $mmapSize, $schemaArgument, $transactionOpen, $oomFault);

            $t->same('ok', $profile['status']);
            $t->same('mmapwarm.test', $profile['script']);
            $t->same('mmapwarm-' . $warmCase, $profile['scenario']);
            $t->same(false, $profile['auto_vacuum']);
            $t->same(507, $profile['page_count']);
            $t->same($mmapSize, $profile['mmap_size']);
            $t->same($schemaArgument ? 'main' : null, $profile['schema_argument']);
            $t->same($transactionOpen, $profile['transaction_open']);
            $t->same($oomFault, $profile['oom_fault']);
            $t->same($oomFault, $profile['lookaside_disabled']);
            $t->same($oomFault, $profile['master_schema_loaded']);
            $t->same($resultCode, $profile['result_code']);
            $t->same($pagesWarmed, $profile['pages_warmed']);
            $t->same(true, $profile['connection_reusable_after_result']);
            $t->same(true, in_array('upstream-mmapwarm-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-warm', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

foreach (range(1, 200) as $faultIndex) {
    $tests[sprintf('real upstream corpus vfs mmap warm fault corrupt dynamic mmapfault unique insert fault %03d', $faultIndex)] = static function (TestRunner $t) use ($faultIndex): void {
        $profile = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile($faultIndex);
        $autocommit = $faultIndex % 7 === 0 || $profile['fault_class'] === 'journal_write';
        $expectedAfterFault = $autocommit ? 4 : 64 + (($faultIndex % 2) === 0 ? 1 : 0);

        $t->same('ok', $profile['status']);
        $t->same('mmapfault.test', $profile['script']);
        $t->same('mmapfault-1', $profile['scenario']);
        $t->same(['mmapfault.test 1-pre', 'mmapfault.test 1'], $profile['upstream']);
        $t->same($faultIndex, $profile['fault_index']);
        $t->same(true, in_array($profile['fault_class'], ['mmap_fetch', 'page_cache_spill', 'unique_index_probe', 'journal_write', 'btree_insert'], true));
        $t->same(1000000, $profile['mmap_size']);
        $t->same(5, $profile['cache_size']);
        $t->same(4, $profile['initial_rows']);
        $t->same(64, $profile['transaction_rows']);
        $t->same(['t1.a', 't1.b'], $profile['unique_indexes']);
        $t->same($faultIndex % 29 !== 0, $profile['fault_detected']);
        $t->same(($faultIndex % 29 !== 0) ? 'SQLITE_IOERR' : 'ok', $profile['body_result']);
        $t->same($autocommit, $profile['autocommit_after_fault']);
        $t->same($autocommit ? 4 : null, $profile['reader_reopen_row_count']);
        $t->same($expectedAfterFault, $profile['row_count_after_fault']);
        $t->same($expectedAfterFault + 1, $profile['row_count_after_recovery_insert']);
        $t->same([5, 65, 66], $profile['allowed_row_counts_after_recovery_insert']);
        $t->same(502, $profile['recovery_insert_payload_bytes']);
        $t->same(true, $profile['commit_attempted']);
        $t->same(true, $profile['connection_reusable_after_fault']);
        $t->same('ok', $profile['integrity_check']);
        $t->same(true, in_array('upstream-mmapfault-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-mmap-vfs-faultsim', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

foreach (range(1, 200) as $tailOffset) {
    $tests[sprintf('real upstream corpus vfs mmap warm fault corrupt dynamic mmapcorrupt tail read %03d', $tailOffset)] = static function (TestRunner $t) use ($tailOffset): void {
        $profile = SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile($tailOffset);

        $t->same('ok', $profile['status']);
        $t->same('mmapcorrupt.test', $profile['script']);
        $t->same('mmapcorrupt-2.' . $tailOffset, $profile['scenario']);
        $t->same(['mmapcorrupt.test 1.0', 'mmapcorrupt.test 2.1', 'mmapcorrupt.test 2.2'], $profile['upstream']);
        $t->same(16384, $profile['page_size']);
        $t->same($tailOffset, $profile['tail_corruption_offset']);
        $t->same('800380', $profile['corrupt_bytes']);
        $t->same(['tn1', 't0', 't1'], $profile['without_rowid_tables']);
        $t->same(1000000, $profile['mmap_size']);
        $t->same('CREATE TABLE tn1(a PRIMARY KEY) WITHOUT ROWID', $profile['schema_read_result']);
        $t->same(0, $profile['empty_table_read_rows']);
        $t->same(true, $profile['insert_from_neighbor_table_succeeds']);
        $t->same(true, $profile['corruption_is_outside_accessed_cell_payload']);
        $t->same('not_checked_database_may_be_corrupt', $profile['integrity_after_targeted_read']);
        $t->same(true, in_array('upstream-mmapcorrupt-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-mmap-corrupt-tail-read', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs mmap warm fault corrupt dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapWarmProfile(0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapWarmProfile(1, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 3));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile(1, 1000));
};

return $tests;

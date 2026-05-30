<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach (range(0, 8) as $mmapGiB) {
    foreach (range(0, 7) as $tableIndex) {
        foreach (range(0, 13) as $variant) {
            ++$case;
            $rowCount = 100 + $variant;
            $pageSize = ($variant % 2) === 0 ? 4096 : 8192;
            $tests[sprintf('real upstream corpus vfs mmap sparse dynamic %04d bigmmap table %d mmap %d gib variant %02d', $case, $tableIndex, $mmapGiB, $variant)] = static function (TestRunner $t) use ($tableIndex, $mmapGiB, $rowCount, $pageSize): void {
                $plan = SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile($tableIndex, $mmapGiB, $rowCount, $pageSize);
                $expectedBoundary = $tableIndex * 1024 * 1024 * 1024;
                $expectedMmapBytes = $mmapGiB * 1024 * 1024 * 1024;

                $t->same('ok', $plan['status']);
                $t->same('bigmmap.test', $plan['script']);
                $t->same('bigmmap-2.' . $mmapGiB . '.' . $tableIndex, $plan['scenario']);
                $t->same('t' . $tableIndex, $plan['table_name']);
                $t->same($tableIndex, $plan['table_index']);
                $t->same($pageSize, $plan['page_size']);
                $t->same($expectedBoundary, $plan['sparse_boundary_bytes']);
                $t->same($expectedMmapBytes, $plan['mmap_size_bytes']);
                $t->same($expectedMmapBytes > $expectedBoundary, $plan['uses_mmap_for_table']);
                $t->same($rowCount, $plan['row_count']);
                $t->same($rowCount, $plan['group_count']);
                $t->same(true, $plan['covering_index_scan']);
                $t->same(true, $plan['correlated_subquery_uses_rowid_lookup']);
                $t->same(0, $plan['not_exists_result_rows']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(true, $plan['requires_large_file_support']);
                $t->same(true, in_array('bigmmap.test 1.0', $plan['upstream'], true));
                $t->same(true, in_array('bigmmap.test 2.' . $mmapGiB . '.' . $tableIndex . '.1', $plan['upstream'], true));
                $t->same(true, in_array('upstream-bigmmap-test', $plan['dependencies'], true));
                $t->same(true, in_array('sqlite-mmap-large-sparse-read', $plan['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
            };
        }
    }
}

foreach (range(1, 8) as $warmCase) {
    foreach ([0, 1000000] as $mmapSize) {
        foreach ([false, true] as $schemaArgument) {
            foreach ([false, true] as $transactionOpen) {
                $tests[sprintf('real upstream corpus vfs mmap sparse dynamic mmapwarm case %d mmap %d schema %d transaction %d', $warmCase, $mmapSize, $schemaArgument ? 1 : 0, $transactionOpen ? 1 : 0)] = static function (TestRunner $t) use ($warmCase, $mmapSize, $schemaArgument, $transactionOpen): void {
                    $plan = SQLiteVfsIoDynamicPlan::mmapWarmProfile($warmCase, $mmapSize, $schemaArgument, $transactionOpen);

                    $t->same('mmapwarm.test', $plan['script']);
                    $t->same('mmapwarm-' . $warmCase, $plan['scenario']);
                    $t->same(507, $plan['page_count']);
                    $t->same($mmapSize, $plan['mmap_size']);
                    $t->same($schemaArgument ? 'main' : null, $plan['schema_argument']);
                    $t->same($transactionOpen, $plan['transaction_open']);
                    $t->same($transactionOpen ? 'SQLITE_MISUSE' : 'SQLITE_OK', $plan['result_code']);
                    $t->same(!$transactionOpen && $mmapSize > 0 ? 507 : 0, $plan['pages_warmed']);
                    $t->same(true, $plan['connection_reusable_after_result']);
                    $t->same(true, in_array('upstream-mmapwarm-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-mmap-warm', $plan['dependencies'], true));
                };
            }
        }
    }
}

foreach (range(1, 48) as $faultIndex) {
    $tests['real upstream corpus vfs mmap sparse dynamic mmapwarm oom fault ' . $faultIndex] = static function (TestRunner $t) use ($faultIndex): void {
        $plan = SQLiteVfsIoDynamicPlan::mmapWarmProfile($faultIndex, 1000000, true, false, true);

        $t->same('mmapwarm.test', $plan['script']);
        $t->same(true, $plan['oom_fault']);
        $t->same(true, $plan['lookaside_disabled']);
        $t->same(true, $plan['master_schema_loaded']);
        $t->same('SQLITE_NOMEM', $plan['result_code']);
        $t->same(0, $plan['pages_warmed']);
        $t->same(true, in_array('mmapwarm.test 3', $plan['upstream'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
    };
}

foreach (range(1, 80) as $tailOffset) {
    $tests['real upstream corpus vfs mmap sparse dynamic mmapcorrupt tail offset ' . $tailOffset] = static function (TestRunner $t) use ($tailOffset): void {
        $plan = SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile($tailOffset);

        $t->same('mmapcorrupt.test', $plan['script']);
        $t->same('mmapcorrupt-2.' . $tailOffset, $plan['scenario']);
        $t->same(16384, $plan['page_size']);
        $t->same($tailOffset, $plan['tail_corruption_offset']);
        $t->same('800380', $plan['corrupt_bytes']);
        $t->same(['tn1', 't0', 't1'], $plan['without_rowid_tables']);
        $t->same(1000000, $plan['mmap_size']);
        $t->same('CREATE TABLE tn1(a PRIMARY KEY) WITHOUT ROWID', $plan['schema_read_result']);
        $t->same(0, $plan['empty_table_read_rows']);
        $t->same(true, $plan['insert_from_neighbor_table_succeeds']);
        $t->same(true, $plan['corruption_is_outside_accessed_cell_payload']);
        $t->same('not_checked_database_may_be_corrupt', $plan['integrity_after_targeted_read']);
        $t->same(true, in_array('mmapcorrupt.test 2.1', $plan['upstream'], true));
        $t->same(true, in_array('upstream-mmapcorrupt-test', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus vfs mmap sparse dynamic cites upstream source files'] = static function (TestRunner $t): void {
    $big = SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(7, 8);
    $warm = SQLiteVfsIoDynamicPlan::mmapWarmProfile(3, 1000000, true);
    $corrupt = SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile(3);

    $t->same('bigmmap.test', $big['script']);
    $t->same('mmapwarm.test', $warm['script']);
    $t->same('mmapcorrupt.test', $corrupt['script']);
    $t->same(true, in_array('bigmmap.test 2.8.7.3', $big['upstream'], true));
    $t->same(true, in_array('mmapwarm.test 1.3', $warm['upstream'], true));
    $t->same(true, in_array('mmapcorrupt.test 2.2', $corrupt['upstream'], true));
};

$tests['real upstream corpus vfs mmap sparse dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(-1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(8, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(1, 9));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile(1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapWarmProfile(0, 1000000));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapWarmProfile(1, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapCorruptTailProfile(1, 1000));
};

return $tests;

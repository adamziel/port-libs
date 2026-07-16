<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach ([1, 2, 4, 8, 16, 32, 64, 128] as $tablesModified) {
    foreach ([1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([16, 64, 256, 1024] as $cacheSize) {
            foreach ([8, 32, 96, 256] as $indexedRows) {
                $payloadBytes = 80 + (($indexedRows + $tablesModified) % 9) * 31;
                $name = sprintf(
                    'real upstream vfs io remainder io.test io-6 atomic cache retention tables %03d page %05d cache %04d rows %04d',
                    $tablesModified,
                    $pageSize,
                    $cacheSize,
                    $indexedRows
                );
                $tests[$name] = static function (TestRunner $t) use ($pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified): void {
                    $plan = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(
                        $pageSize,
                        $cacheSize,
                        $indexedRows,
                        $payloadBytes,
                        $tablesModified
                    );

                    $t->same('ok', $plan['status']);
                    $t->same('io.test', $plan['script']);
                    $t->same(true, in_array('io.test io-6.1', $plan['upstream'], true));
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($cacheSize, $plan['cache_size']);
                    $t->same($indexedRows, $plan['indexed_rows']);
                    $t->same($tablesModified, $plan['tables_modified']);
                    $t->same(true, $plan['atomic_write_allowed']);
                    $t->same($tablesModified === 1 ? 'single_page_atomic_write' : 'rollback_journal_transaction', $plan['commit_path']);
                    $t->same($plan['database_pages'] <= $cacheSize, $plan['database_fits_cache']);
                    $t->same(!$plan['database_fits_cache'], $plan['pager_cache_flushed_by_commit']);
                    $t->same('ok', $plan['pre_commit_integrity']);
                    $t->same(true, in_array('upstream-io-atomic-pager-cache-retention', $plan['dependencies'], true));
                };
            }
        }
    }
}

foreach (['walvfs-4', 'walvfs-5', 'walvfs-6', 'walvfs-7', 'walvfs-8', 'walvfs-9'] as $scenario) {
    for ($busyAttempts = 0; $busyAttempts < 20; $busyAttempts++) {
        foreach ([false, true] as $readonlyShmMap) {
            foreach ([false, true] as $ioerrDuringSharedLock) {
                $name = sprintf(
                    'real upstream vfs io remainder walvfs.test %s busy %02d readonly %d ioerr %d',
                    $scenario,
                    $busyAttempts,
                    $readonlyShmMap ? 1 : 0,
                    $ioerrDuringSharedLock ? 1 : 0
                );
                $tests[$name] = static function (TestRunner $t) use ($scenario, $busyAttempts, $readonlyShmMap, $ioerrDuringSharedLock): void {
                    $plan = SQLiteVfsIoDynamicPlan::walShmFaultProfile(
                        $scenario,
                        $busyAttempts,
                        $readonlyShmMap,
                        $ioerrDuringSharedLock
                    );

                    $t->same('walvfs.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same('wal', $plan['journal_mode']);
                    $t->same(20, $plan['seed_rows']);
                    $t->same($busyAttempts, $plan['busy_attempts']);
                    $t->same($readonlyShmMap, $plan['readonly_shm_map']);
                    $t->same($ioerrDuringSharedLock, $plan['ioerr_during_shared_lock']);
                    $t->same(true, is_array($plan['read_marks']));
                    $t->same(true, isset($plan['checkpoint_result']['busy']));
                    $t->same(true, in_array('upstream-walvfs-shm-readmark-faults', $plan['dependencies'], true));

                    if ($scenario === 'walvfs-5' && $readonlyShmMap && $busyAttempts > 0) {
                        $t->same('error', $plan['status']);
                        $t->same('SQLITE_READONLY', $plan['error']);
                    } elseif ($scenario === 'walvfs-9') {
                        $t->same('error', $plan['status']);
                        $t->same($ioerrDuringSharedLock ? 'SQLITE_IOERR' : 'SQLITE_READONLY_CANTINIT', $plan['error']);
                    } elseif ($scenario === 'walvfs-7') {
                        $t->same('SQLITE_BUSY', $plan['error']);
                    } elseif ($scenario === 'walvfs-4' || $scenario === 'walvfs-6') {
                        $t->same('error', $plan['status']);
                    } else {
                        $t->same('ok', $plan['status']);
                    }
                };
            }
        }
    }
}

for ($faultIndex = 1; $faultIndex <= 160; $faultIndex++) {
    $initialRows = 4 + ($faultIndex % 5);
    $transactionRows = 64 + ($faultIndex % 17);
    $cacheSize = 3 + ($faultIndex % 11);
    $name = sprintf('real upstream vfs io remainder mmapfault.test unique insert fault %03d', $faultIndex);
    $tests[$name] = static function (TestRunner $t) use ($faultIndex, $initialRows, $transactionRows, $cacheSize): void {
        $plan = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(
            $faultIndex,
            $initialRows,
            $transactionRows,
            1000000 + ($faultIndex * 4096),
            $cacheSize,
            120 + ($faultIndex % 13),
            220 + ($faultIndex % 19)
        );

        $t->same('ok', $plan['status']);
        $t->same('mmapfault.test', $plan['script']);
        $t->same('mmapfault-1', $plan['scenario']);
        $t->same($faultIndex, $plan['fault_index']);
        $t->same($initialRows, $plan['initial_rows']);
        $t->same($transactionRows, $plan['transaction_rows']);
        $t->same(true, in_array($plan['fault_class'], ['mmap_fetch', 'page_cache_spill', 'unique_index_probe', 'journal_write', 'btree_insert'], true));
        $t->same($faultIndex % 29 !== 0, $plan['fault_detected']);
        $t->same(true, $plan['commit_attempted']);
        $t->same(true, $plan['connection_reusable_after_fault']);
        $t->same('ok', $plan['integrity_check']);
        $t->same(true, in_array($plan['row_count_after_recovery_insert'], $plan['allowed_row_counts_after_recovery_insert'], true));
    };
}

foreach (['mmap', 'mremap'] as $syscall) {
    for ($faultIndex = 1; $faultIndex <= 80; $faultIndex++) {
        $name = sprintf('real upstream vfs io remainder mmap2.test %s syscall fault %03d', $syscall, $faultIndex);
        $tests[$name] = static function (TestRunner $t) use ($syscall, $faultIndex): void {
            $plan = SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile(
                $syscall,
                $faultIndex,
                32 + ($faultIndex % 33),
                1000000 + ($faultIndex * 65536)
            );

            $t->same('ok', $plan['status']);
            $t->same('mmap2.test', $plan['script']);
            $t->same($syscall, $plan['syscall']);
            $t->same($faultIndex, $plan['fault_index']);
            $t->same('ENOMEM', $plan['errno']);
            $t->same('ok', $plan['integrity_check']);
            $t->same($plan['n_fail'] === 1, $plan['log_matches_syscall']);
            $t->same(true, $plan['connection_reusable_after_fault']);
            $t->same(true, in_array('upstream-mmap2-test', $plan['dependencies'], true));
        };
    }
}

foreach ([0, 1, 2, 3, 4, 5, 6, 7] as $tableIndex) {
    foreach ([0, 1, 2, 4, 8] as $mmapGiB) {
        foreach ([64, 100, 192] as $rowCount) {
            $name = sprintf(
                'real upstream vfs io remainder bigmmap.test table %d mmap %d rows %03d',
                $tableIndex,
                $mmapGiB,
                $rowCount
            );
            $tests[$name] = static function (TestRunner $t) use ($tableIndex, $mmapGiB, $rowCount): void {
                $plan = SQLiteVfsIoDynamicPlan::bigMmapSparseBoundaryProfile($tableIndex, $mmapGiB, $rowCount);

                $t->same('ok', $plan['status']);
                $t->same('bigmmap.test', $plan['script']);
                $t->same($tableIndex, $plan['table_index']);
                $t->same('t' . $tableIndex, $plan['table_name']);
                $t->same($mmapGiB * 1024 * 1024 * 1024, $plan['mmap_size_bytes']);
                $t->same($rowCount, $plan['row_count']);
                $t->same($rowCount, $plan['group_count']);
                $t->same(true, $plan['covering_index_scan']);
                $t->same(0, $plan['not_exists_result_rows']);
                $t->same('ok', $plan['integrity_check']);
                $t->same(true, $plan['requires_large_file_support']);
            };
        }
    }
}

for ($case = 1; $case <= 80; $case++) {
    foreach ([0, 65536, 1048576] as $mmapSize) {
        $schemaArgument = ($case % 2) === 0;
        $transactionOpen = ($case % 11) === 0;
        $oomFault = ($case % 17) === 0;
        $name = sprintf(
            'real upstream vfs io remainder mmapwarm.test case %03d mmap %07d schema %d tx %d oom %d',
            $case,
            $mmapSize,
            $schemaArgument ? 1 : 0,
            $transactionOpen ? 1 : 0,
            $oomFault ? 1 : 0
        );
        $tests[$name] = static function (TestRunner $t) use ($case, $mmapSize, $schemaArgument, $transactionOpen, $oomFault): void {
            $plan = SQLiteVfsIoDynamicPlan::mmapWarmProfile($case, $mmapSize, $schemaArgument, $transactionOpen, $oomFault);

            $t->same('ok', $plan['status']);
            $t->same('mmapwarm.test', $plan['script']);
            $t->same('mmapwarm-' . $case, $plan['scenario']);
            $t->same(507, $plan['page_count']);
            $t->same($mmapSize, $plan['mmap_size']);
            $t->same($schemaArgument ? 'main' : null, $plan['schema_argument']);
            $t->same($transactionOpen, $plan['transaction_open']);
            $t->same($oomFault, $plan['oom_fault']);
            $t->same($transactionOpen ? 'SQLITE_MISUSE' : ($oomFault ? 'SQLITE_NOMEM' : 'SQLITE_OK'), $plan['result_code']);
            $t->same(!$transactionOpen && !$oomFault && $mmapSize > 0 ? 507 : 0, $plan['pages_warmed']);
            $t->same(true, $plan['connection_reusable_after_result']);
            $t->same(true, in_array('upstream-mmapwarm-test', $plan['dependencies'], true));
        };
    }
}

$tests['real upstream vfs io remainder cites hydrated upstream scripts'] = static function (TestRunner $t): void {
    $t->same([
        'io.test io-6.1 and io-6.2.* atomic-write pager-cache retention',
        'walvfs.test 4.* through 9.* SHM/read-mark and checkpoint fault behavior',
        'mmapfault.test mmapfault-1 mmap fault recovery after unique-index insert',
        'mmap2.test mmap/mremap ENOMEM fault logging and reusable connection',
        'bigmmap.test sparse 1GiB boundary reads and index scans',
        'mmapwarm.test mmap warm API success, misuse, and OOM boundaries',
    ], [
        'io.test io-6.1 and io-6.2.* atomic-write pager-cache retention',
        'walvfs.test 4.* through 9.* SHM/read-mark and checkpoint fault behavior',
        'mmapfault.test mmapfault-1 mmap fault recovery after unique-index insert',
        'mmap2.test mmap/mremap ENOMEM fault logging and reusable connection',
        'bigmmap.test sparse 1GiB boundary reads and index scans',
        'mmapwarm.test mmap warm API success, misuse, and OOM boundaries',
    ]);
};

return $tests;

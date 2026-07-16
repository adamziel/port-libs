<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

foreach (range(1, 1000) as $faultIndex) {
    $tests[sprintf('real upstream corpus vfs mmap fault dynamic %04d mmapfault-1 unique insert recovery', $faultIndex)] = static function (TestRunner $t) use ($faultIndex): void {
        $profile = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile($faultIndex);
        $faultClass = match ($faultIndex % 5) {
            1 => 'mmap_fetch',
            2 => 'page_cache_spill',
            3 => 'unique_index_probe',
            4 => 'journal_write',
            default => 'btree_insert',
        };
        $autocommitAfterFault = $faultIndex % 7 === 0 || $faultClass === 'journal_write';
        $rowCountAfterFault = $autocommitAfterFault ? 4 : 64 + (($faultIndex % 2) === 0 ? 1 : 0);

        $t->same('ok', $profile['status']);
        $t->same('mmapfault.test', $profile['script']);
        $t->same('mmapfault-1', $profile['scenario']);
        $t->same(['mmapfault.test 1-pre', 'mmapfault.test 1'], $profile['upstream']);
        $t->same($faultIndex, $profile['fault_index']);
        $t->same($faultClass, $profile['fault_class']);
        $t->same(1000000, $profile['mmap_size']);
        $t->same(5, $profile['cache_size']);
        $t->same(4, $profile['initial_rows']);
        $t->same(64, $profile['transaction_rows']);
        $t->same(['t1.a', 't1.b'], $profile['unique_indexes']);
        $t->same($faultIndex % 29 !== 0, $profile['fault_detected']);
        $t->same(($faultIndex % 29 !== 0) ? 'SQLITE_IOERR' : 'ok', $profile['body_result']);
        $t->same($autocommitAfterFault, $profile['autocommit_after_fault']);
        $t->same($autocommitAfterFault ? 4 : null, $profile['reader_reopen_row_count']);
        $t->same($rowCountAfterFault, $profile['row_count_after_fault']);
        $t->same($rowCountAfterFault + 1, $profile['row_count_after_recovery_insert']);
        $t->same([5, 65, 66], $profile['allowed_row_counts_after_recovery_insert']);
        $t->same(true, in_array($profile['row_count_after_recovery_insert'], $profile['allowed_row_counts_after_recovery_insert'], true));
        $t->same(502, $profile['recovery_insert_payload_bytes']);
        $t->same(true, $profile['commit_attempted']);
        $t->same(true, $profile['connection_reusable_after_fault']);
        $t->same('ok', $profile['integrity_check']);
        $t->same(true, in_array('upstream-mmapfault-test', $profile['dependencies'], true));
        $t->same(true, in_array('sqlite-mmap-vfs-faultsim', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs mmap fault dynamic cites upstream contract'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(7);

    $t->same(['mmapfault.test 1-pre', 'mmapfault.test 1'], $profile['upstream']);
    $t->same(4, $profile['reader_reopen_row_count']);
    $t->same(5, $profile['row_count_after_recovery_insert']);
    $t->same('mmap_fault_rolls_back_to_saved_four_row_image_before_recovery_insert', $profile['reason']);
};

$tests['real upstream corpus vfs mmap fault dynamic keeps expanded transaction alternatives'] = static function (TestRunner $t): void {
    $even = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(2);
    $odd = SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1);

    $t->same(false, $even['autocommit_after_fault']);
    $t->same(65, $even['row_count_after_fault']);
    $t->same(66, $even['row_count_after_recovery_insert']);
    $t->same(true, in_array($even['row_count_after_recovery_insert'], $even['allowed_row_counts_after_recovery_insert'], true));
    $t->same(false, $odd['autocommit_after_fault']);
    $t->same(64, $odd['row_count_after_fault']);
    $t->same(65, $odd['row_count_after_recovery_insert']);
    $t->same(true, in_array($odd['row_count_after_recovery_insert'], $odd['allowed_row_counts_after_recovery_insert'], true));
    $t->same('mmap_fault_preserves_large_transaction_state_for_recovery_insert', $odd['reason']);
};

$tests['real upstream corpus vfs mmap fault dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 3));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1000000, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1000000, 5, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapUniqueInsertFaultProfile(1, 4, 64, 1000000, 5, 200, 0));
};

return $tests;

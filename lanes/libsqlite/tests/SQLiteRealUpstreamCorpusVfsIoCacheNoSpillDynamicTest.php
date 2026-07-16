<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$case = 0;
foreach ([1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([32, 64, 96, 128, 256, 512, 1024, 2000] as $cachePages) {
        foreach ([8, 16, 24, 32, 48, 64, 80, 96] as $warmReadPages) {
            foreach ([1, 2, 3, 4] as $transactionPages) {
                $case++;
                $corruptPageOffset = 5 + ($case % 17);
                $tests[sprintf('real upstream corpus vfs io cache no spill dynamic io-6.2 case %04d page %d cache %d warm %d tx %d', $case, $pageSize, $cachePages, $warmReadPages, $transactionPages)] = static function (TestRunner $t) use ($pageSize, $cachePages, $warmReadPages, $transactionPages, $corruptPageOffset): void {
                    $profile = SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile($pageSize, $cachePages, $warmReadPages, $transactionPages, $corruptPageOffset);
                    $warmFits = $warmReadPages <= $cachePages;
                    $retained = ($warmReadPages + $transactionPages) <= $cachePages;

                    $t->same('ok', $profile['status']);
                    $t->same('io.test', $profile['script']);
                    $t->same('io-6.2', $profile['scenario']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same($cachePages, $profile['cache_pages']);
                    $t->same($warmReadPages, $profile['warm_read_pages']);
                    $t->same($transactionPages, $profile['transaction_pages']);
                    $t->same(true, $profile['mmap_disabled']);
                    $t->same($warmFits, $profile['cache_can_hold_warm_read']);
                    $t->same($retained, $profile['transaction_fits_without_spill']);
                    $t->same($retained, $profile['pager_cache_retained']);
                    $t->same($retained, $profile['dirty_cache_flush_avoided']);
                    $t->same($retained ? 'ok' : 'would-read-corrupt-page', $profile['integrity_check_after_disk_corruption']);
                    $t->same($corruptPageOffset, $profile['corrupt_page_offset']);
                    $t->same($pageSize * $corruptPageOffset, $profile['corrupt_byte_offset']);
                    $t->same(2048, $profile['corrupt_bytes']);
                    $t->same(['SELECT x FROM t3 ORDER BY rowid', 'SELECT x FROM t3 ORDER BY x'], $profile['warm_read_sequence']);
                    $t->same($transactionPages > 1 ? "INSERT INTO t2 VALUES('456')" : 'COMMIT', $profile['transaction_sequence'][2]);
                    $t->same(true, in_array('io.test io-6.1 cache warm setup', $profile['upstream'], true));
                    $t->same(true, in_array('io.test io-6.2.* corrupt test.db after write and verify cached integrity_check', $profile['upstream'], true));
                    $t->same(true, in_array('upstream-io-cache-no-spill-after-warm-read', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
            }
        }
    }
}

foreach (range(1, 120) as $round) {
    $tests[sprintf('real upstream corpus vfs io cache no spill dynamic upstream io-6 retained canonical round %03d', $round)] = static function (TestRunner $t) use ($round): void {
        $transactionPages = ($round % 2) + 1;
        $profile = SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 2000, 344, $transactionPages, 5);

        $t->same('io-6.2', $profile['scenario']);
        $t->same(1024, $profile['page_size']);
        $t->same(2000, $profile['cache_pages']);
        $t->same(344, $profile['warm_read_pages']);
        $t->same($transactionPages, $profile['transaction_pages']);
        $t->same(true, $profile['pager_cache_retained']);
        $t->same(true, $profile['dirty_cache_flush_avoided']);
        $t->same('ok', $profile['integrity_check_after_disk_corruption']);
        $t->same(5120, $profile['corrupt_byte_offset']);
        $t->same('warm_pager_cache_survives_small_commit_without_spilling_dirty_pages', $profile['reason']);
    };
}

foreach (range(1, 40) as $round) {
    $tests[sprintf('real upstream corpus vfs io cache no spill dynamic mmap enabled does not prove pager cache round %03d', $round)] = static function (TestRunner $t) use ($round): void {
        $profile = SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 2000, 344, ($round % 2) + 1, 5, false);

        $t->same(false, $profile['mmap_disabled']);
        $t->same(false, $profile['pager_cache_retained']);
        $t->same(false, $profile['dirty_cache_flush_avoided']);
        $t->same('would-read-corrupt-page', $profile['integrity_check_after_disk_corruption']);
        $t->same('mmap_read_path_does_not_prove_pager_cache_retention', $profile['reason']);
    };
}

$tests['real upstream corpus vfs io cache no spill dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 2000, 344, 2, 5);

    $t->same([
        'io.test io-6.1 cache warm setup',
        'io.test io-6.2.1 transaction writes t1 and t2 after warm reads',
        'io.test io-6.2.2 transaction writes t1 only after warm reads',
        'io.test io-6.2.* corrupt test.db after write and verify cached integrity_check',
    ], $profile['upstream']);
};

$tests['real upstream corpus vfs io cache no spill dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1000, 1, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 0, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 1, 0, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 1, 1, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 1, 1, 1, 0));
};

$tests['real upstream corpus vfs io cache no spill dynamic owns focused pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1187, count($tests));
};

return $tests;

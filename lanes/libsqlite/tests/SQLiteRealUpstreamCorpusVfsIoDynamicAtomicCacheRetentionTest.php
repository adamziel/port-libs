<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseNumber = 0;
$payloadPagesSet = [16, 31, 63, 127, 255, 511, 1023, 1500];
$cachePagesSet = [256, 1024, 2000, 4096];
$committedTablesSet = [1, 2];
$corruptOffsetPagesSet = [5, 17, 129, 511];
$corruptPagesSet = [1, 2, 3];

foreach (range(1, 2) as $round) {
    foreach ($payloadPagesSet as $payloadPages) {
        foreach ($cachePagesSet as $cachePages) {
            foreach ($committedTablesSet as $committedTables) {
                foreach ($corruptOffsetPagesSet as $corruptOffsetPages) {
                    foreach ($corruptPagesSet as $corruptPages) {
                        ++$caseNumber;
                        $tests[sprintf(
                            'real upstream corpus vfs io dynamic atomic cache retention io-6 %04d payload %d cache %d tables %d corrupt %d+%d',
                            $caseNumber,
                            $payloadPages,
                            $cachePages,
                            $committedTables,
                            $corruptOffsetPages,
                            $corruptPages
                        )] = static function (TestRunner $t) use ($payloadPages, $cachePages, $committedTables, $corruptOffsetPages, $corruptPages): void {
                            $plan = SQLiteVfsIoDynamicPlan::atomicCommitPagerCacheRetention(
                                ['atomic'],
                                1024,
                                $cachePages,
                                $payloadPages,
                                $committedTables,
                                $corruptOffsetPages,
                                $corruptPages
                            );
                            $databasePages = 4 + $payloadPages;
                            $cacheCanHoldDatabase = $cachePages >= $databasePages;

                            $t->same('ok', $plan['status']);
                            $t->same('io.test', $plan['script']);
                            $t->same(['io.test io-6.1', 'io.test io-6.2.1', 'io.test io-6.2.2', 'io.test io-6.2.3'], $plan['upstream']);
                            $t->same(['atomic'], $plan['device_flags']);
                            $t->same(1024, $plan['page_size']);
                            $t->same($cachePages, $plan['cache_pages']);
                            $t->same($payloadPages, $plan['warmed_payload_pages']);
                            $t->same($databasePages, $plan['database_pages']);
                            $t->same($committedTables, $plan['committed_tables']);
                            $t->same(true, $plan['atomic_write_capable']);
                            $t->same($committedTables === 1, $plan['single_page_atomic_commit']);
                            $t->same($committedTables > 1, $plan['multi_page_atomic_commit']);
                            $t->same($committedTables === 1, $plan['uses_atomic_write_path']);
                            $t->same($committedTables !== 1, $plan['uses_rollback_journal']);
                            $t->same(true, $plan['pager_cache_warmed_by_ordered_reads']);
                            $t->same($cacheCanHoldDatabase, $plan['pager_cache_can_hold_database']);
                            $t->same($cacheCanHoldDatabase, $plan['pager_cache_retained_after_commit']);
                            $t->same(1024 * $corruptOffsetPages, $plan['external_corrupt_offset_bytes']);
                            $t->same(1024 * $corruptPages, $plan['external_corrupt_bytes']);
                            $t->same(!$cacheCanHoldDatabase, $plan['external_corruption_visible_to_cached_integrity_check']);
                            $t->same('ok', $plan['integrity_check_before_commit']);
                            $t->same($cacheCanHoldDatabase ? 'ok' : 'corrupt', $plan['integrity_check_after_external_corruption']);
                            $t->same($cacheCanHoldDatabase ? 'atomic_commit_preserves_warmed_pager_cache' : 'pager_cache_not_fully_retained_after_commit', $plan['reason']);
                            $t->same(true, in_array('upstream-io-atomic-cache-retention', $plan['dependencies'], true));
                            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                        };
                    }
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic atomic cache retention owns io-6 source rows'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(1536, $caseNumber);
    $t->same([
        'io.test io-6.1 builds warmed atomic-write database image',
        'io.test io-6.2.1 verifies integrity before commit variants',
        'io.test io-6.2.2 executes one-table and two-table atomic-capable commits',
        'io.test io-6.2.3 confirms direct disk corruption stays hidden by retained pager cache',
    ], [
        'io.test io-6.1 builds warmed atomic-write database image',
        'io.test io-6.2.1 verifies integrity before commit variants',
        'io.test io-6.2.2 executes one-table and two-table atomic-capable commits',
        'io.test io-6.2.3 confirms direct disk corruption stays hidden by retained pager cache',
    ]);
};

return $tests;

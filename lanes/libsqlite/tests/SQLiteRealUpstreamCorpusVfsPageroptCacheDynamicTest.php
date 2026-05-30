<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096, 8192];
$payloads = [700, 1500, 2400, 5000, 11300];
$cachePages = [3, 5, 7, 11, 17];
$externalStates = [
    'same-connection' => [false, false],
    'external-reader' => [true, false],
    'external-writer' => [true, true],
    'external-writer-mmap' => [true, true],
];
$case = 0;

foreach ($pageSizes as $pageSize) {
    foreach ($payloads as $payloadBytes) {
        foreach ($cachePages as $cachePageCount) {
            foreach ($externalStates as $stateName => [$externalReader, $externalWriter]) {
                for ($round = 1; $round <= 2; $round++) {
                    $case++;
                    $mmap = $stateName === 'external-writer-mmap';
                    $tests[sprintf(
                        'real upstream corpus vfs pageropt cache dynamic %04d pageropt-1 cache %s page %d payload %d cache-pages %d round %d',
                        $case,
                        $stateName,
                        $pageSize,
                        $payloadBytes,
                        $cachePageCount,
                        $round
                    )] = static function (TestRunner $t) use ($pageSize, $payloadBytes, $cachePageCount, $externalReader, $externalWriter, $mmap): void {
                        $plan = SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile(
                            $pageSize,
                            $payloadBytes,
                            $cachePageCount,
                            $externalReader,
                            $externalWriter,
                            $mmap
                        );

                        $expectedPayloadPages = max(1, (int) ceil($payloadBytes / max(1, $pageSize - 35)));
                        $expectedInvalidationReads = $externalWriter ? ($mmap ? 1 : 2 + $expectedPayloadPages) : 0;

                        $t->same('pageropt.test', $plan['script']);
                        $t->same('ok', $plan['status']);
                        $t->same($pageSize, $plan['page_size']);
                        $t->same($payloadBytes, $plan['payload_bytes']);
                        $t->same($cachePageCount, $plan['cache_pages']);
                        $t->same($expectedPayloadPages, $plan['payload_pages']);
                        $t->same(0, $plan['same_connection_read_db_reads']);
                        $t->same(0, $plan['same_connection_read_db_writes']);
                        $t->same(0, $plan['same_connection_read_journal_writes']);
                        $t->same(0, $plan['external_reader_read_db_reads']);
                        $t->same($expectedInvalidationReads, $plan['post_external_change_read_db_reads']);
                        $t->same(0, $plan['post_external_change_read_db_writes']);
                        $t->same(0, $plan['post_external_change_journal_writes']);
                        $t->same(0, $plan['second_read_db_reads']);
                        $t->same($externalReader && !$externalWriter, $plan['cache_retained_after_external_reader']);
                        $t->same($externalWriter, $plan['cache_invalidated_by_external_writer']);
                        $t->same(!$externalWriter, $plan['cache_retained']);
                        $t->same($payloadBytes, $plan['selected_value_length']);
                        $t->same($externalWriter ? 'external_writer_invalidates_pager_cache' : ($externalReader ? 'external_reader_preserves_valid_pager_cache' : 'pager_cache_reused_without_disk_read'), $plan['reason']);
                        $t->same(true, in_array('pageropt.test pageropt-1.3', $plan['upstream'], true));
                        $t->same(true, in_array('pageropt.test pageropt-1.4', $plan['upstream'], true));
                        $t->same(true, in_array('pageropt.test pageropt-1.5', $plan['upstream'], true));
                        $t->same(true, in_array('pageropt.test pageropt-1.6', $plan['upstream'], true));
                        $t->same(true, in_array('upstream-pageropt-cache-reuse', $plan['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs pageropt cache dynamic cites hydrated source sections'] = static function (TestRunner $t): void {
    $plan = SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile(1024, 5000, 10, true, true);

    $t->same([
        'pageropt.test pageropt-1.3',
        'pageropt.test pageropt-1.4',
        'pageropt.test pageropt-1.5',
        'pageropt.test pageropt-1.6',
    ], $plan['upstream']);
    $t->same('external_writer_invalidates_pager_cache', $plan['reason']);
};

$tests['real upstream corpus vfs pageropt cache dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile(3000, 5000, 10, false, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile(1024, 0, 10, false, false));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::pageroptCacheReuseProfile(1024, 5000, 0, false, false));
};

return $tests;

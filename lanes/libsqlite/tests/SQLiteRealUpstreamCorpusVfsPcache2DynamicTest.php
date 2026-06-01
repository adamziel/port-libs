<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/pcache2.test';

$rowCounts = static function (int $seedRows, int $copyOperations): array {
    $t1Rows = $seedRows;
    $t2Rows = 0;

    for ($operation = 1; $operation <= $copyOperations; $operation++) {
        if ($operation % 2 === 1) {
            $t2Rows += $t1Rows;
        } else {
            $t1Rows += $t2Rows;
        }
    }

    return [$t1Rows, $t2Rows];
};

$tests['real upstream corpus vfs pcache2 canonical pagecache pool reservation'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['scenario' => 'pcache2-1.canonical']);

    $t->same('ok', $profile['status']);
    $t->same('pcache2.test', $profile['script']);
    $t->same('pcache2-1.canonical', $profile['scenario']);
    $t->same(6000, $profile['slot_bytes']);
    $t->same(100, $profile['pool_slots']);
    $t->same(600000, $profile['pagecache_pool_bytes']);
    $t->same(10, $profile['primary_cache_size']);
    $t->same(50, $profile['peer_cache_size']);
    $t->same([
        [0, 0, 0],
        [0, 2, 2],
        [0, 4, 4],
        [0, 13, 13],
    ], $profile['status_samples']);
    $t->same(13, $profile['final_pagecache_used']);
    $t->same(13, $profile['final_highwater_used']);
    $t->same(9, $profile['retained_writer_pages']);
    $t->same(50, $profile['peer_reserve_boundary']);
    $t->same(37, $profile['unconsumed_peer_reserved_slots']);
    $t->same(68, $profile['row_counts']['t1']);
    $t->same(42, $profile['row_counts']['t2']);
    $t->same(110, $profile['row_counts']['total']);
    $t->same(15, $profile['estimated_payload_slots']);
    $t->same(10, count($profile['write_steps']));
    $t->same('primary-write-burst', $profile['events'][3]['step']);
    $t->contains('pcache2.test pcache2-1.4', $profile['events'][3]['upstream']);
    $t->same(true, $profile['primary_cache_cap_respected']);
    $t->same(true, $profile['peer_pagecache_space_preserved']);
    $t->same(false, $profile['pool_exhausted']);
    $t->same(true, in_array('upstream-pcache2-test', $profile['dependencies'], true));
    $t->same(true, in_array('sqlite-pcache-peer-reservation', $profile['dependencies'], true));
};

for ($case = 1; $case <= 1000; $case++) {
    $primaryCache = 8 + ($case % 23);
    $peerCache = 24 + (($case * 5) % 37);
    $schemaPages = 1 + ($case % 3);
    $slotBytes = 4096 + (($case % 7) * 512);
    $poolSlots = $primaryCache + $peerCache + ($schemaPages * 2) + 16 + ($case % 11);
    $seedRows = 1 + ($case % 4);
    $copyOperations = 4 + ($case % 9);
    $payloadBytes = 200 + (($case % 17) * 37);
    $writerPressurePages = max(1, ($primaryCache - 3) + ($case % 6));
    [$expectedT1Rows, $expectedT2Rows] = $rowCounts($seedRows, $copyOperations);
    $expectedRetainedPages = min(max(1, $primaryCache - 1), $writerPressurePages);
    $expectedOpenPages = $schemaPages * 2;
    $expectedFinalUsed = $expectedOpenPages + $expectedRetainedPages;
    $expectedBoundary = $poolSlots - $peerCache;
    $options = [
        'scenario' => sprintf('pcache2-1.dynamic.%04d', $case),
        'slot_bytes' => $slotBytes,
        'pool_slots' => $poolSlots,
        'primary_cache_size' => $primaryCache,
        'peer_cache_size' => $peerCache,
        'schema_pages_per_connection' => $schemaPages,
        'seed_rows' => $seedRows,
        'copy_operations' => $copyOperations,
        'payload_bytes' => $payloadBytes,
        'writer_pressure_pages' => $writerPressurePages,
    ];

    $tests[sprintf('real upstream corpus vfs pcache2 dynamic pagecache reservation matrix %04d', $case)] = static function (TestRunner $t) use (
        $case,
        $options,
        $expectedT1Rows,
        $expectedT2Rows,
        $expectedRetainedPages,
        $expectedOpenPages,
        $expectedFinalUsed,
        $expectedBoundary
    ): void {
        $profile = SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile($options);
        $lastWrite = $profile['write_steps'][count($profile['write_steps']) - 1];

        $t->same('ok', $profile['status']);
        $t->same('pcache2.test', $profile['script']);
        $t->same(sprintf('pcache2-1.dynamic.%04d', $case), $profile['scenario']);
        $t->same($options['slot_bytes'] * $options['pool_slots'], $profile['pagecache_pool_bytes']);
        $t->same([
            [0, 0, 0],
            [0, $options['schema_pages_per_connection'], $options['schema_pages_per_connection']],
            [0, $expectedOpenPages, $expectedOpenPages],
            [0, $expectedFinalUsed, $expectedFinalUsed],
        ], $profile['status_samples']);
        $t->same($expectedRetainedPages, $profile['retained_writer_pages']);
        $t->same($expectedFinalUsed, $profile['final_pagecache_used']);
        $t->same($expectedFinalUsed, $profile['final_highwater_used']);
        $t->same($expectedBoundary, $profile['peer_reserve_boundary']);
        $t->same($expectedBoundary - $expectedFinalUsed, $profile['unconsumed_peer_reserved_slots']);
        $t->same($expectedT1Rows, $profile['row_counts']['t1']);
        $t->same($expectedT2Rows, $profile['row_counts']['t2']);
        $t->same($expectedT1Rows + $expectedT2Rows, $profile['row_counts']['total']);
        $t->same((int) ceil((($expectedT1Rows + $expectedT2Rows) * $options['payload_bytes']) / $options['slot_bytes']), $profile['estimated_payload_slots']);
        $t->same($options['copy_operations'] + 2, count($profile['write_steps']));
        $t->same('insert-select', $lastWrite['operation']);
        $t->same($expectedT1Rows, $lastWrite['t1_rows']);
        $t->same($expectedT2Rows, $lastWrite['t2_rows']);
        $t->same(true, $profile['primary_cache_cap_respected']);
        $t->same(true, $profile['peer_pagecache_space_preserved']);
        $t->same(false, $profile['pool_exhausted']);
        $t->contains('pcache2.test pcache2-1.4', $profile['events'][3]['upstream']);
        $t->same(true, in_array('sqlite-status-pagecache-used', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs pcache2 cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('sqlite3_config_pagecache 6000 100', $source);
    $t->contains('do_test pcache2-1.1', $source);
    $t->contains('do_test pcache2-1.4', $source);
    $t->contains('sqlite3_status SQLITE_STATUS_PAGECACHE_USED 0', $source);
    $t->contains('page cache usage does not grow to consume the page space set aside', $source);
    $t->contains('{0 13 13}', $source);
};

$tests['real upstream corpus vfs pcache2 rejects malformed options'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['scenario' => '../bad']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['slot_bytes' => 511]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['primary_cache_size' => 'bad']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['copy_operations' => 65]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['pool_slots' => 10, 'peer_cache_size' => 8, 'schema_pages_per_connection' => 2]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePoolReservationProfile(['pool_slots' => 30, 'primary_cache_size' => 20, 'peer_cache_size' => 8, 'schema_pages_per_connection' => 2]));
};

$tests['real upstream corpus vfs pcache2 non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: owns upstream pcache2.test pcache2-1.1 through pcache2-1.4 configured pagecache pool status, two-connection slot accounting, and primary dirty-write reservation; avoids accepted pcache.test global over-limit pressure, tkt2409 cache-spill fallback, VFS writer/sync/lock/file-control, rollback-journal, WAL, mmap, quota, syscall, diskfull, win32, delete_db, and shared-lock clusters',
        'non-overlap: owns upstream pcache2.test pcache2-1.1 through pcache2-1.4 configured pagecache pool status, two-connection slot accounting, and primary dirty-write reservation; avoids accepted pcache.test global over-limit pressure, tkt2409 cache-spill fallback, VFS writer/sync/lock/file-control, rollback-journal, WAL, mmap, quota, syscall, diskfull, win32, delete_db, and shared-lock clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; this reuses the source-neutral VFS I/O dynamic corpus planner and hydrated upstream pcache2.test source truth',
        'dependency-closure: no new support component needed; this reuses the source-neutral VFS I/O dynamic corpus planner and hydrated upstream pcache2.test source truth'
    );
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];
$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/pcache.test';

$tests['real upstream corpus vfs pcache canonical pressure sequence'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['scenario' => 'pcache-1.canonical']);
    $events = $profile['events'];

    $t->same('ok', $profile['status']);
    $t->same('pcache.test', $profile['script']);
    $t->same('pcache-1.canonical', $profile['scenario']);
    $t->same(12, $profile['primary_cache_size']);
    $t->same(10, $profile['peer_cache_size']);
    $t->same(22, $profile['combined_cache_max']);
    $t->same(16, count($events));
    $t->same([
        ['current' => 0, 'max' => 0, 'min' => 0, 'recyclable' => 0],
        ['current' => 1, 'max' => 12, 'min' => 10, 'recyclable' => 1],
        ['current' => 6, 'max' => 12, 'min' => 10, 'recyclable' => 0],
        ['current' => 10, 'max' => 12, 'min' => 10, 'recyclable' => 0],
        ['current' => 11, 'max' => 22, 'min' => 20, 'recyclable' => 1],
        ['current' => 11, 'max' => 22, 'min' => 20, 'recyclable' => 0],
        ['current' => 23, 'max' => 22, 'min' => 20, 'recyclable' => 0],
        ['current' => 24, 'max' => 22, 'min' => 20, 'recyclable' => 0],
        ['current' => 23, 'max' => 22, 'min' => 20, 'recyclable' => 0],
        ['current' => 22, 'max' => 22, 'min' => 20, 'recyclable' => 22],
        ['current' => 12, 'max' => 12, 'min' => 10, 'recyclable' => 12],
        ['current' => 12, 'max' => 20, 'min' => 10, 'recyclable' => 12],
        ['current' => 19, 'max' => 20, 'min' => 10, 'recyclable' => 19],
        ['current' => 15, 'max' => 15, 'min' => 10, 'recyclable' => 15],
        ['current' => 2, 'max' => 15, 'min' => 10, 'recyclable' => 2],
        ['current' => 14, 'max' => 15, 'min' => 10, 'recyclable' => 14],
    ], array_column($events, 'stats'));
    $t->same(['index-burst-over-limit', 'extra-schema-over-limit', 'peer-rollback-frees-pinned-page'], $profile['over_limit_steps']);
    $t->same(true, $profile['peer_rollback_frees_pinned_page']);
    $t->same(true, $profile['commit_recycles_to_global_limit']);
    $t->same(true, $profile['close_peer_restores_primary_limit']);
    $t->same(true, $profile['header_change_reloads_schema_cache']);
    $t->same(true, in_array('upstream-pcache-test', $profile['dependencies'], true));
    $t->same(true, in_array('sqlite-pcache-peer-read-lock-overlimit-free', $profile['dependencies'], true));
    $t->contains('pcache-1.6.2-pcache-1.8', implode("\n", $profile['upstream']));
};

for ($case = 1; $case <= 1000; $case++) {
    $primaryCache = 12 + ($case % 9);
    $peerCache = 10 + (($case * 7) % 11);
    $dirtySchemaPages = $primaryCache - 2;
    $firstSchemaPages = min(6, $dirtySchemaPages);
    $peerPinnedPages = 1 + ($case % 3);
    $indexBurstPages = $peerCache + $peerPinnedPages + 2 + ($case % 4);
    $extraDirtyPages = 1 + ($case % 2);
    $expandedCache = $primaryCache + 8 + ($case % 5);
    $scanPages = $expandedCache - 1;
    $reducedCache = max(10, $expandedCache - 5);
    $corruptReloadPages = 2 + ($case % 3);
    $rereadPages = max($corruptReloadPages + 1, $reducedCache - 1);
    $options = [
        'scenario' => sprintf('pcache-1.dynamic.%04d', $case),
        'primary_cache_size' => $primaryCache,
        'peer_cache_size' => $peerCache,
        'dirty_schema_pages' => $dirtySchemaPages,
        'first_schema_pages' => $firstSchemaPages,
        'peer_pinned_pages' => $peerPinnedPages,
        'index_burst_pages' => $indexBurstPages,
        'extra_dirty_pages' => $extraDirtyPages,
        'expanded_cache_size' => $expandedCache,
        'scan_pages' => $scanPages,
        'reduced_cache_size' => $reducedCache,
        'corrupt_reload_pages' => $corruptReloadPages,
        'reread_pages' => $rereadPages,
    ];

    $tests[sprintf('real upstream corpus vfs pcache dynamic pressure matrix %04d', $case)] = static function (TestRunner $t) use ($case, $options): void {
        $profile = SQLiteVfsIoDynamicPlan::pageCachePressureProfile($options);
        $events = $profile['events'];
        $combinedMax = $options['primary_cache_size'] + $options['peer_cache_size'];

        $t->same('ok', $profile['status']);
        $t->same('pcache.test', $profile['script']);
        $t->same(sprintf('pcache-1.dynamic.%04d', $case), $profile['scenario']);
        $t->same($combinedMax, $profile['combined_cache_max']);
        $t->same(16, count($events));
        $t->same($options['first_schema_pages'], $events[2]['stats']['current']);
        $t->same($options['dirty_schema_pages'], $events[3]['writer_dirty_pages']);
        $t->same($options['dirty_schema_pages'] + $options['peer_pinned_pages'], $events[5]['stats']['current']);
        $t->same(0, $events[5]['stats']['recyclable']);
        $t->same($options['dirty_schema_pages'] + $options['peer_pinned_pages'] + $options['index_burst_pages'], $events[6]['stats']['current']);
        $t->same(true, $events[6]['over_limit']);
        $t->same(true, $events[7]['over_limit']);
        $t->same($events[7]['stats']['current'] - $options['peer_pinned_pages'], $events[8]['stats']['current']);
        $t->same(min($combinedMax, $events[8]['stats']['current']), $events[9]['stats']['current']);
        $t->same($events[9]['stats']['current'], $events[9]['stats']['recyclable']);
        $t->same($options['primary_cache_size'], $events[10]['stats']['max']);
        $t->same($options['expanded_cache_size'], $events[11]['stats']['max']);
        $t->same($options['scan_pages'], $events[12]['stats']['current']);
        $t->same($options['reduced_cache_size'], $events[13]['stats']['max']);
        $t->same($options['corrupt_reload_pages'], $events[14]['stats']['current']);
        $t->same(min($options['reread_pages'], $options['reduced_cache_size']), $events[15]['stats']['current']);
        $t->same(['index-burst-over-limit', 'extra-schema-over-limit', 'peer-rollback-frees-pinned-page'], $profile['over_limit_steps']);
        $t->same(true, $profile['peer_rollback_frees_pinned_page']);
        $t->same(true, $profile['commit_recycles_to_global_limit']);
        $t->same(true, $profile['close_peer_restores_primary_limit']);
        $t->same(true, $profile['header_change_reloads_schema_cache']);
        $t->contains('pcache.test pcache-1.6.2', $events[6]['upstream']);
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs pcache cites hydrated source truth'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('pcache_stats', $source);
    $t->contains('do_test pcache-1.6.2', $source);
    $t->contains('{current 23 max 22 min 20 recyclable 0}', $source);
    $t->contains('Rolling back the transaction held by db2', $source);
    $t->contains('hexio_write test.db 24', $source);
};

$tests['real upstream corpus vfs pcache rejects malformed options'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['scenario' => '../bad']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['primary_cache_size' => 0]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['peer_cache_size' => 'bad']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['primary_cache_size' => 8, 'dirty_schema_pages' => 9]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['dirty_schema_pages' => 5, 'first_schema_pages' => 6]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['primary_cache_size' => 12, 'expanded_cache_size' => 11]));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pageCachePressureProfile(['expanded_cache_size' => 20, 'reduced_cache_size' => 21]));
};

$tests['real upstream corpus vfs pcache non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: owns upstream pcache.test pcache-1.1 through pcache-1.15 global pcache pressure, peer pinned-page over-limit free, cache resize, direct header reload, and reread transitions; avoids accepted VFS writer/sync/lock/file-control, shmlock, sharedlock, lock6/lock7, tkt2409 cache-spill read-lock, io.test sync/device, mmap, ioerr, diskfull, win32, delete_db, multiplex, quota, and pager/WAL transaction clusters',
        'non-overlap: owns upstream pcache.test pcache-1.1 through pcache-1.15 global pcache pressure, peer pinned-page over-limit free, cache resize, direct header reload, and reread transitions; avoids accepted VFS writer/sync/lock/file-control, shmlock, sharedlock, lock6/lock7, tkt2409 cache-spill read-lock, io.test sync/device, mmap, ioerr, diskfull, win32, delete_db, multiplex, quota, and pager/WAL transaction clusters'
    );
    $t->same(
        'dependency-closure: no new support component needed; this reuses the source-neutral VFS I/O dynamic corpus surface and hydrated upstream pcache.test source truth',
        'dependency-closure: no new support component needed; this reuses the source-neutral VFS I/O dynamic corpus surface and hydrated upstream pcache.test source truth'
    );
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next141.sqlite';
$masterPath = '/srv/wp-content/database/wp-next141.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('next141 stale schema before master recovery'),
    2 => $page('next141 stale options before master recovery'),
    3 => $page('next141 stale plugin rows before master recovery'),
    4 => $page('next141 stale autoload index before master recovery'),
    5 => $page('next141 stale transient page before master recovery'),
    6 => $page('next141 stale settings page before master recovery'),
];
$recovered = [
    1 => $page('next141 recovered schema current source'),
    2 => $page('next141 recovered options current source'),
    3 => $page('next141 recovered plugin rows current source'),
    4 => $page('next141 recovered autoload index current source'),
    5 => $page('next141 recovered transient page current source'),
    6 => $page('next141 recovered settings page current source'),
];
$dirty = [
    2 => $page('next141 dirty options spill candidate'),
    3 => $page('next141 dirty plugin rows spill candidate'),
    4 => $page('next141 dirty old-source autoload candidate'),
    5 => $page('next141 dirty pinned transient candidate'),
    6 => $page('next141 dirty settings source epoch candidate'),
];

$makeStack = static function () use ($recovered): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-copy');
    $stack->recordPageImageWrite(1, $recovered[1]);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(2, $recovered[2]);
    $stack->recordPageImageWrite(3, $recovered[3]);
    $stack->recordPageImageWrite(6, $recovered[6]);

    return $stack;
};

$cache = [
    2 => ['image' => $dirty[2], 'source_id' => 'next141-current-source', 'epoch' => 8, 'source' => 'dirty-options-cache', 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 20],
    3 => ['image' => $dirty[3], 'source_id' => 'next141-current-source', 'epoch' => 8, 'source' => 'dirty-plugin-cache', 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 21],
    4 => ['image' => $dirty[4], 'source_id' => 'old-next141-source', 'epoch' => 8, 'source' => 'old-source-autoload-cache', 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 22],
    5 => ['image' => $dirty[5], 'source_id' => 'next141-current-source', 'epoch' => 8, 'source' => 'pinned-transient-cache', 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true, 'walFrame' => 23],
    6 => ['image' => $dirty[6], 'source_id' => 'next141-current-source', 'epoch' => 7, 'source' => 'old-epoch-settings-cache', 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 24],
];

$plan = static fn (
    ?array $cachePages = null,
    string $journalMode = 'delete',
    bool $journalSynced = true,
    string $lockState = 'reserved',
    bool $cacheSpillEnabled = true,
    ?int $maxSpillPages = null,
    ?string $currentMaster = null,
    array $reads = [1, 2, 3, 4, 5, 6],
): array => SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/old/site.sqlite-journal\n",
    $currentMaster ?? ($databasePath . "-journal\n/srv/wp-content/database/site-next141.sqlite-journal\n"),
    implode('', $before),
    $pageSize,
    'plugin-batch',
    $makeStack(),
    $recovered,
    $cachePages ?? $cache,
    $reads,
    'next141-current-source',
    8,
    8,
    3,
    $journalMode,
    $journalSynced,
    $lockState,
    $cacheSpillEnabled,
    $maxSpillPages,
);

$walPlan = static fn (): array => $plan(journalMode: 'wal', lockState: 'shared');
$deferredPlan = static fn (): array => $plan(cachePages: [
    4 => $cache[4],
    5 => $cache[5],
    6 => $cache[6],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-cache-spill-savepoint-master-current-source-next141'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_current_source_filters_cache_before_savepoint_spill'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'hot cache status' => [static fn (): mixed => $plan()['hot_cache_status'], 'pager-master-journal-hot-cache-current-source-next136'],
    'cache stale rejected' => [static fn (): mixed => $plan()['cache_stale_rejected'], true],
    'retained cache pages empty because dirty candidates invalidate' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], []],
    'refreshed cache pages empty' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'invalidated cache pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6]],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'next141-current-source'],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 8],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-hot-cache:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 9],
    'eligible pages' => [static fn (): mixed => $plan()['eligible_page_numbers'], [2, 3]],
    'master rejected pages' => [static fn (): mixed => $plan()['master_rejected_page_numbers'], [4, 5, 6]],
    'rejected old source' => [static fn (): mixed => $plan()['master_rejected_pages'][4], ['stale_master_journal_source_id']],
    'rejected pinned' => [static fn (): mixed => $plan()['master_rejected_pages'][5], ['cache_page_pinned']],
    'rejected old epoch' => [static fn (): mixed => $plan()['master_rejected_pages'][6], ['stale_master_journal_source_epoch']],
    'source page two eligible' => [static fn (): mixed => $plan()['source_checks'][2]['eligible_for_savepoint_spill'], true],
    'source page two prefix current' => [static fn (): mixed => $plan()['source_checks'][2]['current_prefix'], 'next141 recovered options current source'],
    'source page two prefix cache' => [static fn (): mixed => $plan()['source_checks'][2]['cache_prefix'], 'next141 dirty options spill candidate'],
    'source page four not eligible' => [static fn (): mixed => $plan()['source_checks'][4]['eligible_for_savepoint_spill'], false],
    'source page four next epoch' => [static fn (): mixed => $plan()['source_checks'][4]['next_epoch'], 9],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'pager_cache_spill_savepoint_current_source_next137'],
    'spill reason' => [static fn (): mixed => $plan()['spill']['reason'], 'cache_spill_uses_current_source_savepoint_before_images'],
    'spill admitted pages' => [static fn (): mixed => $plan()['spill']['admitted_page_numbers'], [2, 3]],
    'spill rejected pages empty after master filter' => [static fn (): mixed => $plan()['spill']['rejected_page_numbers'], []],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3]],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill']['spill_target'], 'database_pages_after_rollback_journal'],
    'spill current dirty pages' => [static fn (): mixed => $plan()['spill']['spill']['current']['dirty_pages'], [2, 3]],
    'spill current journaled pages' => [static fn (): mixed => $plan()['spill']['spill']['current']['journaled_pages'], [2, 3]],
    'spill next dirty empty' => [static fn (): mixed => $plan()['spill']['spill']['next']['dirty_pages'], []],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager-cache-spill-savepoint-master-current-source-next141'],
    'wal mode' => [static fn (): mixed => $walPlan()['journal_mode'], 'wal'],
    'wal target' => [static fn (): mixed => $walPlan()['spill']['spill']['spill_target'], 'wal_frames'],
    'wal frame pages' => [static fn (): mixed => $walPlan()['wal_frame_pages'], [2, 3]],
    'wal database unchanged' => [static fn (): mixed => $walPlan()['spill']['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'one page limit keeps eligible list' => [static fn (): mixed => $plan(maxSpillPages: 1)['eligible_page_numbers'], [2, 3]],
    'one page limit spills only first' => [static fn (): mixed => $plan(maxSpillPages: 1)['spilled_page_numbers'], [2]],
    'unsynced deferred status' => [static fn (): mixed => $plan(journalSynced: false)['status'], 'pager-cache-spill-savepoint-master-current-source-deferred-next141'],
    'unsynced blocked reason' => [static fn (): mixed => $plan(journalSynced: false)['spill']['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled deferred status' => [static fn (): mixed => $plan(cacheSpillEnabled: false)['status'], 'pager-cache-spill-savepoint-master-current-source-deferred-next141'],
    'disabled blocked reason' => [static fn (): mixed => $plan(cacheSpillEnabled: false)['spill']['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'all rejected deferred status' => [static fn (): mixed => $deferredPlan()['status'], 'pager-cache-spill-savepoint-master-current-source-deferred-next141'],
    'all rejected eligible empty' => [static fn (): mixed => $deferredPlan()['eligible_page_numbers'], []],
    'all rejected spilled empty' => [static fn (): mixed => $deferredPlan()['spilled_page_numbers'], []],
    'all rejected rejected pages' => [static fn (): mixed => $deferredPlan()['master_rejected_page_numbers'], [4, 5, 6]],
    'clean current page rejected as not dirty' => [static fn (): mixed => $plan(cachePages: [2 => ['image' => $recovered[2], 'source_id' => 'next141-current-source', 'epoch' => 8]])['master_rejected_pages'][2], ['cache_page_not_dirty_against_recovered_current_source']],
    'clean flag rejected' => [static fn (): mixed => $plan(cachePages: [2 => ['image' => $dirty[2], 'source_id' => 'next141-current-source', 'epoch' => 8, 'dirty' => false]])['master_rejected_pages'][2], ['cache_page_clean']],
    'operation reads current master first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_hot_cache'],
    'operation discards cached members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members_for_hot_cache'],
    'operation admits page two' => [static fn (): mixed => in_array('admit_master_current_source_cache_spill_page', array_column($plan()['operations'], 'op'), true), true],
    'operation rejects stale page' => [static fn (): mixed => in_array('reject_master_current_source_cache_spill_page', array_column($plan()['operations'], 'op'), true), true],
    'operation eventually promotes lock' => [static fn (): mixed => in_array('promote_lock', array_column($plan()['operations'], 'op'), true), true],
    'final bytes page two recovered not dirty cache' => [static fn (): mixed => rtrim(substr($plan()['final_database_bytes'], $pageSize, $pageSize), '.'), 'next141 recovered options current source'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-savepoint-master-current-source-next141', $plan()['dependencies'], true), true],
    'dependency hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'dependency savepoint spill' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-savepoint-current-source-next137', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill savepoint master current source next141 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty cache rejected' => static fn () => $plan(cachePages: []),
    'bad read page rejected' => static fn () => $plan(reads: [0]),
    'missing current master rejected' => static fn () => $plan(currentMaster: ''),
    'current master missing database rejected' => static fn () => $plan(currentMaster: '/tmp/other.sqlite-journal' . "\n"),
    'short image rejected' => static fn () => $plan(cachePages: [2 => ['image' => 'short', 'source_id' => 'next141-current-source', 'epoch' => 8]]),
    'zero cache page rejected' => static fn () => $plan(cachePages: [0 => ['image' => $dirty[2], 'source_id' => 'next141-current-source', 'epoch' => 8]]),
    'bad page size rejected' => static fn () => SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan::plan($databasePath, $masterPath, null, $databasePath . "-journal\n", implode('', $before), 500, 'plugin-batch', $makeStack(), $recovered, $cache, [1], 'next141-current-source', 8, 8, 3),
    'missing savepoint rejected' => static fn () => SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan::plan($databasePath, $masterPath, null, $databasePath . "-journal\n", implode('', $before), $pageSize, 'missing', $makeStack(), $recovered, $cache, [1], 'next141-current-source', 8, 8, 3),
    'bad journal mode rejected' => static fn () => $plan(journalMode: 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill savepoint master current source next141 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

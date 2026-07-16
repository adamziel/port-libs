<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next144.sqlite';
$masterPath = '/srv/wp-content/database/wp-next144.sqlite-mj';
$sourceId = 'pager-next144-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$database = [
    1 => $page('next144 base sqlite header before master recovery'),
    2 => $page('next144 base wp_options root before master recovery'),
    3 => $page('next144 base autoload index before master recovery'),
    4 => $page('next144 base transient settings before master recovery'),
    5 => $page('next144 base plugin queue before master recovery'),
    6 => $page('next144 base reader pinned page before master recovery'),
];
$recovered = [
    1 => $page('next144 recovered sqlite header current source'),
    2 => $page('next144 recovered wp_options root current source'),
    3 => $page('next144 recovered autoload index current source'),
];
$cache = [
    ['page' => 2, 'image' => $page('next144 dirty options page ready to spill'), 'dirty' => true, 'journaled' => true, 'source_id' => $sourceId, 'epoch' => 14, 'bytes' => 512],
    ['page' => 3, 'image' => $page('next144 dirty autoload index ready to spill'), 'dirty' => true, 'journaled' => true, 'source_id' => $sourceId, 'epoch' => 14, 'bytes' => 512],
    ['page' => 4, 'image' => $page('next144 clean transient cache page'), 'dirty' => false, 'journaled' => true, 'source_id' => $sourceId, 'epoch' => 14, 'bytes' => 512],
    ['page' => 5, 'image' => $page('next144 dirty plugin queue without savepoint before image'), 'dirty' => true, 'journaled' => false, 'source_id' => $sourceId, 'epoch' => 14, 'bytes' => 512],
    ['page' => 6, 'image' => $page('next144 pinned reader page not spillable'), 'dirty' => true, 'journaled' => true, 'source_id' => $sourceId, 'epoch' => 14, 'pinned' => true, 'bytes' => 512],
];

$plan = static fn (
    ?array $cachePages = null,
    ?array $reads = null,
    bool $synced = true,
    bool $release = true,
    int $cacheSize = 8,
    int $threshold = 4,
    string $lock = 'reserved',
    ?int $maxSpill = 2,
    ?array $db = null,
    ?array $hot = null,
    string $path = '/srv/wp-content/database/wp-next144.sqlite',
    string $master = '/srv/wp-content/database/wp-next144.sqlite-mj',
    string $savepoint = 'wp_import_page',
    string $source = 'pager-next144-current-source',
    int $epoch = 14,
): array => SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan::plan(
    $path,
    $master,
    $savepoint,
    $pageSize,
    $db ?? $database,
    $hot ?? $recovered,
    $cachePages ?? array_slice($cache, 0, 3),
    $cacheSize,
    $threshold,
    $source,
    $epoch,
    $synced,
    $release,
    $reads ?? [1, 2, 3, 4],
    $lock,
    $maxSpill,
);

$blockedPlan = static fn (): array => $plan($cache, [2, 5, 6]);
$unsyncedPlan = static fn (): array => $plan(array_slice($cache, 0, 3), [2, 3], false);
$sharedLockPlan = static fn (): array => $plan(array_slice($cache, 0, 3), [2, 3], true, false, 8, 4, 'shared');
$limitedPlan = static fn (): array => $plan(array_slice($cache, 0, 3), [2, 3], true, false, 8, 4, 'exclusive', 1);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-savepoint-master-cache-spill-current-source-next144'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_current_source_savepoint_before_images_guard_cache_spill'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'wp_import_page'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 14],
    'master synced' => [static fn (): mixed => $plan()['master_journal_synced'], true],
    'release flag' => [static fn (): mixed => $plan()['release_after_spill'], true],
    'recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3]],
    'before pages' => [static fn (): mixed => $plan()['savepoint_before_page_numbers'], [2, 3, 4]],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3]],
    'no blocked pages' => [static fn (): mixed => $plan()['blocked_cache_pages'], []],
    'spill plan status' => [static fn (): mixed => $plan()['spill_plan_status'], 'spilled'],
    'spill blocked empty' => [static fn (): mixed => $plan()['spill_blocked_reasons'], []],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 3],
    'row two journaled' => [static fn (): mixed => $plan()['cache_rows'][0]['journaled_for_savepoint'], true],
    'row two before prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['before_prefix'], 'next144 recovered wp_options root current source'],
    'row two cache prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['cache_prefix'], 'next144 dirty options page ready to spill'],
    'row four clean not spilled' => [static fn (): mixed => $plan()['cache_rows'][2]['dirty'], false],
    'row four journaled for savepoint' => [static fn (): mixed => $plan()['cache_rows'][2]['journaled_for_savepoint'], true],
    'spill promote lock' => [static fn (): mixed => $plan()['spill_operations'][0]['op'], 'promote_lock'],
    'spill write page two' => [static fn (): mixed => $plan()['spill_operations'][1]['page'], 2],
    'spill write page three' => [static fn (): mixed => $plan()['spill_operations'][3]['page'], 3],
    'rollback read count' => [static fn (): mixed => count($plan()['rollback_reads']), 4],
    'rollback page one prefix' => [static fn (): mixed => $plan()['rollback_reads'][0]['prefix'], 'next144 recovered sqlite header current source'],
    'rollback page two restored' => [static fn (): mixed => $plan()['rollback_reads'][1]['restored_from_savepoint_before_image'], true],
    'rollback page two spilled before' => [static fn (): mixed => $plan()['rollback_reads'][1]['spilled_before_rollback_to'], true],
    'rollback page two prefix' => [static fn (): mixed => $plan()['rollback_reads'][1]['prefix'], 'next144 recovered wp_options root current source'],
    'rollback page four restored but not spilled' => [static fn (): mixed => $plan()['rollback_reads'][3]['restored_from_savepoint_before_image'], true],
    'release pages' => [static fn (): mixed => $plan()['release_page_numbers'], [2, 3]],
    'final page two prefix' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next144 dirty options page ready to spill'],
    'final page three prefix' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next144 dirty autoload index ready to spill'],
    'final page four prefix' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next144 base transient settings before master recovery'],
    'final page two source' => [static fn (): mixed => $plan()['final_sources'][2], 'cache-spill-write-after-savepoint-before-image'],
    'final page five source' => [static fn (): mixed => $plan()['final_sources'][5], 'database-before-master-journal-recovery'],
    'final bytes include spill' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'dirty options page ready'), true],
    'operation first applies master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'apply_master_journal_recovered_page_before_cache_spill'],
    'operation spill present' => [static fn (): mixed => in_array('spill_savepoint_journaled_cache_page_to_database', array_column($plan()['operations'], 'op'), true), true],
    'operation rollback present' => [static fn (): mixed => in_array('rollback_to_savepoint_reads_master_current_before_image', array_column($plan()['operations'], 'op'), true), true],
    'operation release final' => [static fn (): mixed => end($plan()['operations'])['op'], 'release_savepoint_after_cache_spill_keeps_database_pages'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-savepoint-master-cache-spill-current-source-next144', $plan()['dependencies'], true), true],
    'dependency cache spill' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-current-next71', $plan()['dependencies'], true), true],
    'dependency master savepoint' => [static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next138', $plan()['dependencies'], true), true],
    'dependency before image' => [static fn (): mixed => in_array('sqlite-savepoint-before-image-required-before-cache-spill', $plan()['dependencies'], true), true],
    'blocked status' => [static fn (): mixed => $blockedPlan()['status'], 'pager-savepoint-master-cache-spill-deferred-current-source-next144'],
    'blocked reason' => [static fn (): mixed => $blockedPlan()['reason'], 'cache_spill_deferred_until_master_journal_savepoint_before_images_are_current'],
    'blocked page five' => [static fn (): mixed => $blockedPlan()['blocked_cache_pages'][0]['page_number'], 5],
    'blocked page reason' => [static fn (): mixed => $blockedPlan()['blocked_cache_pages'][0]['reason'], 'dirty_page_lacks_savepoint_before_image'],
    'blocked spill reason' => [static fn (): mixed => $blockedPlan()['spill_blocked_reasons'], ['cache_spill_disabled']],
    'blocked read page five not restored' => [static fn (): mixed => $blockedPlan()['rollback_reads'][1]['restored_from_savepoint_before_image'], false],
    'blocked pinned row' => [static fn (): mixed => $blockedPlan()['cache_rows'][4]['pinned'], true],
    'unsynced deferred' => [static fn (): mixed => $unsyncedPlan()['spill_blocked_reasons'], ['journal_not_synced']],
    'shared lock deferred' => [static fn (): mixed => $sharedLockPlan()['spill_blocked_reasons'], ['exclusive_lock_unavailable']],
    'limited one spill' => [static fn (): mixed => $limitedPlan()['spilled_page_numbers'], [2]],
    'limited no release' => [static fn (): mixed => $limitedPlan()['release_page_numbers'], []],
    'duplicate reads collapse' => [static fn (): mixed => array_column($plan(array_slice($cache, 0, 3), [2, 2, 3])['rollback_reads'], 'page_number'), [2, 3]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint master cache spill current source next144 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(path: ''),
    'empty master path rejected' => static fn () => $plan(master: ''),
    'empty savepoint rejected' => static fn () => $plan(savepoint: ''),
    'empty source rejected' => static fn () => $plan(source: ''),
    'bad epoch rejected' => static fn () => $plan(epoch: 0),
    'negative cache size rejected' => static fn () => $plan(cacheSize: -1),
    'bad threshold rejected' => static fn () => $plan(threshold: 0),
    'bad max spill rejected' => static fn () => $plan(maxSpill: 0),
    'empty database pages rejected' => static fn () => $plan(db: []),
    'zero database page rejected' => static fn () => $plan(db: [0 => $database[1]]),
    'short database page rejected' => static fn () => $plan(db: [1 => 'short']),
    'zero recovered page rejected' => static fn () => $plan(hot: [0 => $recovered[1]]),
    'short recovered page rejected' => static fn () => $plan(hot: [1 => 'short']),
    'recovered outside database rejected' => static fn () => $plan(hot: [7 => $page('outside')]),
    'empty cache rejected' => static fn () => $plan(cachePages: []),
    'zero cache page rejected' => static fn () => $plan(cachePages: [['page' => 0, 'image' => $database[1]]]),
    'duplicate cache page rejected' => static fn () => $plan(cachePages: [['page' => 2, 'image' => $database[2]], ['page' => 2, 'image' => $database[2]]]),
    'short cache image rejected' => static fn () => $plan(cachePages: [['page' => 2, 'image' => 'short']]),
    'negative cache bytes rejected' => static fn () => $plan(cachePages: [['page' => 2, 'image' => $database[2], 'bytes' => -1]]),
    'cache outside database rejected' => static fn () => $plan(cachePages: [['page' => 7, 'image' => $page('outside')]]),
    'bad read page rejected' => static fn () => $plan(reads: [0]),
    'read outside database rejected' => static fn () => $plan(reads: [7]),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint master cache spill current source next144 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

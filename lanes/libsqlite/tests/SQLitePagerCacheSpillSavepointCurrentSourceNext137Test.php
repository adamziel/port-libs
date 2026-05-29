<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before1 = $page('next137 before schema root');
$before2 = $page('next137 before wp_options active_plugins');
$before3 = $page('next137 before wp_options theme mods');
$before4 = $page('next137 before autoload index');
$before5 = $page('next137 before transient cache row');
$dirty2 = $page('next137 dirty active_plugins cache page');
$dirty3 = $page('next137 dirty theme_mods cache page');
$dirty4 = $page('next137 dirty autoload index pinned page');
$dirty5 = $page('next137 dirty transient stale source page');
$databaseBytes = $before1 . $before2 . $before3 . $before4 . $before5;

$makeStack = static function () use ($before1, $before2, $before3, $before5): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-copy');
    $stack->recordPageImageWrite(1, $before1);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(2, $before2);
    $stack->recordPageImageWrite(3, $before3);
    $stack->savepoint('transient-cache');
    $stack->recordPageImageWrite(5, $before5);

    return $stack;
};

$cachePages = [
    ['page' => 2, 'image' => $dirty2, 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 8],
    ['page' => 3, 'image' => $dirty3, 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 9],
    ['page' => 4, 'image' => $dirty4, 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true, 'walFrame' => 10],
    ['page' => 5, 'image' => $dirty5, 'current_image' => $page('next137 stale transient source image'), 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 11],
];

$plan = static fn (
    ?array $pages = null,
    string $journalMode = 'delete',
    bool $journalSynced = true,
    string $lockState = 'reserved',
    bool $cacheSpillEnabled = true,
    ?int $maxSpillPages = null,
    string $savepoint = 'plugin-batch',
): array => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
    $databaseBytes,
    $pageSize,
    $savepoint,
    $makeStack(),
    $pages ?? $cachePages,
    7,
    3,
    $journalMode,
    $journalSynced,
    $lockState,
    $cacheSpillEnabled,
    $maxSpillPages
);

$walPlan = static fn (): array => $plan($cachePages, 'wal', true, 'shared');
$deferredPlan = static fn (): array => $plan([
    ['page' => 4, 'image' => $dirty4, 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true],
    ['page' => 5, 'image' => $dirty5, 'current_image' => $page('next137 stale transient source image'), 'bytes' => $pageSize, 'journaled' => true],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_cache_spill_savepoint_current_source_next137'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'cache_spill_uses_current_source_savepoint_before_images'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_page_numbers'], [2, 3]],
    'rejected pages' => [static fn (): mixed => $plan()['rejected_page_numbers'], [4, 5]],
    'page two admitted' => [static fn (): mixed => $plan()['source_checks'][2]['admitted'], true],
    'page two current prefix' => [static fn (): mixed => $plan()['source_checks'][2]['current_prefix'], 'next137 before wp_options active_plugins'],
    'page two cache prefix' => [static fn (): mixed => $plan()['source_checks'][2]['cache_prefix'], 'next137 dirty active_plugins cache page'],
    'page two current matches' => [static fn (): mixed => $plan()['source_checks'][2]['current_matches_database'], true],
    'page two has savepoint image' => [static fn (): mixed => $plan()['source_checks'][2]['has_savepoint_before_image'], true],
    'page three admitted' => [static fn (): mixed => $plan()['source_checks'][3]['admitted'], true],
    'page three has no reject reasons' => [static fn (): mixed => $plan()['source_checks'][3]['rejected_reasons'], []],
    'page four rejected pinned' => [static fn (): mixed => $plan()['rejected_pages'][4], ['cache_page_pinned', 'missing_savepoint_before_image']],
    'page four source pinned' => [static fn (): mixed => $plan()['source_checks'][4]['pinned'], true],
    'page four missing image' => [static fn (): mixed => $plan()['source_checks'][4]['has_savepoint_before_image'], false],
    'page five rejected stale' => [static fn (): mixed => $plan()['rejected_pages'][5], ['current_source_mismatch']],
    'page five current mismatch' => [static fn (): mixed => $plan()['source_checks'][5]['current_matches_database'], false],
    'page five savepoint image exists' => [static fn (): mixed => $plan()['source_checks'][5]['has_savepoint_before_image'], true],
    'savepoint restore pages' => [static fn (): mixed => $plan()['savepoint_restore_page_numbers'], [2, 3, 5]],
    'savepoint missing pages' => [static fn (): mixed => $plan()['savepoint_missing_page_numbers'], []],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill_target'], 'database_pages_after_rollback_journal'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3]],
    'spill dirty pages excludes rejected pages' => [static fn (): mixed => $plan()['spill']['current']['dirty_pages'], [2, 3]],
    'spill journaled pages excludes rejected pages' => [static fn (): mixed => $plan()['spill']['current']['journaled_pages'], [2, 3]],
    'spill next dirty pages empty' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], []],
    'spill promotes lock' => [static fn (): mixed => $plan()['spill']['operations'][0]['op'], 'promote_lock'],
    'operation admits page two' => [static fn (): mixed => $plan()['operations'][0]['op'], 'admit_savepoint_cache_spill_page'],
    'operation admits page three' => [static fn (): mixed => $plan()['operations'][1]['page'], 3],
    'operation defers pinned page' => [static fn (): mixed => $plan()['operations'][2]['reasons'], ['cache_page_pinned', 'missing_savepoint_before_image']],
    'operation defers stale page' => [static fn (): mixed => $plan()['operations'][3]['reasons'], ['current_source_mismatch']],
    'operation then promotes lock' => [static fn (): mixed => $plan()['operations'][4]['op'], 'promote_lock'],
    'dependency next137' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-savepoint-current-source-next137', $plan()['dependencies'], true), true],
    'dependency next107' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true), true],
    'dependency savepoint image rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager_cache_spill_savepoint_current_source_next137'],
    'wal journal mode' => [static fn (): mixed => $walPlan()['journal_mode'], 'wal'],
    'wal spill target' => [static fn (): mixed => $walPlan()['spill']['spill_target'], 'wal_frames'],
    'wal frame pages' => [static fn (): mixed => $walPlan()['wal_frame_pages'], [2, 3]],
    'wal database unchanged' => [static fn (): mixed => $walPlan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'wal frame operation' => [static fn (): mixed => $walPlan()['spill']['operations'][0]['op'], 'append_wal_frame'],
    'one page limit admitted unchanged' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, 1)['admitted_page_numbers'], [2, 3]],
    'one page limit spills first admitted' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, 1)['spilled_page_numbers'], [2]],
    'unsynced defers status' => [static fn (): mixed => $plan(null, 'delete', false)['status'], 'pager_cache_spill_savepoint_current_source_deferred_next137'],
    'unsynced blocked reason' => [static fn (): mixed => $plan(null, 'delete', false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers status' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', false)['status'], 'pager_cache_spill_savepoint_current_source_deferred_next137'],
    'disabled blocked reason' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'all rejected defers status' => [static fn (): mixed => $deferredPlan()['status'], 'pager_cache_spill_savepoint_current_source_deferred_next137'],
    'all rejected admitted empty' => [static fn (): mixed => $deferredPlan()['admitted_page_numbers'], []],
    'all rejected spill no eligible' => [static fn (): mixed => $deferredPlan()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'clean page rejected reason' => [static fn (): mixed => $plan([['page' => 2, 'image' => $dirty2, 'dirty' => false]])['rejected_pages'][2], ['cache_page_clean']],
    'transaction savepoint admits page one' => [static fn (): mixed => $plan([['page' => 1, 'image' => $page('next137 dirty schema root'), 'journaled' => true]], 'delete', true, 'reserved', true, null, 'wp-options-copy')['admitted_page_numbers'], [1]],
    'transaction savepoint spills page one' => [static fn (): mixed => $plan([['page' => 1, 'image' => $page('next137 dirty schema root'), 'journaled' => true]], 'delete', true, 'reserved', true, null, 'wp-options-copy')['spilled_page_numbers'], [1]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill savepoint current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database' => static fn () => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext('', $pageSize, 'plugin-batch', $makeStack(), $cachePages, 7, 3),
    'rejects unaligned database' => static fn () => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($databaseBytes . 'x', $pageSize, 'plugin-batch', $makeStack(), $cachePages, 7, 3),
    'rejects bad page size' => static fn () => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($databaseBytes, 0, 'plugin-batch', $makeStack(), $cachePages, 7, 3),
    'rejects empty savepoint' => static fn () => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($databaseBytes, $pageSize, '', $makeStack(), $cachePages, 7, 3),
    'rejects empty cache pages' => static fn () => SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($databaseBytes, $pageSize, 'plugin-batch', $makeStack(), [], 7, 3),
    'rejects bad page' => static fn () => $plan([['page' => 0, 'image' => $dirty2]]),
    'rejects duplicate page' => static fn () => $plan([['page' => 2, 'image' => $dirty2], ['page' => 2, 'image' => $dirty3]]),
    'rejects page outside database' => static fn () => $plan([['page' => 6, 'image' => $dirty2]]),
    'rejects short image' => static fn () => $plan([['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan([['page' => 2, 'image' => $dirty2, 'current_image' => 'short']]),
    'rejects missing savepoint' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, 'missing'),
    'rejects bad journal mode' => static fn () => $plan(null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill savepoint current source next137 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

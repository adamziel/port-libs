<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next143.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$base = [
    1 => $page('next143 base sqlite header before wal savepoint'),
    2 => $page('next143 base wp_options root before wal'),
    3 => $page('next143 base active_plugins before wal'),
    4 => $page('next143 base autoload index before wal'),
    5 => $page('next143 base transient row before wal'),
    6 => $page('next143 base reader pinned page before wal'),
];
$databaseBytes = implode('', $base);
$walFrames = [
    1 => ['page' => 2, 'image' => $page('next143 retained wal wp_options root'), 'commit_frame' => false],
    2 => ['page' => 3, 'image' => $page('next143 retained wal active_plugins'), 'commit_frame' => true],
    3 => ['page' => 4, 'image' => $page('next143 discarded wal autoload index')],
    4 => ['page' => 5, 'image' => $page('next143 discarded wal transient row'), 'commit_frame' => true],
];
$cachePages = [
    ['page' => 2, 'image' => $page('next143 retry wp_options cache spill'), 'current_image' => $walFrames[1]['image'], 'bytes' => $pageSize, 'walFrame' => 1],
    ['page' => 3, 'image' => $page('next143 retry active_plugins cache spill'), 'current_image' => $walFrames[2]['image'], 'bytes' => $pageSize, 'walFrame' => 2],
    ['page' => 4, 'image' => $walFrames[3]['image'], 'current_image' => $base[4], 'bytes' => $pageSize, 'walFrame' => 3],
    ['page' => 5, 'image' => $page('next143 retry transient stale source'), 'current_image' => $walFrames[4]['image'], 'bytes' => $pageSize, 'walFrame' => 4],
    ['page' => 6, 'image' => $page('next143 retry reader pinned page'), 'current_image' => $base[6], 'bytes' => $pageSize, 'pinned' => true],
];

$makeStack = static function () use ($base, $walFrames): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-import');
    $stack->recordPageImageWrite(1, $base[1]);
    $stack->recordWalFrameWrite(1, 2, false);
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('plugin-batch');
    $stack->recordPageImageWrite(2, $walFrames[1]['image']);
    $stack->recordPageImageWrite(3, $walFrames[2]['image']);
    $stack->recordPageImageWrite(4, $base[4]);
    $stack->recordPageImageWrite(5, $base[5]);
    $stack->recordPageImageWrite(6, $base[6]);
    $stack->recordWalFrameWrite(3, 4, false);
    $stack->recordWalFrameWrite(4, 5, true);

    return $stack;
};

$plan = static fn (
    ?array $pages = null,
    ?array $frames = null,
    bool $synced = true,
    bool $enabled = true,
    ?int $limit = null,
    string $savepoint = 'plugin-batch',
    ?string $bytes = null,
    ?int $size = null,
    string $path = '/srv/wp-content/database/wp-next143.sqlite',
): array => SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan::currentSourceNext(
    $path,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $savepoint,
    $makeStack(),
    $frames ?? $walFrames,
    $pages ?? $cachePages,
    7,
    3,
    $synced,
    $enabled,
    $limit
);

$allRejected = static fn (): array => $plan([
    ['page' => 4, 'image' => $walFrames[3]['image'], 'current_image' => $base[4], 'bytes' => $pageSize, 'walFrame' => 3],
    ['page' => 6, 'image' => $page('next143 reader pinned only'), 'current_image' => $base[6], 'bytes' => $pageSize, 'pinned' => true],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_cache_spill_wal_savepoint_current_source_next143'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'wal_cache_spill_after_savepoint_rollback_uses_verified_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'page count' => [static fn (): mixed => $plan()['page_count'], 6],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_frame'], 2],
    'discarded frames' => [static fn (): mixed => $plan()['discarded_wal_frames'], [3, 4]],
    'discarded pages' => [static fn (): mixed => $plan()['discarded_wal_pages'], [4, 5]],
    'restore pages' => [static fn (): mixed => $plan()['savepoint_restore_page_numbers'], [2, 3, 4, 5, 6]],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_page_numbers'], [2, 3]],
    'rejected pages' => [static fn (): mixed => $plan()['rejected_page_numbers'], [4, 5, 6]],
    'page two admitted' => [static fn (): mixed => $plan()['source_checks'][2]['admitted'], true],
    'page two source' => [static fn (): mixed => $plan()['source_checks'][2]['current_source'], 'retained-wal-frame'],
    'page two frame' => [static fn (): mixed => $plan()['source_checks'][2]['current_frame'], 1],
    'page two prefix' => [static fn (): mixed => $plan()['source_checks'][2]['current_prefix'], 'next143 retained wal wp_options root'],
    'page two cache prefix' => [static fn (): mixed => $plan()['source_checks'][2]['cache_prefix'], 'next143 retry wp_options cache spill'],
    'page three commit source' => [static fn (): mixed => $plan()['source_checks'][3]['current_source'], 'retained-commit-wal-frame'],
    'page three verified' => [static fn (): mixed => $plan()['source_checks'][3]['current_image_verified'], true],
    'page four rejected discarded frame' => [static fn (): mixed => $plan()['rejected_pages'][4], ['cache_page_from_discarded_wal_savepoint_frame', 'cache_image_matches_discarded_wal_tail']],
    'page four flag discarded' => [static fn (): mixed => $plan()['source_checks'][4]['from_discarded_wal_frame'], true],
    'page four current database source' => [static fn (): mixed => $plan()['source_checks'][4]['current_source'], 'database'],
    'page five rejected mismatch and discarded' => [static fn (): mixed => $plan()['rejected_pages'][5], ['wal_savepoint_current_source_mismatch', 'cache_page_from_discarded_wal_savepoint_frame']],
    'page five current frame' => [static fn (): mixed => $plan()['source_checks'][5]['current_frame'], 0],
    'page six rejected pinned' => [static fn (): mixed => $plan()['rejected_pages'][6], ['cache_page_pinned']],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill_target'], 'wal_frames'],
    'spill current dirty' => [static fn (): mixed => $plan()['spill']['current']['dirty_pages'], [2, 3]],
    'spill current journaled' => [static fn (): mixed => $plan()['spill']['current']['journaled_pages'], [2, 3]],
    'spill frame pages' => [static fn (): mixed => $plan()['spill']['next']['wal_frame_pages'], [2, 3]],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3]],
    'database unchanged' => [static fn (): mixed => $plan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'journal rollback not required' => [static fn (): mixed => $plan()['spill']['next']['journal_required_for_rollback'], false],
    'next wal frame start' => [static fn (): mixed => $plan()['next_wal_frame_start'], 3],
    'appended frames' => [static fn (): mixed => array_column($plan()['appended_wal_frames'], 'frame_index'), [3, 4]],
    'appended pages' => [static fn (): mixed => array_column($plan()['appended_wal_frames'], 'page'), [2, 3]],
    'operation rollback first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'rollback_wal_to_savepoint_before_cache_spill'],
    'operation admits page two' => [static fn (): mixed => $plan()['operations'][1]['op'], 'admit_wal_savepoint_cache_spill_page'],
    'operation defers page four' => [static fn (): mixed => $plan()['operations'][3]['page'], 4],
    'operation append frame' => [static fn (): mixed => $plan()['operations'][6]['op'], 'append_wal_frame'],
    'operation mark clean' => [static fn (): mixed => $plan()['operations'][7]['op'], 'mark_page_clean_in_cache'],
    'dependency next143' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-wal-savepoint-current-source-next143', $plan()['dependencies'], true), true],
    'dependency savepoint current source' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-savepoint-current-source-next137', $plan()['dependencies'], true), true],
    'dependency byte truncation' => [static fn (): mixed => in_array('sqlite-wal-savepoint-byte-truncation', $plan()['dependencies'], true), true],
    'one page limit spills first' => [static fn (): mixed => $plan(null, null, true, true, 1)['spilled_page_numbers'], [2]],
    'one page limit leaves dirty page three' => [static fn (): mixed => $plan(null, null, true, true, 1)['spill']['next']['dirty_pages'], [3]],
    'unsynced defers' => [static fn (): mixed => $plan(null, null, false)['status'], 'pager_cache_spill_wal_savepoint_current_source_deferred_next143'],
    'unsynced reason' => [static fn (): mixed => $plan(null, null, false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers' => [static fn (): mixed => $plan(null, null, true, false)['status'], 'pager_cache_spill_wal_savepoint_current_source_deferred_next143'],
    'disabled reason' => [static fn (): mixed => $plan(null, null, true, false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'all rejected defers' => [static fn (): mixed => $allRejected()['status'], 'pager_cache_spill_wal_savepoint_current_source_deferred_next143'],
    'all rejected admitted empty' => [static fn (): mixed => $allRejected()['admitted_page_numbers'], []],
    'all rejected spill reason' => [static fn (): mixed => $allRejected()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'clean page rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => $walFrames[1]['image'], 'dirty' => false]])['rejected_pages'][2], ['cache_page_clean']],
    'unjournaled page rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => $walFrames[1]['image'], 'journaled' => false]])['rejected_pages'][2], ['cache_page_not_journaled']],
    'missing savepoint image rejected' => [static fn (): mixed => $plan([['page' => 1, 'image' => $page('next143 schema retry'), 'current_image' => $base[1]]])['rejected_pages'][1], ['missing_savepoint_before_image']],
    'retained database page source' => [static fn (): mixed => $plan([['page' => 6, 'image' => $page('next143 reader retry'), 'current_image' => $base[6]]])['source_checks'][6]['current_source'], 'database'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill wal savepoint current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty path' => static fn () => $plan(null, null, true, true, null, 'plugin-batch', null, null, ''),
    'rejects empty database' => static fn () => $plan(null, null, true, true, null, 'plugin-batch', ''),
    'rejects unaligned database' => static fn () => $plan(null, null, true, true, null, 'plugin-batch', $databaseBytes . 'x'),
    'rejects bad page size' => static fn () => $plan(null, null, true, true, null, 'plugin-batch', null, 0),
    'rejects empty savepoint' => static fn () => $plan(null, null, true, true, null, ''),
    'rejects missing savepoint' => static fn () => $plan(null, null, true, true, null, 'missing'),
    'rejects empty wal frames' => static fn () => $plan(null, []),
    'rejects bad frame index' => static fn () => $plan(null, [0 => ['page' => 2, 'image' => $walFrames[1]['image']]]),
    'rejects bad frame page' => static fn () => $plan(null, [1 => ['page' => 0, 'image' => $walFrames[1]['image']]]),
    'rejects frame outside database' => static fn () => $plan(null, [1 => ['page' => 7, 'image' => $walFrames[1]['image']]]),
    'rejects short frame image' => static fn () => $plan(null, [1 => ['page' => 2, 'image' => 'short']]),
    'rejects empty cache pages' => static fn () => $plan([]),
    'rejects cache page zero' => static fn () => $plan([['page' => 0, 'image' => $cachePages[0]['image']]]),
    'rejects duplicate cache page' => static fn () => $plan([['page' => 2, 'image' => $cachePages[0]['image']], ['page' => 2, 'image' => $cachePages[1]['image']]]),
    'rejects cache outside database' => static fn () => $plan([['page' => 7, 'image' => $cachePages[0]['image']]]),
    'rejects short cache image' => static fn () => $plan([['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan([['page' => 2, 'image' => $cachePages[0]['image'], 'current_image' => 'short']]),
    'rejects negative bytes' => static fn () => $plan([['page' => 2, 'image' => $cachePages[0]['image'], 'bytes' => -1]]),
    'rejects bad cache frame' => static fn () => $plan([['page' => 2, 'image' => $cachePages[0]['image'], 'walFrame' => 0]]),
    'rejects bad threshold' => static fn () => SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $pageSize, 'plugin-batch', $makeStack(), $walFrames, $cachePages, 7, 0),
    'rejects bad max spill' => static fn () => SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan::currentSourceNext($databasePath, $databaseBytes, $pageSize, 'plugin-batch', $makeStack(), $walFrames, $cachePages, 7, 3, true, true, 0),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill wal savepoint current source next143 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

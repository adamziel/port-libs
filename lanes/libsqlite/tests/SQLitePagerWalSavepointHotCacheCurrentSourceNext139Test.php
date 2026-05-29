<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next139.sqlite';
$sourceId = 'wal-hot-cache-current-next139';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$base = [
    1 => $page('next139 base sqlite header before hot recovery'),
    2 => $page('next139 base wp_options root before hot recovery'),
    3 => $page('next139 base plugin settings before hot recovery'),
    4 => $page('next139 base transient cache before hot recovery'),
    5 => $page('next139 base autoload index before hot recovery'),
    6 => $page('next139 base reader pinned page before hot recovery'),
];
$databaseBytes = implode('', $base);
$hotRecovered = [
    1 => $page('next139 hot recovered sqlite header current source'),
    2 => $page('next139 hot recovered wp_options root current source'),
    6 => $page('next139 hot recovered reader pinned page current source'),
];
$walFrames = [
    1 => ['page' => 1, 'image' => $page('next139 retained wal schema cookie frame'), 'commit_frame' => true],
    2 => ['page' => 2, 'image' => $page('next139 discarded active plugins frame')],
    3 => ['page' => 3, 'image' => $page('next139 discarded plugin settings frame')],
    4 => ['page' => 4, 'image' => $page('next139 discarded transient frame')],
    5 => ['page' => 5, 'image' => $page('next139 discarded autoload retry frame'), 'commit_frame' => true],
];
$cache = [
    1 => ['image' => $walFrames[1]['image'], 'source_id' => $sourceId, 'generation' => 11, 'frame' => 1, 'source' => 'cache-retained-commit-frame'],
    2 => ['image' => $walFrames[2]['image'], 'source_id' => $sourceId, 'generation' => 11, 'frame' => 2, 'source' => 'clean-cache-discarded-frame'],
    3 => ['image' => $walFrames[3]['image'], 'source_id' => $sourceId, 'generation' => 11, 'frame' => 3, 'dirty' => true, 'source' => 'dirty-plugin-cache'],
    4 => ['image' => $base[4], 'source_id' => 'old-hot-source', 'generation' => 11, 'frame' => 0, 'source' => 'old-source-cache'],
    5 => ['image' => $base[5], 'source_id' => $sourceId, 'generation' => 10, 'frame' => 0, 'source' => 'old-generation-cache'],
    6 => ['image' => $base[6], 'source_id' => $sourceId, 'generation' => 11, 'frame' => 0, 'pinned' => true, 'source' => 'pinned-reader-cache'],
];
$writes = [
    2 => $page('next139 rewritten active_plugins after rollback'),
    3 => $page('next139 rewritten plugin settings after rollback'),
];

$plan = static fn (
    ?array $hot = null,
    ?array $frames = null,
    ?array $cachePages = null,
    ?array $reads = null,
    ?array $writePages = null,
    ?string $db = null,
    ?int $size = null,
    string $savepoint = 'wp-import',
    int $rollback = 1,
    string $source = 'wal-hot-cache-current-next139',
    int $generation = 11,
    bool $refresh = true,
    string $path = '/srv/wp-content/database/wp-next139.sqlite',
): array => SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan::plan(
    $path,
    $db ?? $databaseBytes,
    $size ?? $pageSize,
    $savepoint,
    $rollback,
    $hot ?? $hotRecovered,
    $frames ?? $walFrames,
    $cachePages ?? $cache,
    $reads ?? [1, 2, 3, 4, 5, 6],
    $writePages ?? $writes,
    $source,
    $generation,
    $refresh,
);

$blocked = static fn (): array => $plan(null, null, null, [2], [], null, null, 'wp-import', 1, $sourceId, 11, false);
$nested = static fn (): array => $plan(null, null, null, [1, 2, 3, 4, 5], [], null, null, 'wp-import', 3);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-wal-savepoint-hot-cache-current-source-next139'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_recovery_and_savepoint_rollback_rebase_wal_cache_before_next_read_write'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'wp-import'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_frame'], 1],
    'discarded frames' => [static fn (): mixed => $plan()['discarded_wal_frames'], [2, 3, 4, 5]],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current generation' => [static fn (): mixed => $plan()['current_source']['generation'], 11],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'wal-savepoint-hot-cache:'), true],
    'next generation' => [static fn (): mixed => $plan()['next_source']['generation'], 12],
    'hot pages' => [static fn (): mixed => $plan()['hot_recovered_page_numbers'], [1, 2, 6]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'blocked pages empty' => [static fn (): mixed => $plan()['blocked_cache_page_numbers'], []],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][0]['reason'], 'dirty_cache_page_from_discarded_wal_savepoint_frame'],
    'invalidated source reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][1]['reason'], 'stale_wal_hot_cache_source_id'],
    'invalidated generation reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][2]['reason'], 'stale_wal_hot_cache_generation'],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][3]['reason'], 'pinned_reader_cache_predates_hot_wal_current_source'],
    'cache rows count' => [static fn (): mixed => count($plan()['cache_rows']), 6],
    'row one source frame' => [static fn (): mixed => $plan()['cache_rows'][0]['source_frame'], 1],
    'row one retained match' => [static fn (): mixed => $plan()['cache_rows'][0]['matches_current_after'], true],
    'row two stale' => [static fn (): mixed => $plan()['cache_rows'][1]['stale_before_action'], true],
    'row two current hot prefix' => [static fn (): mixed => $plan()['cache_rows'][1]['current_prefix'], 'next139 hot recovered wp_options root current source'],
    'row three dirty' => [static fn (): mixed => $plan()['cache_rows'][2]['dirty'], true],
    'row six pinned' => [static fn (): mixed => $plan()['cache_rows'][5]['pinned'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read one frame' => [static fn (): mixed => $plan()['next_reads'][0]['source_frame'], 1],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read two prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next139 hot recovered wp_options root current source'],
    'read three miss discarded dirty' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read three base prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next139 base plugin settings before hot recovery'],
    'read six miss hot recovered pinned' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next139 hot recovered reader pinned page current source'],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write two before from hot current' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next139 hot recovered wp_options root current source'],
    'write two before frame' => [static fn (): mixed => $plan()['next_writes'][0]['before_frame'], 0],
    'write three before from base current' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next139 base plugin settings before hot recovery'],
    'write journal current source flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_source'], true],
    'final cache pages' => [static fn (): mixed => $plan()['final_cache_page_numbers'], [1, 2, 3]],
    'final dirty pages' => [static fn (): mixed => $plan()['final_cache_dirty_page_numbers'], [2, 3]],
    'final source page one retained wal' => [static fn (): mixed => $plan()['final_sources'][1], 'retained-commit-wal-frame-current-source'],
    'final source page two write' => [static fn (): mixed => $plan()['final_sources'][2], 'next-write-after-wal-savepoint-hot-cache'],
    'final source page six hot' => [static fn (): mixed => $plan()['final_sources'][6], 'hot-recovered-database-current-source'],
    'final prefix page three' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next139 rewritten plugin settings after rollback'],
    'final bytes include rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final bytes exclude dirty cache' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'dirty-plugin-cache'), false],
    'operation hot recovery first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'recover_hot_database_pages_before_wal_savepoint_cache'],
    'operation rollback second' => [static fn (): mixed => $plan()['operations'][1]['op'], 'rollback_wal_to_savepoint_frame_for_hot_cache'],
    'operation retain page one' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_wal_savepoint_hot_cache_page'],
    'operation refresh page two' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_wal_savepoint_hot_cache_page'],
    'operation invalidate page three' => [static fn (): mixed => $plan()['operations'][4]['page_number'], 3],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][8]['op'], 'next_read_wal_savepoint_hot_cache_hit'],
    'operation read miss' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_read_wal_savepoint_hot_cache_miss'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][14]['op'], 'capture_next_write_before_image_from_wal_savepoint_hot_current_source'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-wal-savepoint-hot-cache-current-source-next139', $plan()['dependencies'], true), true],
    'dependency wal cache recovery' => [static fn (): mixed => in_array('sqlite-pager-savepoint-wal-cache-recovery-current-source-next133', $plan()['dependencies'], true), true],
    'dependency master hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'duplicate reads collapse' => [static fn (): mixed => array_column($plan(null, null, null, [2, 2, 1], [])['next_reads'], 'page_number'), [2, 1]],
    'nested discarded frames' => [static fn (): mixed => $nested()['discarded_wal_frames'], [4, 5]],
    'nested retained pages' => [static fn (): mixed => $nested()['retained_cache_page_numbers'], [1, 2, 3]],
    'nested refreshed pages' => [static fn (): mixed => $nested()['refreshed_cache_page_numbers'], []],
    'nested invalidated pages' => [static fn (): mixed => $nested()['invalidated_cache_page_numbers'], [4, 5, 6]],
    'nested read three hit' => [static fn (): mixed => $nested()['next_reads'][2]['cache_hit'], true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'pager-wal-savepoint-hot-cache-blocked-current-source-next139'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'stale_clean_wal_cache_pages_remain_after_hot_recovery_and_savepoint_rollback'],
    'blocked page two' => [static fn (): mixed => $blocked()['blocked_cache_page_numbers'], [2]],
    'blocked no refresh pages' => [static fn (): mixed => $blocked()['refreshed_cache_page_numbers'], []],
    'blocked stale refresh reason' => [static fn (): mixed => $blocked()['invalidated_cache_entries'][0]['reason'], 'stale_wal_hot_cache_refresh_disabled'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager wal savepoint hot cache current source next139 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty path rejected' => static fn () => $plan(null, null, null, null, null, null, null, 'wp-import', 1, $sourceId, 11, true, ''),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, $databaseBytes . 'x'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty savepoint rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'negative rollback rejected' => static fn () => $plan(null, null, null, null, null, null, null, 'wp-import', -1),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, 'wp-import', 1, ''),
    'bad generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, 'wp-import', 1, $sourceId, 0),
    'empty next operation rejected' => static fn () => $plan(null, null, null, [], []),
    'empty hot pages rejected' => static fn () => $plan([]),
    'zero hot page rejected' => static fn () => $plan([0 => $hotRecovered[1]]),
    'short hot image rejected' => static fn () => $plan([1 => 'short']),
    'empty wal frames rejected' => static fn () => $plan(null, []),
    'zero wal frame rejected' => static fn () => $plan(null, [0 => ['page' => 1, 'image' => $base[1]]]),
    'wal page outside rejected' => static fn () => $plan(null, [1 => ['page' => 7, 'image' => $base[1]]]),
    'short wal image rejected' => static fn () => $plan(null, [1 => ['page' => 1, 'image' => 'short']]),
    'empty cache rejected' => static fn () => $plan(null, null, []),
    'zero cache page rejected' => static fn () => $plan(null, null, [0 => ['image' => $base[1]]]),
    'short cache image rejected' => static fn () => $plan(null, null, [1 => ['image' => 'short']]),
    'bad cache frame rejected' => static fn () => $plan(null, null, [1 => ['image' => $base[1], 'frame' => -1]]),
    'bad cache generation rejected' => static fn () => $plan(null, null, [1 => ['image' => $base[1], 'generation' => -1]]),
    'bad read page rejected' => static fn () => $plan(null, null, null, [0], []),
    'zero write page rejected' => static fn () => $plan(null, null, null, [], [0 => $base[1]]),
    'short write image rejected' => static fn () => $plan(null, null, null, [], [1 => 'short']),
    'hot page outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'cache page outside database rejected' => static fn () => $plan(null, null, [7 => ['image' => $page('outside')]]),
    'read page outside database rejected' => static fn () => $plan(null, null, null, [7], []),
    'write page outside database rejected' => static fn () => $plan(null, null, null, [], [7 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager wal savepoint hot cache current source next139 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

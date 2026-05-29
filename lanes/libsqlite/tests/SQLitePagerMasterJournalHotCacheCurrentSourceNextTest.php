<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next136.sqlite';
$masterPath = '/srv/wp-content/database/wp-next136.sqlite-mj';
$cachedMaster = $databasePath . "-journal\n";
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/site-next136.sqlite-journal\n";
$currentSourceId = 'cached-master-hot-cache-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next136 stale schema page before hot recovery'),
    2 => $page('next136 stale wp_options root before hot recovery'),
    3 => $page('next136 stale autoload index before hot recovery'),
    4 => $page('next136 unchanged comments page before hot recovery'),
    5 => $page('next136 stale plugin settings page before hot recovery'),
    6 => $page('next136 stale pinned reader page before hot recovery'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next136 recovered schema page current source'),
    2 => $page('next136 recovered wp_options root current source'),
    3 => $page('next136 recovered autoload index current source'),
    5 => $page('next136 recovered plugin settings current source'),
    6 => $page('next136 recovered pinned reader current source'),
];
$cache = [
    1 => ['image' => $recovered[1], 'source_id' => $currentSourceId, 'epoch' => 7, 'source' => 'hot-cache-after-master-recovery'],
    2 => ['image' => $before[2], 'source_id' => $currentSourceId, 'epoch' => 7, 'source' => 'clean-cache-before-master-recovery'],
    3 => ['image' => $recovered[3], 'source_id' => 'old-master-source', 'epoch' => 7, 'source' => 'old-source-cache'],
    4 => ['image' => $before[4], 'source_id' => $currentSourceId, 'epoch' => 6, 'source' => 'old-epoch-cache'],
    5 => ['image' => $before[5], 'source_id' => $currentSourceId, 'epoch' => 7, 'dirty' => true, 'source' => 'dirty-cache-from-crash'],
    6 => ['image' => $before[6], 'source_id' => $currentSourceId, 'epoch' => 7, 'pinned' => true, 'source' => 'pinned-reader-cache'],
];
$nextWrites = [
    2 => $page('next136 rewritten autoload option after hot cache refresh'),
    5 => $page('next136 rewritten plugin setting after dirty cache invalidation'),
];

$plan = static fn (
    ?array $recoveredPages = null,
    ?array $cachePages = null,
    ?array $readPages = null,
    ?array $writePages = null,
    mixed $cached = '__default__',
    mixed $current = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $source = null,
    int $epoch = 7,
    bool $refresh = true,
    ?string $path = null,
    ?string $masterJournalPath = null,
): array => SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $cached === '__default__' ? $cachedMaster : $cached,
    $current === '__default__' ? $currentMaster : $current,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $cachePages ?? $cache,
    $readPages ?? [1, 2, 3, 4, 5, 6],
    $writePages ?? $nextWrites,
    $source ?? $currentSourceId,
    $epoch,
    $refresh,
);

$noRefresh = static fn (): array => $plan(null, null, [2], [], '__default__', '__default__', null, null, null, 7, false);
$sameMembers = static fn (): array => $plan(null, null, [1], [], $currentMaster, $currentMaster);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-hot-cache-current-source-next136'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_hot_recovery_rebases_pager_cache_before_next_read_write'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$databasePath . '-journal']],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/site-next136.sqlite-journal']],
    'cache stale rejected' => [static fn (): mixed => $plan()['cache_stale_rejected'], true],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $currentSourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 7],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-hot-cache:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 8],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 5, 6]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'invalidated source reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][0]['reason'], 'stale_master_journal_source_id'],
    'invalidated epoch reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][1]['reason'], 'stale_master_journal_source_epoch'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][2]['reason'], 'dirty_cache_from_crashed_transaction'],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['invalidated_cache_entries'][3]['reason'], 'pinned_reader_cache_predates_hot_recovery'],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 6],
    'row one current match' => [static fn (): mixed => $plan()['cache_rows'][0]['image_matches_current_source'], true],
    'row two stale image' => [static fn (): mixed => $plan()['cache_rows'][1]['image_matches_current_source'], false],
    'row five dirty' => [static fn (): mixed => $plan()['cache_rows'][4]['dirty'], true],
    'row six pinned' => [static fn (): mixed => $plan()['cache_rows'][5]['pinned'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read one retained source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'hot-cache-after-master-recovery'],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read two refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next136 recovered wp_options root current source'],
    'read three miss after source invalidation' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read three current prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next136 recovered autoload index current source'],
    'read four miss unchanged database' => [static fn (): mixed => $plan()['next_reads'][3]['prefix'], 'next136 unchanged comments page before hot recovery'],
    'read five miss recovered dirty' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next136 recovered plugin settings current source'],
    'read six miss recovered pinned' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next136 recovered pinned reader current source'],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write two before current source' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next136 recovered wp_options root current source'],
    'write two after prefix' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next136 rewritten autoload option after hot cache refresh'],
    'write five before current source' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next136 recovered plugin settings current source'],
    'write journal current flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_source'], true],
    'final cache pages' => [static fn (): mixed => $plan()['final_cache_page_numbers'], [1, 2, 5]],
    'final cache dirty pages' => [static fn (): mixed => $plan()['final_cache_dirty_page_numbers'], [2, 5]],
    'final source page two write' => [static fn (): mixed => $plan()['final_sources'][2], 'next-write-after-master-journal-hot-cache'],
    'final source page three recovered' => [static fn (): mixed => $plan()['final_sources'][3], 'master-journal-hot-recovered-current-source'],
    'final prefix page five write' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next136 rewritten plugin setting after dirty cache invalidation'],
    'final bytes include write' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten plugin setting'), true],
    'final bytes exclude stale dirty cache' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale plugin settings page'), false],
    'operation first read current master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_hot_cache'],
    'operation second discards stale members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members_for_hot_cache'],
    'operation restores first page' => [static fn (): mixed => $plan()['operations'][2]['op'], 'restore_master_journal_hot_page'],
    'operation retains page one' => [static fn (): mixed => $plan()['operations'][7]['op'], 'retain_master_journal_hot_cache_page'],
    'operation refreshes page two' => [static fn (): mixed => $plan()['operations'][8]['op'], 'refresh_master_journal_hot_cache_page'],
    'operation invalidates source page' => [static fn (): mixed => $plan()['operations'][9]['op'], 'invalidate_master_journal_hot_cache_page'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][13]['op'], 'next_read_master_journal_hot_cache_hit'],
    'operation read miss' => [static fn (): mixed => $plan()['operations'][15]['op'], 'next_read_master_journal_hot_cache_miss'],
    'operation captures write' => [static fn (): mixed => $plan()['operations'][19]['op'], 'capture_next_write_before_image_from_hot_current_source'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'dependency cache recovery' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-recovery-current-source-next122', $plan()['dependencies'], true), true],
    'dependency savepoint cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next125', $plan()['dependencies'], true), true],
    'same members not stale' => [static fn (): mixed => $sameMembers()['cache_stale_rejected'], false],
    'same members operation no discard' => [static fn (): mixed => $sameMembers()['operations'][1]['op'], 'restore_master_journal_hot_page'],
    'duplicate current members collapsed' => [static fn (): mixed => $plan(null, null, [1], [], '__default__', $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/site-next136.sqlite-journal']],
    'blank cached members' => [static fn (): mixed => $plan(null, null, [1], [], '')['cached_members'], []],
    'no refresh invalidates stale page' => [static fn (): mixed => $noRefresh()['invalidated_cache_page_numbers'], [2, 3, 4, 5, 6]],
    'no refresh reason' => [static fn (): mixed => $noRefresh()['invalidated_cache_entries'][0]['reason'], 'stale_hot_cache_refresh_disabled'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal hot cache current source next136 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 7, true, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 7, true, null, ''),
    'missing current master rejected' => static fn () => $plan(null, null, null, null, '__default__', null),
    'wrong current master rejected' => static fn () => $plan(null, null, null, null, '__default__', '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', $databaseBytes . 'x'),
    'empty source id rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, ''),
    'bad source epoch rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 0),
    'empty next operations rejected' => static fn () => $plan(null, null, [], []),
    'empty recovered rejected' => static fn () => $plan([]),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'empty cache rejected' => static fn () => $plan(null, []),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => ['image' => $recovered[1], 'epoch' => -1]]),
    'bad read page rejected' => static fn () => $plan(null, null, [0], []),
    'zero write page rejected' => static fn () => $plan(null, null, [], [0 => $nextWrites[2]]),
    'short write image rejected' => static fn () => $plan(null, null, [], [2 => 'short']),
    'recovered outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'cache outside database rejected' => static fn () => $plan(null, [7 => ['image' => $page('outside')]]),
    'read outside database rejected' => static fn () => $plan(null, null, [7], []),
    'write outside database rejected' => static fn () => $plan(null, null, [], [7 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal hot cache current source next136 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

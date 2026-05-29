<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp/content/database/wp-next127.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$stale = [
    1 => $page('next127 stale header after interrupted writer'),
    2 => $page('next127 stale wp_options root after crash'),
    3 => $page('next127 stale active_plugins after crash'),
    4 => $page('next127 stale autoload index after crash'),
    5 => $page('next127 stale transient row after crash'),
    6 => $page('next127 stale commentmeta untouched page'),
];
$hot = [
    1 => $page('next127 hot recovered sqlite header'),
    2 => $page('next127 hot recovered wp_options root'),
    3 => $page('next127 hot recovered active_plugins'),
    4 => $page('next127 hot recovered autoload index'),
];
$cache = [
    ['page' => 2, 'image' => $page('next127 cache spill wp_options root retry'), 'bytes' => $pageSize, 'journaled' => true],
    ['page' => 3, 'image' => $page('next127 cache spill active_plugins retry'), 'bytes' => $pageSize, 'journaled' => true],
    ['page' => 4, 'image' => $page('next127 pinned autoload index dirty retry'), 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true],
    ['page' => 5, 'image' => $page('next127 cache spill transient retry'), 'bytes' => $pageSize, 'journaled' => true],
];
$databaseBytes = implode('', $stale);

$plan = static fn (
    array $hotPages = null,
    array $cachePages = null,
    bool $journalSynced = true,
    string $lock = 'reserved',
    bool $spillEnabled = true,
    ?int $maxSpillPages = 3,
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan(
    $databasePath,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $hotPages ?? $hot,
    $cachePages ?? $cache,
    8,
    3,
    $journalSynced,
    $lock,
    $spillEnabled,
    $maxSpillPages
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_cache_spill_current_source_next127'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovered_before_cache_spill_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['hot_journal_path'], $databasePath . '-journal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'page count' => [static fn (): mixed => $plan()['page_count'], 6],
    'delete hot journal' => [static fn (): mixed => $plan()['delete_hot_journal_after_recovery'], true],
    'hot recovered pages' => [static fn (): mixed => $plan()['hot_journal_recovered_page_numbers'], [1, 2, 3, 4]],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'current source mismatches' => [static fn (): mixed => $plan()['current_source_mismatch_pages'], []],
    'stale spill source pages' => [static fn (): mixed => $plan()['stale_spill_source_pages'], []],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill current lock' => [static fn (): mixed => $plan()['spill']['current']['lock'], 'reserved'],
    'spill next lock' => [static fn (): mixed => $plan()['spill']['next']['lock'], 'exclusive'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3, 5]],
    'spill remaining dirty pages' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], [4]],
    'recovered bytes include hot root' => [static fn (): mixed => str_contains($plan()['hot_journal_recovered_database_bytes'], 'next127 hot recovered wp_options root'), true],
    'recovered bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['hot_journal_recovered_database_bytes'], 'next127 stale wp_options root after crash'), false],
    'recovered bytes keep untouched page' => [static fn (): mixed => str_contains($plan()['hot_journal_recovered_database_bytes'], 'next127 stale commentmeta untouched page'), true],
    'spilled bytes include retry root' => [static fn (): mixed => str_contains($plan()['spilled_database_bytes'], 'next127 cache spill wp_options root retry'), true],
    'spilled bytes include retry active plugins' => [static fn (): mixed => str_contains($plan()['spilled_database_bytes'], 'next127 cache spill active_plugins retry'), true],
    'spilled bytes include retry transient' => [static fn (): mixed => str_contains($plan()['spilled_database_bytes'], 'next127 cache spill transient retry'), true],
    'spilled bytes keep pinned hot index' => [static fn (): mixed => str_contains($plan()['spilled_database_bytes'], 'next127 hot recovered autoload index'), true],
    'page two stale prefix' => [static fn (): mixed => $plan()['cache_page_sources'][2]['stale_prefix'], 'next127 stale wp_options root after crash'],
    'page two current prefix' => [static fn (): mixed => $plan()['cache_page_sources'][2]['current_source_prefix'], 'next127 hot recovered wp_options root'],
    'page two cache prefix' => [static fn (): mixed => $plan()['cache_page_sources'][2]['cache_prefix'], 'next127 cache spill wp_options root retry'],
    'page two was recovered' => [static fn (): mixed => $plan()['cache_page_sources'][2]['was_hot_journal_recovered'], true],
    'page two current verified' => [static fn (): mixed => $plan()['cache_page_sources'][2]['current_image_verified'], true],
    'page five not recovered' => [static fn (): mixed => $plan()['cache_page_sources'][5]['was_hot_journal_recovered'], false],
    'page five stale equals current cache false' => [static fn (): mixed => $plan()['cache_page_sources'][5]['cache_matches_current_source'], false],
    'operation open journal' => [static fn (): mixed => $plan()['operations'][0]['op'], 'open_hot_journal'],
    'operation restore first page' => [static fn (): mixed => $plan()['operations'][1]['op'], 'restore_hot_journal_page'],
    'operation delete journal' => [static fn (): mixed => $plan()['operations'][5]['op'], 'delete_hot_journal'],
    'operation promote lock' => [static fn (): mixed => $plan()['operations'][6]['op'], 'promote_lock'],
    'operation first spill page' => [static fn (): mixed => $plan()['operations'][7]['page'], 2],
    'operation second mark clean' => [static fn (): mixed => $plan()['operations'][10]['op'], 'mark_page_clean_in_cache'],
    'dependency next127' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-cache-spill-current-source-next127', $plan()['dependencies'], true), true],
    'dependency hot journal before spill' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery-before-cache-spill', $plan()['dependencies'], true), true],
    'dependency dirty spill' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-current-next71', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal cache spill current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$blockedCases = [
    'unsynced status' => [static fn (): mixed => $plan(null, null, false)['status'], 'pager_hot_journal_cache_spill_current_source_blocked_next127'],
    'unsynced reason' => [static fn (): mixed => $plan(null, null, false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'shared lock status' => [static fn (): mixed => $plan(null, null, true, 'shared')['status'], 'pager_hot_journal_cache_spill_current_source_blocked_next127'],
    'shared lock reason' => [static fn (): mixed => $plan(null, null, true, 'shared')['spill']['blocked_reasons'], ['exclusive_lock_unavailable']],
    'disabled spill status' => [static fn (): mixed => $plan(null, null, true, 'reserved', false)['status'], 'pager_hot_journal_cache_spill_current_source_blocked_next127'],
    'disabled spill reason' => [static fn (): mixed => $plan(null, null, true, 'reserved', false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'one page limit spills first page only' => [static fn (): mixed => $plan(null, null, true, 'reserved', true, 1)['spilled_page_numbers'], [2]],
    'one page limit leaves dirty pages' => [static fn (): mixed => $plan(null, null, true, 'reserved', true, 1)['spill']['next']['dirty_pages'], [3, 4, 5]],
    'stale cache image status' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $stale[2], 'bytes' => $pageSize, 'journaled' => true]])['status'], 'pager_hot_journal_cache_spill_current_source_blocked_next127'],
    'stale cache image page' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $stale[2], 'bytes' => $pageSize, 'journaled' => true]])['stale_spill_source_pages'], [2]],
    'stale cache blocked reason' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $stale[2], 'bytes' => $pageSize, 'journaled' => true]])['spill']['blocked_reasons'], ['cache_spill_disabled', 'stale_cache_image_before_hot_journal_recovery']],
    'mismatched current image status' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $cache[0]['image'], 'bytes' => $pageSize, 'journaled' => true, 'current_image' => $stale[2]]])['status'], 'pager_hot_journal_cache_spill_current_source_blocked_next127'],
    'mismatched current image page' => [static fn (): mixed => $plan(null, [['page' => 2, 'image' => $cache[0]['image'], 'bytes' => $pageSize, 'journaled' => true, 'current_image' => $stale[2]]])['current_source_mismatch_pages'], [2]],
    'no delete operation when retained' => [static fn (): mixed => array_column($plan(null, null, true, 'reserved', true, 3)['operations'], 'op'), ['open_hot_journal', 'restore_hot_journal_page', 'restore_hot_journal_page', 'restore_hot_journal_page', 'restore_hot_journal_page', 'delete_hot_journal', 'promote_lock', 'write_database_page', 'mark_page_clean_in_cache', 'write_database_page', 'mark_page_clean_in_cache', 'write_database_page', 'mark_page_clean_in_cache']],
];

foreach ($blockedCases as $name => [$callback, $expected]) {
    $tests['pager hot journal cache spill current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty path' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan('', $databaseBytes, $pageSize, $hot, $cache, 8, 3),
    'rejects bad page size' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan($databasePath, $databaseBytes, 500, $hot, $cache, 8, 3),
    'rejects empty database' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan($databasePath, '', $pageSize, $hot, $cache, 8, 3),
    'rejects unaligned database' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan($databasePath, $databaseBytes . 'x', $pageSize, $hot, $cache, 8, 3),
    'rejects empty hot journal' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, [], $cache, 8, 3),
    'rejects empty cache pages' => static fn () => SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, $hot, [], 8, 3),
    'rejects hot page zero' => static fn () => $plan([0 => $hot[1]]),
    'rejects hot page outside image' => static fn () => $plan([7 => $hot[1]]),
    'rejects short hot image' => static fn () => $plan([1 => 'short']),
    'rejects cache page zero' => static fn () => $plan(null, [['page' => 0, 'image' => $cache[0]['image']]]),
    'rejects duplicate cache page' => static fn () => $plan(null, [['page' => 2, 'image' => $cache[0]['image']], ['page' => 2, 'image' => $cache[1]['image']]]),
    'rejects cache page outside image' => static fn () => $plan(null, [['page' => 7, 'image' => $cache[0]['image']]]),
    'rejects short cache image' => static fn () => $plan(null, [['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan(null, [['page' => 2, 'image' => $cache[0]['image'], 'current_image' => 'short']]),
    'rejects invalid lock state' => static fn () => $plan(null, null, true, 'bad-lock'),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal cache spill current source next127 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

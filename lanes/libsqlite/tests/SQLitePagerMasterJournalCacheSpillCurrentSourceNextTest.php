<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next132.sqlite';
$masterPath = '/srv/wp-content/database/wp-next132.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/site-next132.sqlite-journal\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next132 stale header before master recovery'),
    2 => $page('next132 stale wp_options root before master recovery'),
    3 => $page('next132 stale autoload index before master recovery'),
    4 => $page('next132 comments page unchanged before master recovery'),
    5 => $page('next132 transient payload before master recovery'),
    6 => $page('next132 pinned termmeta before master recovery'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next132 recovered header current source'),
    2 => $page('next132 recovered wp_options root current source'),
    3 => $page('next132 recovered autoload index current source'),
    5 => $page('next132 recovered transient payload current source'),
    6 => $page('next132 recovered pinned termmeta current source'),
];
$cache = [
    2 => ['image' => $before[2], 'before' => $recovered[2], 'journaled' => true, 'dirty' => true, 'bytes' => 512],
    3 => ['image' => $recovered[3], 'before' => $recovered[3], 'journaled' => true, 'dirty' => true, 'source' => 'cache-after-master-recovery', 'bytes' => 512],
    4 => ['image' => $before[4], 'before' => $before[4], 'journaled' => false, 'dirty' => true, 'bytes' => 512],
    5 => ['image' => $page('next132 stale transient cache image'), 'before' => $before[5], 'journaled' => true, 'dirty' => true, 'bytes' => 512],
    6 => ['image' => $recovered[6], 'before' => $recovered[6], 'journaled' => true, 'dirty' => true, 'pinned' => true, 'bytes' => 512],
];

$plan = static fn (
    ?array $recoveredPages = null,
    ?array $cachePages = null,
    int $cacheSize = 9,
    int $threshold = 4,
    string $journalMode = 'delete',
    string $lockState = 'reserved',
    bool $refresh = true,
    ?int $max = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    mixed $master = '__default__',
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master === '__default__' ? $masterBytes : $master,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $cachePages ?? $cache,
    $cacheSize,
    $threshold,
    $journalMode,
    $lockState,
    $refresh,
    $max,
);

$walPlan = static fn (): array => $plan(null, null, 9, 4, 'wal', 'exclusive', true, 1);
$blocked = static fn (): array => $plan(null, null, 2, 4);
$noRefresh = static fn (): array => $plan(null, null, 9, 4, 'delete', 'reserved', false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-cache-spill-current-source-next132'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_refreshes_cache_before_safe_spill'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'cache size before' => [static fn (): mixed => $plan()['cache_size_before'], 9],
    'cache size after' => [static fn (): mixed => $plan()['cache_size_after'], 7],
    'spill threshold' => [static fn (): mixed => $plan()['spill_threshold'], 4],
    'lock before' => [static fn (): mixed => $plan()['lock_before'], 'reserved'],
    'lock after' => [static fn (): mixed => $plan()['lock_after'], 'exclusive'],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 5, 6]],
    'refreshed cache pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'retained cache pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [3, 4, 6]],
    'deferred cache pages' => [static fn (): mixed => $plan()['deferred_cache_page_numbers'], [5]],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2, 3]],
    'blocked reasons include before image' => [static fn (): mixed => in_array('cache_before_image_predates_master_journal_recovery', $plan()['blocked_reasons'], true), true],
    'blocked reasons exclude no candidates after spill' => [static fn (): mixed => in_array('no_master_journal_protected_dirty_pages', $plan()['blocked_reasons'], true), false],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 5],
    'row two eligible' => [static fn (): mixed => $plan()['cache_rows'][0]['eligible_for_spill'], true],
    'row two refreshed source' => [static fn (): mixed => $plan()['cache_rows'][0]['source_after'], 'master-journal-refreshed-cache-before-spill'],
    'row two cache prefix refreshed' => [static fn (): mixed => $plan()['cache_rows'][0]['cache_prefix'], 'next132 recovered wp_options root current source'],
    'row three retained source' => [static fn (): mixed => $plan()['cache_rows'][1]['source_after'], 'cache-after-master-recovery'],
    'row four not journaled' => [static fn (): mixed => $plan()['cache_rows'][2]['journaled'], false],
    'row four not eligible' => [static fn (): mixed => $plan()['cache_rows'][2]['eligible_for_spill'], false],
    'row five before mismatch' => [static fn (): mixed => $plan()['cache_rows'][3]['before_matches_current_source'], false],
    'row five not eligible' => [static fn (): mixed => $plan()['cache_rows'][3]['eligible_for_spill'], false],
    'row six pinned' => [static fn (): mixed => $plan()['cache_rows'][4]['pinned'], true],
    'row six not eligible' => [static fn (): mixed => $plan()['cache_rows'][4]['eligible_for_spill'], false],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 16],
    'operation first reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal'],
    'operation restores page one' => [static fn (): mixed => $plan()['operations'][1]['op'], 'restore_master_journal_page'],
    'operation refreshes page two' => [static fn (): mixed => $plan()['operations'][6]['op'], 'refresh_cache_page'],
    'operation retains page three' => [static fn (): mixed => $plan()['operations'][7]['op'], 'retain_cache_page'],
    'operation defers page five' => [static fn (): mixed => $plan()['operations'][9]['op'], 'defer_cache_spill'],
    'operation promotes lock' => [static fn (): mixed => $plan()['operations'][11]['op'], 'promote_lock'],
    'operation writes page two' => [static fn (): mixed => $plan()['operations'][12]['op'], 'write_database_page_after_master_journal'],
    'operation marks page two clean' => [static fn (): mixed => $plan()['operations'][13]['op'], 'mark_page_clean_in_cache'],
    'operation writes page three' => [static fn (): mixed => $plan()['operations'][14]['page_number'], 3],
    'operation final mark clean' => [static fn (): mixed => $plan()['operations'][15]['reason'] ?? $plan()['operations'][17]['reason'], 'master_journal_cache_spill_completed'],
    'final prefix one recovered' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next132 recovered header current source'],
    'final prefix two spilled recovered' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next132 recovered wp_options root current source'],
    'final prefix three spilled retained' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next132 recovered autoload index current source'],
    'final prefix four unchanged' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next132 comments page unchanged before master recovery'],
    'final prefix five recovered only' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next132 recovered transient payload current source'],
    'final source two spill' => [static fn (): mixed => $plan()['final_sources'][2], 'cache-spill-after-master-journal'],
    'final source three spill' => [static fn (): mixed => $plan()['final_sources'][3], 'cache-spill-after-master-journal'],
    'final source five master recovery' => [static fn (): mixed => $plan()['final_sources'][5], 'master-journal-recovered-current-source'],
    'final bytes include recovered options' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next132 recovered wp_options root current source'), true],
    'final bytes exclude stale options' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next132 stale wp_options root before master recovery'), false],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-spill-current-source-next132', $plan()['dependencies'], true), true],
    'dependency prior cache spill' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager-master-journal-cache-spill-current-source-next132'],
    'wal spilled limited' => [static fn (): mixed => $walPlan()['spilled_page_numbers'], [2]],
    'wal operation appends' => [static fn (): mixed => $walPlan()['operations'][11]['op'], 'append_wal_frame_after_master_journal'],
    'wal final source two not database spill' => [static fn (): mixed => $walPlan()['final_sources'][2], 'master-journal-recovered-current-source'],
    'wal lock remains exclusive' => [static fn (): mixed => $walPlan()['lock_after'], 'exclusive'],
    'blocked status below threshold' => [static fn (): mixed => $blocked()['status'], 'pager-master-journal-cache-spill-deferred-current-source-next132'],
    'blocked cache after unchanged' => [static fn (): mixed => $blocked()['cache_size_after'], 2],
    'blocked below threshold reason' => [static fn (): mixed => in_array('cache_below_spill_threshold', $blocked()['blocked_reasons'], true), true],
    'blocked lock shared reason' => [static fn (): mixed => in_array('exclusive_lock_unavailable', $plan(null, null, 9, 4, 'delete', 'shared')['blocked_reasons'], true), true],
    'no refresh defers page two' => [static fn (): mixed => $noRefresh()['deferred_cache_page_numbers'], [2, 5]],
    'no refresh reason' => [static fn (): mixed => in_array('stale_cache_image_not_refreshed', $noRefresh()['blocked_reasons'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal cache spill current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, ''),
    'missing master bytes rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, null, null),
    'wrong master bytes rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, null, '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, null, '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, null, '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, null, null, null, '__default__', $databaseBytes . 'x'),
    'negative cache size rejected' => static fn () => $plan(null, null, -1),
    'zero threshold rejected' => static fn () => $plan(null, null, 9, 0),
    'bad max rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved', true, 0),
    'bad journal mode rejected' => static fn () => $plan(null, null, 9, 4, 'off'),
    'bad lock state rejected' => static fn () => $plan(null, null, 9, 4, 'delete', 'reserved+'),
    'empty recovered rejected' => static fn () => $plan([]),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'empty cache rejected' => static fn () => $plan(null, []),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cache[2]]),
    'short cache image rejected' => static fn () => $plan(null, [2 => ['image' => 'short']]),
    'short before image rejected' => static fn () => $plan(null, [2 => ['image' => $cache[2]['image'], 'before' => 'short']]),
    'negative cache bytes rejected' => static fn () => $plan(null, [2 => ['image' => $cache[2]['image'], 'bytes' => -1]]),
    'recovered outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'cache outside database rejected' => static fn () => $plan(null, [7 => ['image' => $page('outside')]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal cache spill current source next132 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

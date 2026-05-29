<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next129.sqlite';
$masterPath = '/srv/wp-content/database/wp-next129.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/site-next129.sqlite-journal\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$stale = [
    1 => $page('next129 stale header before master recovery'),
    2 => $page('next129 stale wp_options root before master recovery'),
    3 => $page('next129 stale autoload index before master recovery'),
    4 => $page('next129 unchanged comments page before master recovery'),
    5 => $page('next129 stale transient payload before master recovery'),
];
$databaseBytes = implode('', $stale);
$masterRecovered = [
    1 => $page('next129 recovered header current source'),
    2 => $page('next129 recovered wp_options root current source'),
    3 => $page('next129 recovered autoload index current source'),
    5 => $page('next129 recovered transient payload current source'),
];
$walCache = [
    2 => ['image' => $stale[2], 'source' => 'wal-cache-before-master-recovery', 'dirty' => false, 'frame' => 11],
    3 => ['image' => $masterRecovered[3], 'source' => 'wal-cache-after-hot-recovery', 'dirty' => false, 'frame' => 12],
    5 => ['image' => $stale[5], 'source' => 'wal-cache-before-master-recovery', 'dirty' => true, 'frame' => 13],
];
$walAppend = [
    2 => $page('next129 appended wp_options root after cache refresh'),
    5 => $page('next129 appended transient payload after cache refresh'),
];
$checkpointPages = [1, 2, 3, 5];

$plan = static fn (
    ?array $recovered = null,
    ?array $cache = null,
    ?array $append = null,
    ?array $checkpoint = null,
    bool $refresh = true,
    ?string $path = null,
    ?string $masterJournalPath = null,
    mixed $master = '__default__',
    ?string $bytes = null,
    ?int $size = null,
): array => SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master === '__default__' ? $masterBytes : $master,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recovered ?? $masterRecovered,
    $cache ?? $walCache,
    $append ?? $walAppend,
    $checkpoint ?? $checkpointPages,
    $refresh,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-wal-cache-current-source-next129'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_invalidates_stale_wal_cache_before_next_append_checkpoint'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 5]],
    'stale cache pages' => [static fn (): mixed => $plan()['stale_cache_page_numbers'], [2, 5]],
    'refreshed cache pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2, 5]],
    'retained cache pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [3]],
    'wal append pages' => [static fn (): mixed => $plan()['wal_append_page_numbers'], [2, 5]],
    'checkpoint pages sorted unique' => [static fn (): mixed => $plan(null, null, null, [5, 2, 2, 1, 3])['checkpoint_page_numbers'], [1, 2, 3, 5]],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 3],
    'cache row two stale' => [static fn (): mixed => $plan()['cache_rows'][0]['stale_before_refresh'], true],
    'cache row two refreshed' => [static fn (): mixed => $plan()['cache_rows'][0]['refreshed'], true],
    'cache row two before prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['before_prefix'], 'next129 stale wp_options root before master recovery'],
    'cache row two current prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['current_prefix'], 'next129 recovered wp_options root current source'],
    'cache row two after prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['after_prefix'], 'next129 recovered wp_options root current source'],
    'cache row two source after' => [static fn (): mixed => $plan()['cache_rows'][0]['source_after'], 'master-journal-refreshed-wal-cache'],
    'cache row three retained' => [static fn (): mixed => $plan()['cache_rows'][1]['refreshed'], false],
    'cache row three not stale' => [static fn (): mixed => $plan()['cache_rows'][1]['stale_before_refresh'], false],
    'cache row three source retained' => [static fn (): mixed => $plan()['cache_rows'][1]['source_after'], 'wal-cache-after-hot-recovery'],
    'cache row five dirty retained flag' => [static fn (): mixed => $plan()['cache_rows'][2]['dirty'], true],
    'cache row five frame' => [static fn (): mixed => $plan()['cache_rows'][2]['frame'], 13],
    'final prefix one recovered' => [static fn (): mixed => $plan()['final_prefixes'][1], 'next129 recovered header current source'],
    'final prefix two append' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next129 appended wp_options root after cache refresh'],
    'final prefix three recovered retained' => [static fn (): mixed => $plan()['final_prefixes'][3], 'next129 recovered autoload index current source'],
    'final prefix four unchanged' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next129 unchanged comments page before master recovery'],
    'final prefix five append' => [static fn (): mixed => $plan()['final_prefixes'][5], 'next129 appended transient payload after cache refresh'],
    'final source one master' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-recovered-current-source'],
    'final source two wal append' => [static fn (): mixed => $plan()['final_sources'][2], 'wal-append-after-master-cache-refresh'],
    'final source three master' => [static fn (): mixed => $plan()['final_sources'][3], 'master-journal-recovered-current-source'],
    'final source four stale database untouched' => [static fn (): mixed => $plan()['final_sources'][4], 'database-before-master-journal-recovery'],
    'final source five wal append' => [static fn (): mixed => $plan()['final_sources'][5], 'wal-append-after-master-cache-refresh'],
    'final bytes include appended root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next129 appended wp_options root after cache refresh'), true],
    'final bytes include appended transient' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next129 appended transient payload after cache refresh'), true],
    'final bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next129 stale wp_options root before master recovery'), false],
    'final bytes keep unchanged comments' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next129 unchanged comments page before master recovery'), true],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 14],
    'operation first reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal'],
    'operation restores page one' => [static fn (): mixed => $plan()['operations'][1]['op'], 'restore_master_journal_page'],
    'operation refreshes page two' => [static fn (): mixed => $plan()['operations'][5]['op'], 'refresh_wal_cache_page'],
    'operation retains page three' => [static fn (): mixed => $plan()['operations'][6]['op'], 'retain_wal_cache_page'],
    'operation refreshes page five' => [static fn (): mixed => $plan()['operations'][7]['op'], 'refresh_wal_cache_page'],
    'operation appends page two' => [static fn (): mixed => $plan()['operations'][8]['op'], 'append_wal_frame_from_refreshed_cache'],
    'operation appends page five' => [static fn (): mixed => $plan()['operations'][9]['page_number'], 5],
    'operation checkpoints page one' => [static fn (): mixed => $plan()['operations'][10]['op'], 'checkpoint_page_from_current_source'],
    'operation checkpoints page five source' => [static fn (): mixed => $plan()['operations'][13]['source'], 'wal-append-after-master-cache-refresh'],
    'blocked status' => [static fn (): mixed => $plan(null, null, null, null, false)['status'], 'pager-master-journal-wal-cache-blocked-current-source-next129'],
    'blocked current source false' => [static fn (): mixed => $plan(null, null, null, null, false)['current_source_verified'], false],
    'blocked stale pages' => [static fn (): mixed => $plan(null, null, null, null, false)['stale_cache_page_numbers'], [2, 5]],
    'blocked operation blocks stale page' => [static fn (): mixed => $plan(null, null, null, null, false)['operations'][5]['op'], 'block_stale_wal_cache_page'],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-master-journal-wal-cache-current-source-next129', $plan()['dependencies'], true), true],
    'dependency invalidation' => [static fn (): mixed => in_array('sqlite-wal-cache-stale-page-invalidation', $plan()['dependencies'], true), true],
    'digest stable length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal wal cache current source next129 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, true, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, true, null, ''),
    'missing master bytes rejected' => static fn () => $plan(null, null, null, null, true, null, null, null),
    'wrong master bytes rejected' => static fn () => $plan(null, null, null, null, true, null, null, '/other.sqlite-journal'),
    'empty database rejected' => static fn () => $plan(null, null, null, null, true, null, null, '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, true, null, null, '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, true, null, null, '__default__', $databaseBytes . 'x'),
    'empty recovered rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty append rejected' => static fn () => $plan(null, null, []),
    'empty checkpoint rejected' => static fn () => $plan(null, null, null, []),
    'zero recovered page rejected' => static fn () => $plan([0 => $masterRecovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $walCache[2]]),
    'short cache image rejected' => static fn () => $plan(null, [2 => ['image' => 'short']]),
    'bad cache frame rejected' => static fn () => $plan(null, [2 => ['image' => $stale[2], 'frame' => 0]]),
    'bad checkpoint page rejected' => static fn () => $plan(null, null, null, [1, 0]),
    'recovered outside database rejected' => static fn () => $plan([6 => $page('outside')]),
    'cache outside database rejected' => static fn () => $plan(null, [6 => ['image' => $page('outside')]]),
    'append outside database rejected' => static fn () => $plan(null, null, [6 => $page('outside')]),
    'append without cache rejected' => static fn () => $plan(null, [2 => $walCache[2]], [5 => $walAppend[5]]),
    'checkpoint outside database rejected' => static fn () => $plan(null, null, null, [1, 6]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal wal cache current source next129 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

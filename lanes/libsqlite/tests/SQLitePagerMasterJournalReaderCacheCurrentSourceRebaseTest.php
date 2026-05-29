<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next158.sqlite';
$masterPath = '/srv/wp-content/database/wp-next158.sqlite-mj';
$cachedMaster = $databasePath . "-journal\n";
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/site-next158.sqlite-journal\n";
$currentSourceId = 'reader-cache-current-source-next158';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next158 stale schema page before reader recovery'),
    2 => $page('next158 stale wp_options root before reader recovery'),
    3 => $page('next158 stale autoload index before reader recovery'),
    4 => $page('next158 unchanged comments page before reader recovery'),
    5 => $page('next158 stale plugin settings before reader recovery'),
    6 => $page('next158 stale pinned reader before reader recovery'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next158 recovered schema page current source'),
    2 => $page('next158 recovered wp_options root current source'),
    3 => $page('next158 recovered autoload index current source'),
    5 => $page('next158 recovered plugin settings current source'),
    6 => $page('next158 recovered pinned reader current source'),
];
$readerCache = [
    1 => ['image' => $recovered[1], 'source_id' => $currentSourceId, 'epoch' => 9, 'end_frame' => 4, 'source' => 'reader-cache-after-current-recovery'],
    2 => ['image' => $before[2], 'source_id' => $currentSourceId, 'epoch' => 9, 'end_frame' => 4, 'source' => 'clean-reader-cache-before-recovery'],
    3 => ['image' => $recovered[3], 'source_id' => 'old-reader-source', 'epoch' => 9, 'end_frame' => 4, 'source' => 'old-source-reader-cache'],
    4 => ['image' => $before[4], 'source_id' => $currentSourceId, 'epoch' => 8, 'end_frame' => 4, 'source' => 'old-epoch-reader-cache'],
    5 => ['image' => $recovered[5], 'source_id' => $currentSourceId, 'epoch' => 9, 'end_frame' => 3, 'source' => 'old-frame-reader-cache'],
    6 => ['image' => $before[6], 'source_id' => $currentSourceId, 'epoch' => 9, 'end_frame' => 4, 'pinned' => true, 'source' => 'pinned-reader-before-recovery'],
];

$plan = static fn (
    ?array $recoveredPages = null,
    ?array $cachePages = null,
    ?array $readPages = null,
    mixed $cached = '__default__',
    mixed $current = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $source = null,
    int $epoch = 9,
    int $endFrame = 4,
    bool $refresh = true,
    ?string $path = null,
    ?string $masterJournalPath = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentSourceReaderCacheRebase(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $cached === '__default__' ? $cachedMaster : $cached,
    $current === '__default__' ? $currentMaster : $current,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $cachePages ?? $readerCache,
    $readPages ?? [1, 2, 3, 4, 5, 6],
    $source ?? $currentSourceId,
    $epoch,
    $endFrame,
    $refresh,
);

$sameMembers = static fn (): array => $plan(null, null, [1], $currentMaster, $currentMaster);
$noRefresh = static fn (): array => $plan(null, null, [2], '__default__', '__default__', null, null, null, 9, 4, false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next158'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_rebases_reader_cache_before_next_read'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$databasePath . '-journal']],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/site-next158.sqlite-journal']],
    'reader cache stale rejected' => [static fn (): mixed => $plan()['reader_cache_stale_rejected'], true],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $currentSourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 9],
    'current reader frame' => [static fn (): mixed => $plan()['current_source']['reader_end_frame'], 4],
    'next source id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-cache:'), true],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 10],
    'next reader frame' => [static fn (): mixed => $plan()['next_source']['reader_end_frame'], 4],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 5, 6]],
    'retained reader page' => [static fn (): mixed => $plan()['retained_reader_cache_page_numbers'], [1]],
    'refreshed reader page' => [static fn (): mixed => $plan()['refreshed_reader_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_reader_cache_page_numbers'], [3, 4, 5, 6]],
    'invalidated source reason' => [static fn (): mixed => $plan()['invalidated_reader_cache_entries'][0]['reason'], 'stale_reader_cache_source_id'],
    'invalidated epoch reason' => [static fn (): mixed => $plan()['invalidated_reader_cache_entries'][1]['reason'], 'stale_reader_cache_source_epoch'],
    'invalidated frame reason' => [static fn (): mixed => $plan()['invalidated_reader_cache_entries'][2]['reason'], 'stale_reader_end_frame_after_master_recovery'],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['invalidated_reader_cache_entries'][3]['reason'], 'pinned_reader_cache_predates_master_recovery'],
    'reader row count' => [static fn (): mixed => count($plan()['reader_cache_rows']), 6],
    'row one matches current' => [static fn (): mixed => $plan()['reader_cache_rows'][0]['image_matches_current_source'], true],
    'row two stale image' => [static fn (): mixed => $plan()['reader_cache_rows'][1]['image_matches_current_source'], false],
    'row five old frame' => [static fn (): mixed => $plan()['reader_cache_rows'][4]['end_frame_before'], 3],
    'row six pinned' => [static fn (): mixed => $plan()['reader_cache_rows'][5]['pinned'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['reader_cache_hit'], true],
    'read one retained source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-after-current-recovery'],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['reader_cache_hit'], true],
    'read two refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next158 recovered wp_options root current source'],
    'read three miss after source invalidation' => [static fn (): mixed => $plan()['next_reads'][2]['reader_cache_hit'], false],
    'read three recovered prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next158 recovered autoload index current source'],
    'read four unchanged prefix' => [static fn (): mixed => $plan()['next_reads'][3]['prefix'], 'next158 unchanged comments page before reader recovery'],
    'read five miss despite matching old frame' => [static fn (): mixed => $plan()['next_reads'][4]['reader_cache_hit'], false],
    'read six miss pinned recovered prefix' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next158 recovered pinned reader current source'],
    'final reader cache pages' => [static fn (): mixed => $plan()['final_reader_cache_page_numbers'], [1, 2]],
    'final source page one recovered' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-reader-recovered-current-source'],
    'final source page four old database' => [static fn (): mixed => $plan()['final_sources'][4], 'database-before-master-journal-reader-recovery'],
    'final prefix page two recovered' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next158 recovered wp_options root current source'],
    'final bytes include recovered settings' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next158 recovered plugin settings current source'), true],
    'final bytes exclude stale root' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'next158 stale wp_options root'), false],
    'operation first read current master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache'],
    'operation second discards stale members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members_for_reader_cache'],
    'operation restores page one' => [static fn (): mixed => $plan()['operations'][2]['op'], 'restore_master_journal_reader_page'],
    'operation retains page one' => [static fn (): mixed => $plan()['operations'][7]['op'], 'retain_master_journal_reader_cache_page'],
    'operation refreshes page two' => [static fn (): mixed => $plan()['operations'][8]['op'], 'refresh_master_journal_reader_cache_page'],
    'operation invalidates source page' => [static fn (): mixed => $plan()['operations'][9]['op'], 'invalidate_master_journal_reader_cache_page'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][13]['op'], 'next_read_master_journal_reader_cache_hit'],
    'operation read miss' => [static fn (): mixed => $plan()['operations'][15]['op'], 'next_read_master_journal_reader_cache_miss'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next158', $plan()['dependencies'], true), true],
    'dependency cache recovery' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-recovery-current-source-next122', $plan()['dependencies'], true), true],
    'dependency hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'same members not stale' => [static fn (): mixed => $sameMembers()['reader_cache_stale_rejected'], false],
    'same members operation no discard' => [static fn (): mixed => $sameMembers()['operations'][1]['op'], 'restore_master_journal_reader_page'],
    'duplicate current members collapsed' => [static fn (): mixed => $plan(null, null, [1], '__default__', $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/site-next158.sqlite-journal']],
    'blank cached members' => [static fn (): mixed => $plan(null, null, [1], '')['cached_members'], []],
    'no refresh invalidates clean stale page' => [static fn (): mixed => $noRefresh()['invalidated_reader_cache_page_numbers'], [2, 3, 4, 5, 6]],
    'no refresh reason' => [static fn (): mixed => $noRefresh()['invalidated_reader_cache_entries'][0]['reason'], 'stale_reader_cache_refresh_disabled'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next158 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, null, 9, 4, true, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, null, 9, 4, true, null, ''),
    'missing current master rejected' => static fn () => $plan(null, null, null, '__default__', null),
    'wrong current master rejected' => static fn () => $plan(null, null, null, '__default__', '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', $databaseBytes . 'x'),
    'empty source id rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, ''),
    'bad source epoch rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, null, 0),
    'bad reader frame rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, null, 9, -1),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'empty recovered rejected' => static fn () => $plan([]),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'empty reader cache rejected' => static fn () => $plan(null, []),
    'zero reader cache page rejected' => static fn () => $plan(null, [0 => $readerCache[1]]),
    'short reader cache image rejected' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'bad reader cache epoch rejected' => static fn () => $plan(null, [1 => ['image' => $recovered[1], 'epoch' => -1]]),
    'bad reader cache frame rejected' => static fn () => $plan(null, [1 => ['image' => $recovered[1], 'end_frame' => -1]]),
    'bad read page rejected' => static fn () => $plan(null, null, [0]),
    'recovered outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'reader outside database rejected' => static fn () => $plan(null, [7 => ['image' => $page('outside')]]),
    'read outside database rejected' => static fn () => $plan(null, null, [7]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next158 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next159.sqlite';
$masterPath = '/srv/wp-content/database/wp-next159.sqlite-mj';
$cachedMaster = $databasePath . "-journal\n/srv/wp-content/database/old-plugin-next159.sqlite-journal\n";
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/current-plugin-next159.sqlite-journal\n";
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next159 stale schema before current master journal'),
    2 => $page('next159 stale wp_options root before current master journal'),
    3 => $page('next159 stale active_plugins before current master journal'),
    4 => $page('next159 stale plugin settings before current master journal'),
    5 => $page('next159 comments page unchanged before current master journal'),
    6 => $page('next159 transient page before current master journal'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next159 recovered schema from current master journal'),
    2 => $page('next159 recovered wp_options root from current master journal'),
    3 => $page('next159 recovered active_plugins from current master journal'),
    4 => $page('next159 recovered plugin settings from current master journal'),
    6 => $page('next159 recovered transient from current master journal'),
];
$sourceBefore = 'next159-before-current-master-source';
$recoveredId = static fn (): string => 'master-reader-cache:' . hash('sha256', $masterPath . '|' . $databasePath . '-journal|/srv/wp-content/database/current-plugin-next159.sqlite-journal');
$readerCache = static fn (): array => [
    1 => ['label' => 'schema-retained', 'image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 7],
    2 => ['label' => 'root-refreshable', 'image' => $before[2], 'source_id' => $sourceBefore, 'epoch' => 7],
    3 => ['label' => 'pinned-active-plugins', 'image' => $before[3], 'source_id' => $sourceBefore, 'epoch' => 7, 'pinned' => true],
    4 => ['label' => 'dirty-plugin-settings', 'image' => $before[4], 'source_id' => $sourceBefore, 'epoch' => 7, 'dirty' => true],
    5 => ['label' => 'wrong-source-comments', 'image' => $before[5], 'source_id' => 'old-reader-source', 'epoch' => 7],
    6 => ['label' => 'wrong-epoch-transient', 'image' => $recovered[6], 'source_id' => $sourceBefore, 'epoch' => 6],
];
$nextWrites = [
    3 => $page('next159 rewritten active_plugins after reader cache rebase'),
    4 => $page('next159 rewritten plugin settings after reader cache rebase'),
];

$plan = static fn (
    ?array $masterRecovered = null,
    ?array $cachePages = null,
    ?array $readPages = null,
    ?array $writePages = null,
    mixed $cachedBytes = '__default__',
    mixed $currentBytes = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $source = null,
    int $epoch = 7,
    ?string $path = null,
    ?string $masterJournalPath = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderCacheRebaseWithWrites(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $cachedBytes === '__default__' ? $cachedMaster : $cachedBytes,
    $currentBytes === '__default__' ? $currentMaster : $currentBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $masterRecovered ?? $recovered,
    $cachePages ?? $readerCache(),
    $readPages ?? [1, 2, 3, 4, 5, 6],
    $writePages ?? $nextWrites,
    $source ?? $sourceBefore,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next159'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'current_master_journal_membership_rebases_reader_cache_before_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$databasePath . '-journal', '/srv/wp-content/database/old-plugin-next159.sqlite-journal']],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/current-plugin-next159.sqlite-journal']],
    'cached membership stale' => [static fn (): mixed => $plan()['cached_membership_stale'], true],
    'input source id' => [static fn (): mixed => $plan()['input_source']['id'], $sourceBefore],
    'input epoch' => [static fn (): mixed => $plan()['input_source']['epoch'], 7],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], $recoveredId()],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 8],
    'master recovered pages' => [static fn (): mixed => $plan()['master_recovered_page_numbers'], [1, 2, 3, 4, 6]],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 6],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'row retained reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_current_master_source'],
    'row refreshed reason' => [static fn (): mixed => $plan()['reader_rows'][1]['reason'], 'reader_cache_refreshed_from_current_master_source'],
    'row pinned reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'pinned_reader_cache_image_predates_current_master_source'],
    'row dirty reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'dirty_reader_cache_from_failed_master_transaction'],
    'row source reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reason'], 'reader_cache_source_id_predates_current_master_source'],
    'row epoch reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reason'], 'reader_cache_epoch_predates_current_master_source'],
    'row refreshed prefix' => [static fn (): mixed => $plan()['reader_rows'][1]['current_prefix'], 'next159 recovered wp_options root from current master journal'],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'read retained cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read invalidated cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read invalidated prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next159 recovered active_plugins from current master journal'],
    'read unchanged prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next159 comments page unchanged before current master journal'],
    'read source id' => [static fn (): mixed => $plan()['next_reads'][0]['source_id'], $recoveredId()],
    'read epoch' => [static fn (): mixed => $plan()['next_reads'][0]['epoch'], 8],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next159 recovered active_plugins from current master journal'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next159 rewritten active_plugins after reader cache rebase'],
    'write settings before' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next159 recovered plugin settings from current master journal'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_master_source'], true],
    'final source page two recovered' => [static fn (): mixed => $plan()['final_sources'][2], 'master-journal-reader-cache-current-source'],
    'final source page three write' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-master-journal-reader-cache'],
    'final prefix page four write' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next159 rewritten plugin settings after reader cache rebase'],
    'final bytes include rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final bytes exclude stale active plugins' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale active_plugins'), false],
    'operation first master read' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache'],
    'operation discard stale members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_members_for_reader_cache'],
    'operation restore page one' => [static fn (): mixed => $plan()['operations'][2]['op'], 'restore_master_journal_page_before_reader_cache_check'],
    'operation retain cache' => [static fn (): mixed => $plan()['operations'][7]['op'], 'retain_reader_cache_after_master_journal_source_check'],
    'operation refresh cache' => [static fn (): mixed => $plan()['operations'][8]['op'], 'refresh_reader_cache_from_master_journal_current_source'],
    'operation invalidate pinned' => [static fn (): mixed => $plan()['operations'][9]['op'], 'invalidate_reader_cache_after_master_journal_recovery'],
    'operation next read' => [static fn (): mixed => $plan()['operations'][13]['op'], 'next_read_uses_rebased_reader_cache'],
    'operation cache miss read' => [static fn (): mixed => $plan()['operations'][15]['op'], 'next_read_uses_recovered_master_current_source'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][19]['op'], 'capture_next_write_before_image_after_reader_cache_rebase'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next159', $plan()['dependencies'], true), true],
    'dependency cache recovery' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-recovery-current-source-next122', $plan()['dependencies'], true), true],
    'dependency hot cache' => [static fn (): mixed => in_array('sqlite-pager-master-journal-hot-cache-current-source-next136', $plan()['dependencies'], true), true],
    'non stale cached members' => [static fn (): mixed => $plan(null, null, [1], [], $currentMaster)['cached_membership_stale'], false],
    'duplicate current members collapsed' => [static fn (): mixed => $plan(null, null, [1], [], '__default__', $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/current-plugin-next159.sqlite-journal']],
    'all cache valid no reopen' => [static fn (): mixed => $plan(null, [1 => ['image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 7]], [1], [])['requires_reader_reopen'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next159 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 7, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 7, null, ''),
    'missing current master rejected' => static fn () => $plan(null, null, null, null, '__default__', null),
    'wrong current master rejected' => static fn () => $plan(null, null, null, null, '__default__', '/other.sqlite-journal'),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, 500),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', $databaseBytes . 'x'),
    'empty reader cache rejected' => static fn () => $plan(null, []),
    'empty next work rejected' => static fn () => $plan(null, null, [], []),
    'empty source rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, ''),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, '__default__', '__default__', null, null, null, 0),
    'empty recovered rejected' => static fn () => $plan([]),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered image rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => ['image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 7]]),
    'short cache image rejected' => static fn () => $plan(null, [1 => ['image' => 'short', 'source_id' => $sourceBefore, 'epoch' => 7]]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => ['image' => $recovered[1], 'source_id' => '', 'epoch' => 7]]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => ['image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 0]]),
    'bad read page rejected' => static fn () => $plan(null, null, [0], []),
    'zero write page rejected' => static fn () => $plan(null, null, [], [0 => $nextWrites[3]]),
    'short write image rejected' => static fn () => $plan(null, null, [], [3 => 'short']),
    'recovered outside database rejected' => static fn () => $plan([7 => $page('outside')]),
    'cache outside database rejected' => static fn () => $plan(null, [7 => ['image' => $page('outside'), 'source_id' => $sourceBefore, 'epoch' => 7]]),
    'read outside database rejected' => static fn () => $plan(null, null, [7], []),
    'write outside database rejected' => static fn () => $plan(null, null, [], [7 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next159 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

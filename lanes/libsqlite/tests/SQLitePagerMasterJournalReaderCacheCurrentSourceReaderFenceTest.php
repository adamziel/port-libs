<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/tmp/wp-content/database/.ht.sqlite';
$master = '/tmp/wp-content/database/.ht.sqlite-mj160';
$currentMembers = [
    $database . '-journal',
    '/tmp/wp-content/database/wp_comments.sqlite-journal',
    '/tmp/wp-content/database/wp_options_meta.sqlite-journal',
];
$cachedMembers = [
    $database . '-journal',
    '/tmp/wp-content/database/old-plugin-cache.sqlite-journal',
];
$currentBytes = implode("\n", $currentMembers) . "\n";
$cachedBytes = implode("\n", $cachedMembers) . "\n";
$sourceId = 'master-reader-current:160';
$epoch = 160;
$currentPages = [
    1 => $page('reader fence schema page current'),
    2 => $page('reader fence active_plugins recovered current'),
    3 => $page('reader fence plugin settings current'),
    4 => $page('reader fence autoload index current'),
    5 => $page('reader fence comments attachment current'),
    6 => $page('reader fence options meta current'),
];
$cache = [
    1 => ['image' => $currentPages[1], 'source' => 'reader-cache', 'source_id' => $sourceId, 'epoch' => $epoch, 'master_members' => $currentMembers],
    2 => ['image' => $page('reader fence stale active_plugins reader'), 'source' => 'reader-cache', 'source_id' => $sourceId, 'epoch' => $epoch, 'master_members' => $currentMembers],
    3 => ['image' => $currentPages[3], 'source' => 'pinned-reader-cache', 'source_id' => $sourceId, 'epoch' => $epoch, 'pinned' => true, 'master_members' => $cachedMembers],
    4 => ['image' => $currentPages[4], 'source' => 'dirty-reader-cache', 'source_id' => $sourceId, 'epoch' => $epoch, 'dirty' => true, 'master_members' => $currentMembers],
    5 => ['image' => $currentPages[5], 'source' => 'old-source-reader-cache', 'source_id' => 'master-reader-old:159', 'epoch' => $epoch, 'master_members' => $currentMembers],
    6 => ['image' => $currentPages[6], 'source' => 'old-epoch-reader-cache', 'source_id' => $sourceId, 'epoch' => 159, 'master_members' => $currentMembers],
    7 => ['image' => $page('reader fence ghost reader cache page'), 'source' => 'ghost-reader-cache', 'source_id' => $sourceId, 'epoch' => $epoch, 'master_members' => $currentMembers],
];
$readPages = [1, 2, 3, 4, 5, 6];

$plan = static fn (
    ?string $cached = null,
    ?string $current = null,
    ?array $pages = null,
    ?array $reader = null,
    ?array $reads = null,
    string $source = 'master-reader-current:160',
    int $epochArg = 160,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderFence(
    $database,
    $master,
    $cached ?? $cachedBytes,
    $current ?? $currentBytes,
    $pageSize,
    $pages ?? $currentPages,
    $reader ?? $cache,
    $reads ?? $readPages,
    $source,
    $epochArg,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_master_journal_reader_cache_current_source_reader_fence'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_cache_pages_are_fenced_by_current_master_journal_membership_and_page_digests'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], $cachedMembers],
    'current members' => [static fn (): mixed => $plan()['current_members'], $currentMembers],
    'cache stale rejected' => [static fn (): mixed => $plan()['cache_stale_rejected'], true],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 160],
    'current page numbers' => [static fn (): mixed => $plan()['current_source']['page_numbers'], [1, 2, 3, 4, 5, 6]],
    'digest count' => [static fn (): mixed => count($plan()['current_source']['digests']), 6],
    'retained pages' => [static fn (): mixed => $plan()['cache']['retained_page_numbers'], [1]],
    'invalidated pages' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5, 6, 7]],
    'invalidated image reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'reader_cache_image_mismatch'],
    'invalidated pinned stale reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'pinned_reader_cache_uses_stale_master_members'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'dirty_reader_cache_from_failed_writer'],
    'invalidated source reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'reader_cache_source_id_mismatch'],
    'invalidated epoch reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][4]['reason'], 'reader_cache_source_epoch_mismatch'],
    'invalidated absent reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][5]['reason'], 'reader_cache_page_absent_from_current_source'],
    'row count' => [static fn (): mixed => count($plan()['cache']['rows']), 7],
    'row one matches members' => [static fn (): mixed => $plan()['cache']['rows'][0]['master_members_match'], true],
    'row two image mismatch' => [static fn (): mixed => $plan()['cache']['rows'][1]['image_matches_current_source'], false],
    'row three member mismatch' => [static fn (): mixed => $plan()['cache']['rows'][2]['master_members_match'], false],
    'read pages' => [static fn (): mixed => $plan()['read_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'read one cache hit' => [static fn (): mixed => $plan()['reads'][0]['cache_hit'], true],
    'read two cache miss' => [static fn (): mixed => $plan()['reads'][1]['cache_hit'], false],
    'read three source' => [static fn (): mixed => $plan()['reads'][2]['source'], 'current-master-journal-reader-source'],
    'read prefix current' => [static fn (): mixed => $plan()['read_prefixes'][2], 'reader fence active_plugins recovered current'],
    'read digest match' => [static fn (): mixed => $plan()['reads'][5]['matches_current_source_digest'], true],
    'cache hit map retained' => [static fn (): mixed => $plan()['read_cache_hits'][1], true],
    'cache hit map stale miss' => [static fn (): mixed => $plan()['read_cache_hits'][2], false],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache'],
    'discard operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_reader_members'],
    'retain operation' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_master_journal_reader_cache_page'],
    'invalidate operation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_master_journal_reader_cache_page'],
    'read hit operation' => [static fn (): mixed => $plan()['operations'][9]['op'], 'read_master_journal_reader_cache_hit'],
    'read miss operation' => [static fn (): mixed => $plan()['operations'][10]['op'], 'read_master_journal_reader_cache_miss_current_source'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 15],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-reader-fence', $plan()['dependencies'], true), true],
    'dependency source fence' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-reader-fence', $plan()['dependencies'], true), true],
    'dependency digest fence' => [static fn (): mixed => in_array('sqlite-pager-cache-current-source-digest', $plan()['dependencies'], true), true],
    'fresh cached members are not stale' => [static fn (): mixed => $plan($currentBytes, null)['cache_stale_rejected'], false],
    'unpinned stale members produce member mismatch' => [static fn (): mixed => $plan(null, null, null, [3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 160, 'master_members' => $cachedMembers]], [3])['cache']['invalidated_entries'][0]['reason'], 'reader_cache_master_members_mismatch'],
    'empty entry members allow digest retention' => [static fn (): mixed => $plan(null, null, null, [2 => ['image' => $currentPages[2], 'source_id' => $sourceId, 'epoch' => 160]], [2])['cache']['retained_page_numbers'], [2]],
    'current cache can serve all reads' => [static fn (): mixed => $plan($currentBytes, null, null, [
        1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 160, 'master_members' => $currentMembers],
        2 => ['image' => $currentPages[2], 'source_id' => $sourceId, 'epoch' => 160, 'master_members' => $currentMembers],
    ], [1, 2])['read_cache_hits'], [1 => true, 2 => true]],
    'partial cache misses fall back current source' => [static fn (): mixed => $plan($currentBytes, null, null, [1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 160, 'master_members' => $currentMembers]], [1, 2])['read_cache_hits'], [1 => true, 2 => false]],
    'duplicate current members collapse' => [static fn (): mixed => $plan(null, $database . "-journal\n" . $database . "-journal\n")['current_members'], [$database . '-journal']],
    'blank cached members normalize empty' => [static fn (): mixed => $plan(" \n\t\n")['cached_members'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source reader fence ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderFence('', $master, $cachedBytes, $currentBytes, $pageSize, $currentPages, $cache, $readPages, $sourceId, $epoch),
    'rejects empty master path' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderFence($database, '', $cachedBytes, $currentBytes, $pageSize, $currentPages, $cache, $readPages, $sourceId, $epoch),
    'rejects empty source id' => static fn () => $plan(null, null, null, null, null, '', 160),
    'rejects blank current master' => static fn () => $plan(null, " \n"),
    'rejects bad page size' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderFence($database, $master, $cachedBytes, $currentBytes, 0, $currentPages, $cache, $readPages, $sourceId, $epoch),
    'rejects bad epoch' => static fn () => $plan(null, null, null, null, null, $sourceId, 0),
    'rejects empty current pages' => static fn () => $plan(null, null, []),
    'rejects empty cache' => static fn () => $plan(null, null, null, []),
    'rejects empty reads' => static fn () => $plan(null, null, null, null, []),
    'rejects unreferenced database journal' => static fn () => $plan(null, "/tmp/other.sqlite-journal\n"),
    'rejects zero current page' => static fn () => $plan(null, null, [0 => $currentPages[1]]),
    'rejects short current page' => static fn () => $plan(null, null, [1 => 'short']),
    'rejects zero cache page' => static fn () => $plan(null, null, null, [0 => ['image' => $currentPages[1]]]),
    'rejects short cache page' => static fn () => $plan(null, null, null, [1 => ['image' => 'short']]),
    'rejects bad cache epoch' => static fn () => $plan(null, null, null, [1 => ['image' => $currentPages[1], 'epoch' => -1]]),
    'rejects nonlist members' => static fn () => $plan(null, null, null, [1 => ['image' => $currentPages[1], 'master_members' => 'bad']]),
    'rejects bad read page' => static fn () => $plan(null, null, null, null, [0]),
    'rejects read outside current source' => static fn () => $plan(null, null, null, null, [8]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source reader fence ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

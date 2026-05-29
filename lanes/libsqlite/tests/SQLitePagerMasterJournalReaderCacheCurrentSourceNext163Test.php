<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/tmp/wp-content/database/.ht.sqlite';
$master = '/tmp/wp-content/database/.ht.sqlite-mj163';
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
$currentSourceId = 'master-reader-current:163';
$nextSourceId = 'master-reader-next:163';
$currentEpoch = 163;
$nextEpoch = 164;
$currentPages = [
    1 => $page('next163 schema page recovered current'),
    2 => $page('next163 active_plugins recovered current'),
    3 => $page('next163 plugin settings recovered current'),
    4 => $page('next163 autoload index recovered current'),
    5 => $page('next163 comments attachment recovered current'),
    6 => $page('next163 options meta recovered current'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $page('next163 active_plugins next committed'),
    3 => $currentPages[3],
    4 => $page('next163 autoload index next committed'),
    5 => $currentPages[5],
    6 => $currentPages[6],
    7 => $page('next163 newly allocated option page'),
];
$cache = [
    1 => ['image' => $currentPages[1], 'source' => 'reader-cache-current', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'master_members' => $currentMembers],
    2 => ['image' => $currentPages[2], 'source' => 'reader-cache-current', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'master_members' => $currentMembers],
    3 => ['image' => $currentPages[3], 'source' => 'pinned-reader-cache', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'pinned' => true, 'master_members' => $currentMembers],
    4 => ['image' => $currentPages[4], 'source' => 'pinned-reader-cache', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'pinned' => true, 'master_members' => $currentMembers],
    5 => ['image' => $currentPages[5], 'source' => 'dirty-reader-cache', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'dirty' => true, 'master_members' => $currentMembers],
    6 => ['image' => $page('next163 stale options meta reader'), 'source' => 'reader-cache-current', 'source_id' => $currentSourceId, 'epoch' => $currentEpoch, 'master_members' => $currentMembers],
];
$readPages = [1, 2, 3, 4, 5, 6, 7];

$plan = static fn (
    ?string $cached = null,
    ?string $current = null,
    ?array $currentSet = null,
    ?array $nextSet = null,
    ?array $reader = null,
    ?array $reads = null,
    string $currentSource = 'master-reader-current:163',
    string $nextSource = 'master-reader-next:163',
    int $currentEpochArg = 163,
    int $nextEpochArg = 164,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext163(
    $database,
    $master,
    $cached ?? $cachedBytes,
    $current ?? $currentBytes,
    $pageSize,
    $currentSet ?? $currentPages,
    $nextSet ?? $nextPages,
    $reader ?? $cache,
    $reads ?? $readPages,
    $currentSource,
    $nextSource,
    $currentEpochArg,
    $nextEpochArg,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_master_journal_reader_cache_current_source_next163'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_cache_pages_are_reused_only_when_the_recovered_current_source_still_matches_the_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], $cachedMembers],
    'current members' => [static fn (): mixed => $plan()['current_members'], $currentMembers],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $currentSourceId],
    'next source id' => [static fn (): mixed => $plan()['next_source']['id'], $nextSourceId],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 163],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 164],
    'current digest count' => [static fn (): mixed => count($plan()['current_source']['digests']), 6],
    'next digest count' => [static fn (): mixed => count($plan()['next_source']['digests']), 7],
    'read pages' => [static fn (): mixed => $plan()['read_page_numbers'], [1, 2, 3, 4, 5, 6, 7]],
    'read cache hits' => [static fn (): mixed => $plan()['read_cache_hits'], [1 => true, 2 => false, 3 => true, 4 => false, 5 => false, 6 => false, 7 => false]],
    'read source retained' => [static fn (): mixed => $plan()['read_sources'][1], 'reader-cache-current'],
    'read source changed' => [static fn (): mixed => $plan()['read_sources'][2], 'next-master-journal-reader-source'],
    'pinned unchanged is retained' => [static fn (): mixed => $plan()['read_sources'][3], 'pinned-reader-cache'],
    'pinned changed falls back next' => [static fn (): mixed => $plan()['read_sources'][4], 'next-master-journal-reader-source'],
    'dirty falls back next' => [static fn (): mixed => $plan()['read_sources'][5], 'next-master-journal-reader-source'],
    'stale image falls back next' => [static fn (): mixed => $plan()['read_sources'][6], 'next-master-journal-reader-source'],
    'missing cache falls back next' => [static fn (): mixed => $plan()['read_sources'][7], 'next-master-journal-reader-source'],
    'retained reason' => [static fn (): mixed => $plan()['read_reasons'][1], 'reader_cache_reused_for_unchanged_next_source_page'],
    'changed reason' => [static fn (): mixed => $plan()['read_reasons'][2], 'next_source_page_changed_after_master_journal_recovery'],
    'pinned changed reason' => [static fn (): mixed => $plan()['read_reasons'][4], 'pinned_reader_cache_blocks_changed_next_source'],
    'dirty reason' => [static fn (): mixed => $plan()['read_reasons'][5], 'dirty_reader_cache_blocks_next_source_read'],
    'image mismatch reason' => [static fn (): mixed => $plan()['read_reasons'][6], 'reader_cache_does_not_match_recovered_current_source'],
    'missing reason' => [static fn (): mixed => $plan()['read_reasons'][7], 'reader_cache_missing_next_source_page'],
    'blocked pages' => [static fn (): mixed => $plan()['blocked_page_numbers'], [4, 5]],
    'blocker count' => [static fn (): mixed => count($plan()['blockers']), 2],
    'first blocker reason' => [static fn (): mixed => $plan()['blockers'][0]['reason'], 'pinned_reader_cache_blocks_changed_next_source'],
    'second blocker reason' => [static fn (): mixed => $plan()['blockers'][1]['reason'], 'dirty_reader_cache_blocks_next_source_read'],
    'decision count' => [static fn (): mixed => count($plan()['decisions']), 7],
    'decision one current matches next' => [static fn (): mixed => $plan()['decisions'][0]['current_matches_next'], true],
    'decision two current differs next' => [static fn (): mixed => $plan()['decisions'][1]['current_matches_next'], false],
    'decision four is blocked' => [static fn (): mixed => $plan()['decisions'][3]['blocked'], true],
    'decision seven prefix' => [static fn (): mixed => $plan()['decisions'][6]['next_prefix'], 'next163 newly allocated option page'],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_before_next_reader_source'],
    'discard operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'discard_cached_master_journal_for_next_reader_source'],
    'cache hit operation' => [static fn (): mixed => $plan()['operations'][2]['op'], 'read_next_reader_source_from_current_cache'],
    'cache miss operation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'read_next_reader_source_from_next_pages'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 9],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next163', $plan()['dependencies'], true), true],
    'dependency transition' => [static fn (): mixed => in_array('sqlite-master-journal-current-to-next-reader-source', $plan()['dependencies'], true), true],
    'dependency digest' => [static fn (): mixed => in_array('sqlite-pager-cache-next-source-digest', $plan()['dependencies'], true), true],
    'fresh cached master skips discard operation' => [static fn (): mixed => $plan($currentBytes)['operations'][1]['op'], 'read_next_reader_source_from_current_cache'],
    'empty cached master skips discard operation' => [static fn (): mixed => $plan('')['operations'][1]['op'], 'read_next_reader_source_from_current_cache'],
    'duplicate current members collapse' => [static fn (): mixed => $plan(null, $database . "-journal\n" . $database . "-journal\n")['current_members'], [$database . '-journal']],
    'empty cache members permit page reuse' => [static fn (): mixed => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'source_id' => $currentSourceId, 'epoch' => 163]], [1])['read_cache_hits'], [1 => true]],
    'stale member mismatch reason' => [static fn (): mixed => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'source_id' => $currentSourceId, 'epoch' => 163, 'master_members' => $cachedMembers]], [1])['read_reasons'][1], 'reader_cache_master_members_mismatch'],
    'source token mismatch reason' => [static fn (): mixed => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'source_id' => 'old', 'epoch' => 163, 'master_members' => $currentMembers]], [1])['read_reasons'][1], 'reader_cache_current_source_token_mismatch'],
    'epoch mismatch reason' => [static fn (): mixed => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'source_id' => $currentSourceId, 'epoch' => 162, 'master_members' => $currentMembers]], [1])['read_reasons'][1], 'reader_cache_current_source_token_mismatch'],
    'absent current page reason' => [static fn (): mixed => $plan(null, null, [1 => $currentPages[1]], null, [7 => ['image' => $nextPages[7], 'source_id' => $currentSourceId, 'epoch' => 163, 'master_members' => $currentMembers]], [7])['read_reasons'][7], 'reader_cache_page_absent_from_current_source'],
    'all unchanged pages reuse cache' => [static fn (): mixed => $plan($currentBytes, null, $currentPages, $currentPages, [
        1 => ['image' => $currentPages[1], 'source_id' => $currentSourceId, 'epoch' => 163, 'master_members' => $currentMembers],
        2 => ['image' => $currentPages[2], 'source_id' => $currentSourceId, 'epoch' => 163, 'master_members' => $currentMembers],
    ], [1, 2])['read_cache_hits'], [1 => true, 2 => true]],
    'changed unpinned page falls back next' => [static fn (): mixed => $plan(null, null, null, null, [2 => ['image' => $currentPages[2], 'source_id' => $currentSourceId, 'epoch' => 163, 'master_members' => $currentMembers]], [2])['read_reasons'][2], 'next_source_page_changed_after_master_journal_recovery'],
    'dirty unchanged page still blocks' => [static fn (): mixed => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'source_id' => $currentSourceId, 'epoch' => 163, 'dirty' => true, 'master_members' => $currentMembers]], [1])['blocked_page_numbers'], [1]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next163 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext163('', $master, $cachedBytes, $currentBytes, $pageSize, $currentPages, $nextPages, $cache, $readPages, $currentSourceId, $nextSourceId, $currentEpoch, $nextEpoch),
    'rejects empty master path' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext163($database, '', $cachedBytes, $currentBytes, $pageSize, $currentPages, $nextPages, $cache, $readPages, $currentSourceId, $nextSourceId, $currentEpoch, $nextEpoch),
    'rejects empty current source id' => static fn () => $plan(null, null, null, null, null, null, '', $nextSourceId),
    'rejects empty next source id' => static fn () => $plan(null, null, null, null, null, null, $currentSourceId, ''),
    'rejects same source ids' => static fn () => $plan(null, null, null, null, null, null, $currentSourceId, $currentSourceId),
    'rejects blank current master' => static fn () => $plan(null, " \n"),
    'rejects bad page size' => static fn () => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext163($database, $master, $cachedBytes, $currentBytes, 0, $currentPages, $nextPages, $cache, $readPages, $currentSourceId, $nextSourceId, $currentEpoch, $nextEpoch),
    'rejects bad current epoch' => static fn () => $plan(null, null, null, null, null, null, $currentSourceId, $nextSourceId, 0, 164),
    'rejects non-increasing next epoch' => static fn () => $plan(null, null, null, null, null, null, $currentSourceId, $nextSourceId, 163, 163),
    'rejects empty current pages' => static fn () => $plan(null, null, []),
    'rejects empty next pages' => static fn () => $plan(null, null, null, []),
    'rejects empty cache' => static fn () => $plan(null, null, null, null, []),
    'rejects empty reads' => static fn () => $plan(null, null, null, null, null, []),
    'rejects unreferenced database journal' => static fn () => $plan(null, "/tmp/other.sqlite-journal\n"),
    'rejects zero current page' => static fn () => $plan(null, null, [0 => $currentPages[1]]),
    'rejects short current page' => static fn () => $plan(null, null, [1 => 'short']),
    'rejects zero next page' => static fn () => $plan(null, null, null, [0 => $nextPages[1]]),
    'rejects short next page' => static fn () => $plan(null, null, null, [1 => 'short']),
    'rejects zero cache page' => static fn () => $plan(null, null, null, null, [0 => ['image' => $currentPages[1]]]),
    'rejects short cache page' => static fn () => $plan(null, null, null, null, [1 => ['image' => 'short']]),
    'rejects bad cache epoch' => static fn () => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'epoch' => -1]]),
    'rejects nonlist members' => static fn () => $plan(null, null, null, null, [1 => ['image' => $currentPages[1], 'master_members' => 'bad']]),
    'rejects bad read page' => static fn () => $plan(null, null, null, null, null, [0]),
    'rejects read outside next source' => static fn () => $plan(null, null, null, null, null, [8]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next163 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

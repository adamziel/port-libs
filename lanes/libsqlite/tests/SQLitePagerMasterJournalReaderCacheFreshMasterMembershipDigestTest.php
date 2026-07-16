<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next173.sqlite';
$master = '/srv/wp-content/database/wp-next173.sqlite-mj';
$members = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next173-site.sqlite-journal',
    '/srv/wp-content/database/wp-next173-users.sqlite-journal',
];
$masterBytes = implode("\n", $members) . "\n";
$masterDigest = hash('sha256', implode("\n", $members));
$sourceId = 'wp-next173-current-source-after-master-read';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$pages = [
    1 => $page('next173 current schema after fresh master membership read'),
    2 => $page('next173 current wp_options after fresh master membership read'),
    3 => $page('next173 current active_plugins after fresh master membership read'),
    4 => $page('next173 current user roles after fresh master membership read'),
    5 => $page('next173 current transient timeout after fresh master membership read'),
    6 => $page('next173 current rewrite rules after fresh master membership read'),
    7 => $page('next173 current cron array after fresh master membership read'),
];
$cache = [
    1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'schema-reader', 'master_digest' => $masterDigest, 'master_members' => $members, 'shared' => true],
    2 => ['image' => $page('next173 stale wp_options before fresh master membership read'), 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'options-reader', 'master_digest' => $masterDigest, 'master_members' => $members],
    3 => ['image' => $pages[3], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'active-reader', 'master_digest' => str_repeat('0', 64), 'master_members' => $members],
    4 => ['image' => $pages[4], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'roles-reader', 'master_digest' => '', 'master_members' => [$members[0], $members[1]]],
    5 => ['image' => $pages[5], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'dirty-reader', 'master_digest' => $masterDigest, 'master_members' => $members, 'dirty' => true],
    6 => ['image' => $page('next173 pinned rewrite rules before fresh master membership read'), 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'pinned-reader', 'master_digest' => $masterDigest, 'master_members' => $members, 'pinned' => true],
    7 => ['image' => $pages[7], 'source_id' => 'old-source-next173', 'epoch' => 173, 'reader_id' => 'old-source-reader', 'master_digest' => $masterDigest, 'master_members' => $members],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
    ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
    ['reader_id' => 'active-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => str_repeat('0', 64)],
    ['reader_id' => 'roles-reader', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
    ['reader_id' => 'dirty-reader', 'page_number' => 5, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
    ['reader_id' => 'pinned-reader', 'page_number' => 6, 'source_id' => $sourceId, 'epoch' => 172, 'master_digest' => $masterDigest],
    ['reader_id' => 'old-source-reader', 'page_number' => 7, 'source_id' => 'old-source-next173', 'epoch' => 173, 'master_digest' => $masterDigest],
];

$plan = static fn (
    ?array $currentPages = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $masterBytesArg = null,
    ?int $size = null,
    ?string $db = null,
    ?string $mj = null,
    ?string $source = null,
    int $epoch = 173,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantFreshMasterMembershipDigest(
    $db ?? $database,
    $mj ?? $master,
    $masterBytesArg ?? $masterBytes,
    $size ?? $pageSize,
    $currentPages ?? $pages,
    $readerCache ?? $cache,
    $nextReads ?? $reads,
    $source ?? $sourceId,
    $epoch,
);

$freshOne = [
    1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest, 'master_members' => array_reverse($members)],
];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next173'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'fresh master-journal membership digest fences reader-cache reuse before current-source reads'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 173],
    'source digest' => [static fn (): mixed => $plan()['current_source']['master_journal_digest'], $masterDigest],
    'source members' => [static fn (): mixed => $plan()['current_source']['master_journal_members'], $members],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema row admitted' => [static fn (): mixed => $plan()['cache_rows'][0]['admitted'], true],
    'schema row reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_current_master_membership'],
    'schema row shared' => [static fn (): mixed => $plan()['cache_rows'][0]['shared'], true],
    'options row refreshed' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_current_master_membership'],
    'options row image mismatch' => [static fn (): mixed => $plan()['cache_rows'][1]['image_matches_current_source'], false],
    'digest mismatch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'reader_cache_master_journal_digest_mismatch'],
    'membership mismatch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_master_journal_membership_mismatch'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'dirty_reader_cache_cannot_cross_current_master_membership'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'pinned_reader_cache_image_mismatch_after_master_membership_read'],
    'source reason' => [static fn (): mixed => $plan()['invalidated_reasons'][7], 'reader_cache_source_id_mismatch'],
    'membership mismatch row' => [static fn (): mixed => $plan()['cache_rows'][3]['membership_matches'], false],
    'digest mismatch row current digest' => [static fn (): mixed => $plan()['cache_rows'][2]['current_master_digest'], $masterDigest],
    'invalidated current members' => [static fn (): mixed => $plan()['invalidated_entries'][1]['current_members'], $members],
    'invalidated cached members' => [static fn (): mixed => $plan()['invalidated_entries'][1]['cached_members'], [$members[0], $members[1]]],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'schema read ticket current' => [static fn (): mixed => $plan()['next_reads'][0]['ticket_current'], true],
    'schema read cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'schema read source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-retained-current-master-membership'],
    'schema read prefix' => [static fn (): mixed => $plan()['next_reads'][0]['prefix'], 'next173 current schema after fresh master membership read'],
    'options read cache hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'options read source' => [static fn (): mixed => $plan()['next_reads'][1]['source'], 'reader-cache-refreshed-current-master-membership'],
    'options read prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next173 current wp_options after fresh master membership read'],
    'active stale digest ticket' => [static fn (): mixed => $plan()['next_reads'][2]['ticket_current'], false],
    'active stale digest cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'roles read cache miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'dirty read current page' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next173 current transient timeout after fresh master membership '],
    'pinned stale epoch' => [static fn (): mixed => $plan()['next_reads'][5]['ticket_current'], false],
    'old source stale ticket' => [static fn (): mixed => $plan()['next_reads'][6]['ticket_current'], false],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['active-reader', 'roles-reader', 'dirty-reader', 'pinned-reader', 'old-source-reader']],
    'hit map schema' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'hit map roles' => [static fn (): mixed => $plan()['read_cache_hits']['roles-reader'], false],
    'prefix map options' => [static fn (): mixed => $plan()['read_prefixes']['options-reader'], 'next173 current wp_options after fresh master membership read'],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache_membership_next173'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_current_master_membership'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][2]['op'], 'refresh_reader_cache_from_current_master_membership'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_master_membership_ticket'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][8]['op'], 'next_reader_cache_hit_current_master_membership'],
    'operation read reopen' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_reader_reopen_current_master_membership_page'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next173', $plan()['dependencies'], true), true],
    'dependency prior accepted' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next167', $plan()['dependencies'], true), true],
    'dependency membership digest' => [static fn (): mixed => in_array('sqlite-master-journal-membership-digest-reader-ticket', $plan()['dependencies'], true), true],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, null, $masterBytes . $database . "-journal\n")['current_source']['master_journal_members'], $members],
    'member order ignored for cache list' => [static fn (): mixed => $plan(null, $freshOne, [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest]])['retained_page_numbers'], [1]],
    'blank digest allowed with matching members' => [static fn (): mixed => $plan(null, [1 => array_replace($freshOne[1], ['master_digest' => ''])], [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173]])['next_reads'][0]['cache_hit'], true],
    'blank member list falls back to digest' => [static fn (): mixed => $plan(null, [1 => array_replace($freshOne[1], ['master_members' => []])], [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest]])['retained_page_numbers'], [1]],
    'source mismatch direct reason' => [static fn (): mixed => $plan(null, [1 => array_replace($freshOne[1], ['source_id' => 'old'])], [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173]])['invalidated_reasons'][1], 'reader_cache_source_id_mismatch'],
    'epoch mismatch direct reason' => [static fn (): mixed => $plan(null, [1 => array_replace($freshOne[1], ['epoch' => 172])], [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173]])['invalidated_reasons'][1], 'reader_cache_epoch_mismatch'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next173 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, 500),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 0),
    'empty pages rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'zero current page rejected' => static fn () => $plan([0 => $pages[1]]),
    'short current page rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cache[1]]),
    'short cache page rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['epoch' => 0])]),
    'bad cache members type rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['master_members' => 'not-list'])]),
    'bad cache member rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['master_members' => ['']])]),
    'bad cache digest rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['master_digest' => 10])]),
    'cache outside rejected' => static fn () => $plan(null, [8 => array_merge($cache[1], ['image' => $page('outside')])]),
    'empty read id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $sourceId, 'epoch' => 173]]),
    'empty read source rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 173]]),
    'bad read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 0]]),
    'bad read digest rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => 10]]),
    'read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'outside', 'page_number' => 8, 'source_id' => $sourceId, 'epoch' => 173]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next173 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

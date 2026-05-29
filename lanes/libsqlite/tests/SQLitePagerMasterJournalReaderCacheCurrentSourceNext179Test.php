<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next179.sqlite';
$master = '/srv/wp-content/database/wp-next179.sqlite-mj';
$source = 'wp-next179-canonical-master-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$canonicalMap = [
    '../database/wp-next179.sqlite-journal' => $database . '-journal',
    '/mnt/alias/wp-next179-users.sqlite-journal' => '/srv/wp-content/database/wp-next179-users.sqlite-journal',
    '/srv/wp-content/database/../database/wp-next179-comments.sqlite-journal' => '/srv/wp-content/database/wp-next179-comments.sqlite-journal',
    '/tmp/wp-next179-evil.sqlite-journal' => '/tmp/wp-next179-evil.sqlite-journal',
];
$rawMembers = [
    '../database/wp-next179.sqlite-journal',
    '/mnt/alias/wp-next179-users.sqlite-journal',
    '/srv/wp-content/database/../database/wp-next179-comments.sqlite-journal',
];
$canonicalMembers = [
    '/srv/wp-content/database/wp-next179-comments.sqlite-journal',
    '/srv/wp-content/database/wp-next179-users.sqlite-journal',
    $database . '-journal',
];
$masterBytes = implode("\n", $rawMembers) . "\n";
$rawDigest = hash('sha256', implode("\n", $rawMembers));
$canonicalDigest = hash('sha256', implode("\n", $canonicalMembers));
$sameCanonicalDifferentRaw = [
    '/srv/wp-content/database/wp-next179-comments.sqlite-journal',
    '../database/wp-next179.sqlite-journal',
    '/mnt/alias/wp-next179-users.sqlite-journal',
];

$pages = [
    1 => $page('next179 canonical schema page'),
    2 => $page('next179 canonical active_plugins page'),
    3 => $page('next179 canonical user roles page'),
    4 => $page('next179 canonical dirty cache page'),
    5 => $page('next179 canonical stale source page'),
    6 => $page('next179 canonical pinned current image'),
    7 => $page('next179 canonical rewrite rules current image'),
    8 => $page('next179 canonical optionmeta current image'),
];

$cache = [
    1 => ['reader_id' => 'schema-alias', 'image' => $pages[1], 'source_id' => $source, 'epoch' => 179, 'master_members' => $sameCanonicalDifferentRaw, 'canonical_digest' => $canonicalDigest, 'shared' => true],
    2 => ['reader_id' => 'active-refresh', 'image' => $page('next179 stale active_plugins alias cache'), 'source_id' => $source, 'epoch' => 179, 'master_members' => $rawMembers, 'canonical_digest' => $canonicalDigest],
    3 => ['reader_id' => 'roles-wrong-canonical', 'image' => $pages[3], 'source_id' => $source, 'epoch' => 179, 'master_members' => [$database . '-journal', '/tmp/wp-next179-evil.sqlite-journal'], 'canonical_digest' => hash('sha256', 'old-canonical')],
    4 => ['reader_id' => 'dirty-cache', 'image' => $pages[4], 'source_id' => $source, 'epoch' => 179, 'master_members' => $rawMembers, 'canonical_digest' => $canonicalDigest, 'dirty' => true],
    5 => ['reader_id' => 'old-source', 'image' => $pages[5], 'source_id' => 'old-source', 'epoch' => 179, 'master_members' => $rawMembers, 'canonical_digest' => $canonicalDigest],
    6 => ['reader_id' => 'old-epoch', 'image' => $pages[6], 'source_id' => $source, 'epoch' => 178, 'master_members' => $rawMembers, 'canonical_digest' => $canonicalDigest],
    7 => ['reader_id' => 'pinned-stale', 'image' => $page('next179 stale pinned rewrite rules'), 'source_id' => $source, 'epoch' => 179, 'master_members' => $rawMembers, 'canonical_digest' => $canonicalDigest, 'pinned' => true],
    8 => ['reader_id' => 'stored-digest-empty', 'image' => $pages[8], 'source_id' => $source, 'epoch' => 179, 'master_members' => $rawMembers],
];

$reads = [
    ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ['reader_id' => 'active-read', 'page_number' => 2, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ['reader_id' => 'roles-read', 'page_number' => 3, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ['reader_id' => 'dirty-read', 'page_number' => 4, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ['reader_id' => 'ticket-old-digest', 'page_number' => 1, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => hash('sha256', 'stale')],
    ['reader_id' => 'ticket-old-source', 'page_number' => 1, 'source_id' => 'old-source', 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ['reader_id' => 'optionmeta-read', 'page_number' => 8, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => ''],
];
$writes = [
    2 => $page('next179 rewritten active_plugins after canonical fence'),
    8 => $page('next179 rewritten optionmeta after canonical fence'),
];

$plan = static fn (
    ?array $cacheArg = null,
    ?array $readsArg = null,
    ?array $writesArg = null,
    ?array $mapArg = null,
    ?array $pagesArg = null,
    ?string $masterBytesArg = null,
    ?int $size = null,
    ?string $db = null,
    ?string $mj = null,
    ?string $sourceArg = null,
    int $epoch = 179,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::canonicalMemberPathReaderCachePlan(
    $db ?? $database,
    $mj ?? $master,
    $masterBytesArg ?? $masterBytes,
    $mapArg ?? $canonicalMap,
    $size ?? $pageSize,
    $pagesArg ?? $pages,
    $cacheArg ?? $cache,
    $readsArg ?? $reads,
    $writesArg ?? $writes,
    $sourceArg ?? $source,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next179'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'canonical master-journal member paths fence reader-cache reuse before the current source is trusted'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'raw members' => [static fn (): mixed => $plan()['raw_members'], $rawMembers],
    'canonical members' => [static fn (): mixed => $plan()['canonical_members'], $canonicalMembers],
    'raw digest' => [static fn (): mixed => $plan()['raw_master_digest'], $rawDigest],
    'canonical digest' => [static fn (): mixed => $plan()['canonical_master_digest'], $canonicalDigest],
    'source id' => [static fn (): mixed => $plan()['source']['id'], $source],
    'source epoch' => [static fn (): mixed => $plan()['source']['epoch'], 179],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1, 8]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7]],
    'wrong canonical reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'reader_cache_stored_canonical_digest_mismatch_next179'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'dirty_reader_cache_cannot_cross_canonical_master_source'],
    'source reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'reader_cache_source_id_mismatch_after_canonical_master_source'],
    'epoch reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'reader_cache_epoch_mismatch_after_canonical_master_source'],
    'pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][7], 'pinned_reader_cache_image_mismatch_after_canonical_master_source'],
    'alias row admitted' => [static fn (): mixed => $plan()['cache_rows'][0]['admitted'], true],
    'alias raw preserved' => [static fn (): mixed => $plan()['cache_rows'][0]['raw_members'], $sameCanonicalDifferentRaw],
    'alias canonical digest matches' => [static fn (): mixed => $plan()['cache_rows'][0]['canonical_digest_matches'], true],
    'alias stored digest matches' => [static fn (): mixed => $plan()['cache_rows'][0]['stored_canonical_digest_matches'], true],
    'refresh row admitted' => [static fn (): mixed => $plan()['cache_rows'][1]['admitted'], true],
    'refresh row reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_canonical_master_source'],
    'refresh image mismatch' => [static fn (): mixed => $plan()['cache_rows'][1]['image_matches_current_source'], false],
    'wrong canonical row mismatch' => [static fn (): mixed => $plan()['cache_rows'][2]['canonical_digest_matches'], false],
    'dirty row rejected' => [static fn (): mixed => $plan()['cache_rows'][3]['admitted'], false],
    'empty stored digest admitted' => [static fn (): mixed => $plan()['cache_rows'][7]['admitted'], true],
    'read count' => [static fn (): mixed => count($plan()['reads']), 7],
    'schema read hit' => [static fn (): mixed => $plan()['read_cache_hits']['schema-read'], true],
    'active read hit after refresh' => [static fn (): mixed => $plan()['read_cache_hits']['active-read'], true],
    'roles read miss' => [static fn (): mixed => $plan()['read_cache_hits']['roles-read'], false],
    'dirty read miss' => [static fn (): mixed => $plan()['read_cache_hits']['dirty-read'], false],
    'old digest read miss' => [static fn (): mixed => $plan()['read_cache_hits']['ticket-old-digest'], false],
    'old source read miss' => [static fn (): mixed => $plan()['read_cache_hits']['ticket-old-source'], false],
    'optionmeta empty digest read hit' => [static fn (): mixed => $plan()['read_cache_hits']['optionmeta-read'], true],
    'active read prefix refreshed' => [static fn (): mixed => $plan()['read_prefixes']['active-read'], 'next179 canonical active_plugins page'],
    'roles read prefix current source' => [static fn (): mixed => $plan()['read_prefixes']['roles-read'], 'next179 canonical user roles page'],
    'schema read source label' => [static fn (): mixed => $plan()['reads'][0]['source'], 'reader-cache-retained-canonical-master-source-next179'],
    'active read source label' => [static fn (): mixed => $plan()['reads'][1]['source'], 'reader-cache-refreshed-canonical-master-source-next179'],
    'roles read source label' => [static fn (): mixed => $plan()['reads'][2]['source'], 'canonical-master-source-reopen-next179'],
    'schema ticket current' => [static fn (): mixed => $plan()['reads'][0]['ticket_current'], true],
    'old digest ticket stale' => [static fn (): mixed => $plan()['reads'][4]['ticket_current'], false],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['roles-read', 'dirty-read', 'ticket-old-digest', 'ticket-old-source']],
    'write count' => [static fn (): mixed => count($plan()['writes']), 2],
    'write before active' => [static fn (): mixed => $plan()['writes'][0]['before_prefix'], 'next179 canonical active_plugins page'],
    'write after active' => [static fn (): mixed => $plan()['writes'][0]['after_prefix'], 'next179 rewritten active_plugins after canonical fence'],
    'write before canonical' => [static fn (): mixed => $plan()['writes'][0]['before_image_from_canonical_master_source'], true],
    'write digest' => [static fn (): mixed => $plan()['writes'][1]['canonical_digest'], $canonicalDigest],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal_and_canonicalize_members_for_reader_cache_next179'],
    'retain operation exists' => [static fn (): mixed => in_array('retain_reader_cache_after_canonical_master_source_next179', array_column($plan()['operations'], 'op'), true), true],
    'refresh operation exists' => [static fn (): mixed => in_array('refresh_reader_cache_after_canonical_master_source_next179', array_column($plan()['operations'], 'op'), true), true],
    'invalidate operation exists' => [static fn (): mixed => in_array('invalidate_reader_cache_after_canonical_master_source_next179', array_column($plan()['operations'], 'op'), true), true],
    'read hit operation exists' => [static fn (): mixed => in_array('next179_reader_cache_hit', array_column($plan()['operations'], 'op'), true), true],
    'read reopen operation exists' => [static fn (): mixed => in_array('next179_reader_reopen', array_column($plan()['operations'], 'op'), true), true],
    'write operation exists' => [static fn (): mixed => in_array('capture_next_write_before_image_after_canonical_master_source_next179', array_column($plan()['operations'], 'op'), true), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next179', $plan()['dependencies'], true), true],
    'dependency canonical ordering' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next174', $plan()['dependencies'], true), true],
    'dependency vfs canonical' => [static fn (): mixed => in_array('sqlite-master-journal-vfs-canonical-pathname', $plan()['dependencies'], true), true],
    'non overlap mentions canonical pathname' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'canonical pathname'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next179 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'empty pages rejected' => static fn () => $plan(null, null, null, null, []),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads and writes rejected' => static fn () => $plan(null, [], []),
    'canonical map bad from rejected' => static fn () => $plan(null, null, null, ['' => '/tmp/x']),
    'canonical map bad to rejected' => static fn () => $plan(null, null, null, ['/tmp/x' => '']),
    'missing database journal rejected' => static fn () => $plan(null, null, null, [], null, "/tmp/other.sqlite-journal\n"),
    'zero current page rejected' => static fn () => $plan(null, null, null, null, [0 => $pages[1]]),
    'short current page rejected' => static fn () => $plan(null, null, null, null, [1 => 'short']),
    'zero cache page rejected' => static fn () => $plan([0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan([1 => array_replace($cache[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan([1 => array_replace($cache[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => array_replace($cache[1], ['epoch' => 0])]),
    'empty cache members rejected' => static fn () => $plan([1 => array_replace($cache[1], ['master_members' => []])]),
    'bad cache members rejected' => static fn () => $plan([1 => array_replace($cache[1], ['master_members' => 'bad'])]),
    'empty cache member rejected' => static fn () => $plan([1 => array_replace($cache[1], ['master_members' => ['']])]),
    'bad stored canonical digest rejected' => static fn () => $plan([1 => array_replace($cache[1], ['canonical_digest' => 42])]),
    'cache outside source rejected' => static fn () => $plan([9 => array_replace($cache[1], ['image' => $page('outside')])]),
    'empty read id rejected' => static fn () => $plan(null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest]]),
    'empty read source rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 179, 'canonical_digest' => $canonicalDigest]]),
    'bad read digest rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => 42]]),
    'zero read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest]]),
    'bad read epoch rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $source, 'epoch' => 0, 'canonical_digest' => $canonicalDigest]]),
    'read outside source rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 9, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest]]),
    'zero write page rejected' => static fn () => $plan(null, null, [0 => $pages[1]]),
    'short write page rejected' => static fn () => $plan(null, null, [1 => 'short']),
    'write outside source rejected' => static fn () => $plan(null, null, [9 => $page('outside write')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next179 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

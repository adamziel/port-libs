<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next162.sqlite';
$masterPath = '/srv/wp-content/database/wp-next162.sqlite-mj';
$cachedMaster = $databasePath . "-journal\n";
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/wp-next162-site.sqlite-journal\n";
$sourceId = 'master-reader-cache-next162-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('next162 current schema after master recovery'),
    2 => $page('next162 current wp_options root after master recovery'),
    3 => $page('next162 current autoload index after master recovery'),
    4 => $page('next162 current plugin setting after master recovery'),
    5 => $page('next162 current transient cache after master recovery'),
    6 => $page('next162 current comments page after master recovery'),
];
$readerCache = [
    1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 12, 'reader_id' => 'schema-reader'],
    2 => ['image' => $currentPages[2], 'source_id' => 'old-master-source', 'epoch' => 12, 'reader_id' => 'old-source-reader'],
    3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 11, 'reader_id' => 'old-epoch-reader'],
    4 => ['image' => $page('next162 stale plugin setting before recovery'), 'source_id' => $sourceId, 'epoch' => 12, 'reader_id' => 'stale-image-reader'],
    5 => ['image' => $currentPages[5], 'source_id' => $sourceId, 'epoch' => 12, 'reader_id' => 'pinned-reader', 'pinned' => true],
    6 => ['image' => $currentPages[6], 'source_id' => $sourceId, 'epoch' => 12, 'reader_id' => 'speculative-reader', 'next_source' => true],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 12, 'end_frame' => 7],
    ['reader_id' => 'old-source-reader', 'page_number' => 2, 'source_id' => 'old-master-source', 'epoch' => 12, 'end_frame' => 7],
    ['reader_id' => 'old-epoch-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 11, 'end_frame' => 7],
    ['reader_id' => 'stale-image-reader', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 12, 'end_frame' => 7],
    ['reader_id' => 'pinned-reader', 'page_number' => 5, 'source_id' => $sourceId, 'epoch' => 12, 'end_frame' => 7],
    ['reader_id' => 'speculative-reader', 'page_number' => 6, 'source_id' => $sourceId, 'epoch' => 12, 'end_frame' => 8],
];

$plan = static fn (
    ?array $pages = null,
    ?array $cache = null,
    ?array $nextReads = null,
    mixed $cached = '__default__',
    mixed $current = '__default__',
    ?int $size = null,
    ?string $source = null,
    int $epoch = 12,
    int $endFrame = 7,
    ?string $path = null,
    ?string $mjPath = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext162(
    $path ?? $databasePath,
    $mjPath ?? $masterPath,
    $cached === '__default__' ? $cachedMaster : $cached,
    $current === '__default__' ? $currentMaster : $current,
    $size ?? $pageSize,
    $pages ?? $currentPages,
    $cache ?? $readerCache,
    $nextReads ?? $reads,
    $source ?? $sourceId,
    $epoch,
    $endFrame,
);

$sameMembers = static fn (): array => $plan(null, null, [['reader_id' => 'schema-reader', 'page_number' => 1]], $currentMaster, $currentMaster);
$dirty = $readerCache;
$dirty[1]['dirty'] = true;

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next162'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'next_reader_sources_are_reopened_when_master_journal_recovery_changes_cache_membership'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master journal path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$databasePath . '-journal']],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next162-site.sqlite-journal']],
    'membership changed' => [static fn (): mixed => $plan()['membership_changed'], true],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 12],
    'current source end frame' => [static fn (): mixed => $plan()['current_source']['end_frame'], 7],
    'current source pages' => [static fn (): mixed => $plan()['current_source']['page_numbers'], [1, 2, 3, 4, 5, 6]],
    'retained cache page numbers' => [static fn (): mixed => $plan()['cache']['retained_page_numbers'], [1]],
    'invalidated cache page numbers' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5, 6]],
    'old source invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'reader_cache_source_id_is_not_current'],
    'old epoch invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'reader_cache_epoch_is_not_current'],
    'stale image invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'reader_cache_image_not_current'],
    'pinned invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'pinned_reader_cache_cannot_seed_next_reader'],
    'speculative invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][4]['reason'], 'speculative_next_source_cache_must_be_reopened'],
    'cache row count' => [static fn (): mixed => count($plan()['cache']['rows']), 6],
    'cache row one admitted' => [static fn (): mixed => $plan()['cache']['rows'][0]['reason'], 'cache_page_admitted_for_current_source_next_read'],
    'cache row four mismatch' => [static fn (): mixed => $plan()['cache']['rows'][3]['image_matches_current_source'], false],
    'cache row five pinned' => [static fn (): mixed => $plan()['cache']['rows'][4]['pinned'], true],
    'cache row six next source' => [static fn (): mixed => $plan()['cache']['rows'][5]['next_source'], true],
    'read count' => [static fn (): mixed => count($plan()['reads']), 6],
    'schema read ticket current' => [static fn (): mixed => $plan()['reads'][0]['ticket_current'], true],
    'schema read cache hit' => [static fn (): mixed => $plan()['reads'][0]['cache_hit'], true],
    'schema read prefix' => [static fn (): mixed => $plan()['reads'][0]['prefix'], 'next162 current schema after master recovery'],
    'old source ticket stale' => [static fn (): mixed => $plan()['reads'][1]['ticket_current'], false],
    'old source cache miss' => [static fn (): mixed => $plan()['reads'][1]['cache_hit'], false],
    'old source prefix current' => [static fn (): mixed => $plan()['reads'][1]['prefix'], 'next162 current wp_options root after master recovery'],
    'old epoch ticket stale' => [static fn (): mixed => $plan()['reads'][2]['ticket_current'], false],
    'stale image uses current page' => [static fn (): mixed => $plan()['reads'][3]['prefix'], 'next162 current plugin setting after master recovery'],
    'pinned reader uses current page' => [static fn (): mixed => $plan()['reads'][4]['source'], 'current-master-journal-source'],
    'speculative ticket stale by end frame' => [static fn (): mixed => $plan()['reads'][5]['ticket_current'], false],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['old-source-reader', 'old-epoch-reader', 'speculative-reader']],
    'read cache hit map schema' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'read cache hit map pinned' => [static fn (): mixed => $plan()['read_cache_hits']['pinned-reader'], false],
    'read prefix map speculative' => [static fn (): mixed => $plan()['read_prefixes']['speculative-reader'], 'next162 current comments page after master recovery'],
    'operation reads current master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_before_next_reader_cache_source'],
    'operation retires cached members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retire_cached_master_journal_members_for_next_reader'],
    'operation retains page one' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_reader_cache_for_current_source_next_read'],
    'operation invalidates page two' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_before_next_source_read'],
    'operation final read reopen' => [static fn (): mixed => $plan()['operations'][13]['op'], 'next_reader_reopen_current_source_page'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next162' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next162', $plan()['dependencies'], true), true],
    'dependency next160' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next160', $plan()['dependencies'], true), true],
    'dependency reopen marker' => [static fn (): mixed => in_array('sqlite-master-journal-next-reader-current-source-reopen', $plan()['dependencies'], true), true],
    'same members unchanged' => [static fn (): mixed => $sameMembers()['membership_changed'], false],
    'same members no retire operation' => [static fn (): mixed => $sameMembers()['operations'][1]['op'], 'retain_reader_cache_for_current_source_next_read'],
    'duplicate members collapse' => [static fn (): mixed => $plan(null, null, null, '__default__', $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next162-site.sqlite-journal']],
    'blank cached members' => [static fn (): mixed => $plan(null, null, null, '')['cached_members'], []],
    'dirty reason wins' => [static fn (): mixed => $plan(null, $dirty)['cache']['invalidated_entries'][0]['reason'], 'dirty_reader_cache_page_after_master_recovery'],
    'default reader id is synthesized' => [static fn (): mixed => $plan(null, [1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 12]], [['reader_id' => 'schema-reader', 'page_number' => 1]])['cache']['rows'][0]['reader_id'], 'reader-1'],
    'default read ticket current' => [static fn (): mixed => $plan(null, [1 => $readerCache[1]], [['reader_id' => 'schema-reader', 'page_number' => 1]])['reads'][0]['ticket_current'], true],
    'default read ticket cache hit' => [static fn (): mixed => $plan(null, [1 => $readerCache[1]], [['reader_id' => 'schema-reader', 'page_number' => 1]])['reads'][0]['cache_hit'], true],
    'read digest length' => [static fn (): mixed => strlen($plan()['reads'][0]['digest']), 64],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next162 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, 12, 7, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, 12, 7, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, ''),
    'blank current master rejected' => static fn () => $plan(null, null, null, '__default__', ''),
    'wrong current master rejected' => static fn () => $plan(null, null, null, '__default__', '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', 500),
    'bad epoch rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, 0),
    'bad end frame rejected' => static fn () => $plan(null, null, null, '__default__', '__default__', null, null, 12, -1),
    'empty pages rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'zero current page rejected' => static fn () => $plan([0 => $currentPages[1]]),
    'short current page rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $readerCache[1]]),
    'short cache page rejected' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => ['image' => $currentPages[1], 'epoch' => -1]]),
    'empty reader id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 0]]),
    'negative read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'epoch' => -1]]),
    'negative read frame rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'end_frame' => -1]]),
    'read outside current source rejected' => static fn () => $plan(null, null, [['reader_id' => 'outside-reader', 'page_number' => 7]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next162 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next165.sqlite';
$masterPath = '/srv/wp-content/database/wp-next165.sqlite-mj';
$currentMaster = $databasePath . "-journal\n/srv/wp-content/database/wp-next165-network.sqlite-journal\n";
$digest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next165-network.sqlite-journal");
$sourceId = 'next165-current-master-header-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('next165 page1 header counter schema cookie after master recovery'),
    2 => $page('next165 current wp_options root after master recovery'),
    3 => $page('next165 current active_plugins after master recovery'),
    4 => $page('next165 current autoload index after master recovery'),
    5 => $page('next165 current transient timeout after master recovery'),
    6 => $page('next165 current site options after master recovery'),
    7 => $page('next165 current comments after master recovery'),
    8 => $page('next165 current users after master recovery'),
];
$readerCache = [
    1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'header-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    2 => ['image' => $currentPages[2], 'source_id' => 'old-source', 'epoch' => 21, 'reader_id' => 'old-source-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 20, 'reader_id' => 'old-epoch-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    4 => ['image' => $currentPages[4], 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'old-master-reader', 'master_journal_digest' => hash('sha256', 'old-master'), 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    5 => ['image' => $currentPages[5], 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'old-change-reader', 'master_journal_digest' => $digest, 'change_counter' => 43, 'schema_cookie' => 9, 'end_frame' => 12],
    6 => ['image' => $currentPages[6], 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'old-schema-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 8, 'end_frame' => 12],
    7 => ['image' => $currentPages[7], 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'old-frame-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 11],
    8 => ['image' => $page('next165 stale users cache before master recovery'), 'source_id' => $sourceId, 'epoch' => 21, 'reader_id' => 'stale-image-reader', 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
];
$reads = [
    ['reader_id' => 'header-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 21, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    ['reader_id' => 'old-source-reader', 'page_number' => 2, 'source_id' => 'old-source', 'epoch' => 21, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
    ['reader_id' => 'old-change-reader', 'page_number' => 5, 'source_id' => $sourceId, 'epoch' => 21, 'change_counter' => 43, 'schema_cookie' => 9, 'end_frame' => 12],
    ['reader_id' => 'old-schema-reader', 'page_number' => 6, 'source_id' => $sourceId, 'epoch' => 21, 'change_counter' => 44, 'schema_cookie' => 8, 'end_frame' => 12],
    ['reader_id' => 'old-frame-reader', 'page_number' => 7, 'source_id' => $sourceId, 'epoch' => 21, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 11],
    ['reader_id' => 'stale-image-reader', 'page_number' => 8, 'source_id' => $sourceId, 'epoch' => 21, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12],
];

$plan = static fn (
    ?array $pages = null,
    ?array $cache = null,
    ?array $nextReads = null,
    mixed $master = '__default__',
    ?int $size = null,
    ?string $source = null,
    int $epoch = 21,
    int $changeCounter = 44,
    int $schemaCookie = 9,
    int $endFrame = 12,
    ?string $path = null,
    ?string $mjPath = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planPinnedReaderCacheMasterJournalRevalidation(
    $path ?? $databasePath,
    $mjPath ?? $masterPath,
    $master === '__default__' ? $currentMaster : $master,
    $size ?? $pageSize,
    $pages ?? $currentPages,
    $cache ?? $readerCache,
    $nextReads ?? $reads,
    $source ?? $sourceId,
    $epoch,
    $changeCounter,
    $schemaCookie,
    $endFrame,
);

$dirty = $readerCache;
$dirty[1]['dirty'] = true;
$pinned = $readerCache;
$pinned[1]['pinned'] = true;
$validSingle = [1 => $readerCache[1]];
$defaultRead = [['reader_id' => 'header-reader', 'page_number' => 1]];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next165'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_cache_reuse_is_fenced_by_master_journal_membership_and_header_generation'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next165-network.sqlite-journal']],
    'current digest' => [static fn (): mixed => $plan()['current_master_journal_digest'], $digest],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 21],
    'source change counter' => [static fn (): mixed => $plan()['current_source']['change_counter'], 44],
    'source schema cookie' => [static fn (): mixed => $plan()['current_source']['schema_cookie'], 9],
    'source end frame' => [static fn (): mixed => $plan()['current_source']['end_frame'], 12],
    'source page numbers' => [static fn (): mixed => $plan()['current_source']['page_numbers'], [1, 2, 3, 4, 5, 6, 7, 8]],
    'page1 digest length' => [static fn (): mixed => strlen($plan()['current_source']['page1_digest']), 64],
    'retained page numbers' => [static fn (): mixed => $plan()['cache']['retained_page_numbers'], [1]],
    'invalidated page numbers' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5, 6, 7, 8]],
    'old source reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'reader_cache_source_id_not_current'],
    'old epoch reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'reader_cache_epoch_not_current'],
    'old master reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'reader_cache_master_journal_digest_not_current'],
    'old change reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'reader_cache_change_counter_predates_current_header'],
    'old schema reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][4]['reason'], 'reader_cache_schema_cookie_predates_current_header'],
    'old frame reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][5]['reason'], 'reader_cache_end_frame_predates_current_wal_source'],
    'stale image reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][6]['reason'], 'reader_cache_image_digest_not_current'],
    'cache row count' => [static fn (): mixed => count($plan()['cache']['rows']), 8],
    'cache row one admitted' => [static fn (): mixed => $plan()['cache']['rows'][0]['reason'], 'reader_cache_admitted_by_master_journal_header_generation'],
    'cache row four digest mismatch' => [static fn (): mixed => $plan()['cache']['rows'][3]['master_journal_digest_matches'], false],
    'cache row five change counter' => [static fn (): mixed => $plan()['cache']['rows'][4]['change_counter'], 43],
    'cache row eight image mismatch' => [static fn (): mixed => $plan()['cache']['rows'][7]['image_matches_current'], false],
    'read count' => [static fn (): mixed => count($plan()['reads']), 6],
    'header read ticket current' => [static fn (): mixed => $plan()['reads'][0]['ticket_current'], true],
    'header read cache hit' => [static fn (): mixed => $plan()['reads'][0]['cache_hit'], true],
    'header read source' => [static fn (): mixed => $plan()['reads'][0]['source'], 'reader-cache-current-master-header-generation'],
    'header read prefix' => [static fn (): mixed => $plan()['reads'][0]['prefix'], 'next165 page1 header counter schema cookie after master recovery'],
    'old source ticket stale' => [static fn (): mixed => $plan()['reads'][1]['ticket_current'], false],
    'old source cache miss' => [static fn (): mixed => $plan()['reads'][1]['cache_hit'], false],
    'old source prefix current' => [static fn (): mixed => $plan()['reads'][1]['prefix'], 'next165 current wp_options root after master recovery'],
    'old change ticket stale' => [static fn (): mixed => $plan()['reads'][2]['ticket_current'], false],
    'old schema ticket stale' => [static fn (): mixed => $plan()['reads'][3]['ticket_current'], false],
    'old frame ticket stale' => [static fn (): mixed => $plan()['reads'][4]['ticket_current'], false],
    'stale image ticket current but miss' => [static fn (): mixed => $plan()['reads'][5]['ticket_current'], true],
    'stale image cache miss' => [static fn (): mixed => $plan()['reads'][5]['cache_hit'], false],
    'stale image source current' => [static fn (): mixed => $plan()['reads'][5]['source'], 'current-master-journal-header-generation-source'],
    'reopen reader ids' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['old-source-reader', 'old-change-reader', 'old-schema-reader', 'old-frame-reader', 'stale-image-reader']],
    'read cache hits header' => [static fn (): mixed => $plan()['read_cache_hits']['header-reader'], true],
    'read cache hits stale image' => [static fn (): mixed => $plan()['read_cache_hits']['stale-image-reader'], false],
    'read prefix map' => [static fn (): mixed => $plan()['read_prefixes']['old-schema-reader'], 'next165 current site options after master recovery'],
    'operation first' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_and_header_generation_before_reader_cache'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_after_master_journal_header_generation_fence'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][2]['op'], 'invalidate_reader_cache_on_master_journal_header_generation_fence'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][9]['op'], 'next_reader_cache_hit_header_generation_current'],
    'operation read reopen' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_reader_reopen_header_generation_current_source'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next165' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next165', $plan()['dependencies'], true), true],
    'dependency next162' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next162', $plan()['dependencies'], true), true],
    'dependency header fence' => [static fn (): mixed => in_array('sqlite-master-journal-header-generation-reader-cache-fence', $plan()['dependencies'], true), true],
    'duplicate members collapse' => [static fn (): mixed => $plan(null, null, null, $currentMaster . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next165-network.sqlite-journal']],
    'default read ticket current' => [static fn (): mixed => $plan(null, $validSingle, $defaultRead)['reads'][0]['ticket_current'], true],
    'default read cache hit' => [static fn (): mixed => $plan(null, $validSingle, $defaultRead)['reads'][0]['cache_hit'], true],
    'default reader id synthesized' => [static fn (): mixed => $plan(null, [1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 21, 'master_journal_digest' => $digest, 'change_counter' => 44, 'schema_cookie' => 9, 'end_frame' => 12]], $defaultRead)['cache']['rows'][0]['reader_id'], 'reader-1'],
    'dirty reason wins' => [static fn (): mixed => $plan(null, $dirty, $defaultRead)['cache']['invalidated_entries'][0]['reason'], 'dirty_reader_cache_page_after_master_journal_recovery'],
    'pinned reason wins after dirty' => [static fn (): mixed => $plan(null, $pinned, $defaultRead)['cache']['invalidated_entries'][0]['reason'], 'pinned_reader_cache_page_needs_reopen_after_master_journal_recovery'],
    'read digest length' => [static fn (): mixed => strlen($plan()['reads'][0]['digest']), 64],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next165 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 21, 44, 9, 12, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 21, 44, 9, 12, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, '__default__', null, ''),
    'blank current master rejected' => static fn () => $plan(null, null, null, ''),
    'wrong current master rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, '__default__', 500),
    'bad epoch rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 0),
    'bad change counter rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 21, -1),
    'bad schema cookie rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 21, 44, -1),
    'bad end frame rejected' => static fn () => $plan(null, null, null, '__default__', null, null, 21, 44, 9, -1),
    'empty pages rejected' => static fn () => $plan([]),
    'missing page one rejected' => static fn () => $plan([2 => $currentPages[2]]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'zero current page rejected' => static fn () => $plan([0 => $currentPages[1]]),
    'short current page rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $readerCache[1]]),
    'short cache page rejected' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => ['image' => $currentPages[1], 'epoch' => -1]]),
    'bad cache change rejected' => static fn () => $plan(null, [1 => ['image' => $currentPages[1], 'change_counter' => -1]]),
    'bad cache schema rejected' => static fn () => $plan(null, [1 => ['image' => $currentPages[1], 'schema_cookie' => -1]]),
    'bad cache frame rejected' => static fn () => $plan(null, [1 => ['image' => $currentPages[1], 'end_frame' => -1]]),
    'empty reader id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 0]]),
    'negative read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'epoch' => -1]]),
    'negative read change rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'change_counter' => -1]]),
    'negative read schema rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'schema_cookie' => -1]]),
    'negative read frame rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad-reader', 'page_number' => 1, 'end_frame' => -1]]),
    'read outside current source rejected' => static fn () => $plan(null, null, [['reader_id' => 'outside-reader', 'page_number' => 9]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next165 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

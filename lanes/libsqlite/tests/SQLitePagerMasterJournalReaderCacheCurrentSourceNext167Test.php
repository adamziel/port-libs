<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next167.sqlite';
$masterPath = '/srv/wp-content/database/wp-next167.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next167-meta.sqlite-journal\n";
$sourceId = 'next167-current-after-master-delete';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('next167 current schema after master journal delete'),
    2 => $page('next167 current wp_options root after master journal delete'),
    3 => $page('next167 current active_plugins after master journal delete'),
    4 => $page('next167 current plugin settings after master journal delete'),
    5 => $page('next167 current transients after master journal delete'),
    6 => $page('next167 current cron after master journal delete'),
];
$cache = [
    1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'schema-reader', 'master_generation' => 44, 'master_deleted' => true, 'shared' => true],
    2 => ['image' => $page('next167 stale wp_options root before master journal delete'), 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'options-reader', 'master_generation' => 44, 'master_deleted' => true],
    3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'old-generation-reader', 'master_generation' => 43, 'master_deleted' => true],
    4 => ['image' => $currentPages[4], 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'present-master-reader', 'master_generation' => 44, 'master_deleted' => false],
    5 => ['image' => $currentPages[5], 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'dirty-reader', 'master_generation' => 44, 'master_deleted' => true, 'dirty' => true],
    6 => ['image' => $page('next167 stale cron before master journal delete'), 'source_id' => $sourceId, 'epoch' => 18, 'reader_id' => 'pinned-reader', 'master_generation' => 44, 'master_deleted' => true, 'pinned' => true],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44],
    ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44],
    ['reader_id' => 'old-generation-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 43],
    ['reader_id' => 'present-master-reader', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44],
    ['reader_id' => 'dirty-reader', 'page_number' => 5, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44],
    ['reader_id' => 'pinned-reader', 'page_number' => 6, 'source_id' => $sourceId, 'epoch' => 17, 'master_generation' => 44],
];

$plan = static fn (
    ?array $pages = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $master = null,
    ?int $size = null,
    ?string $path = null,
    ?string $mjPath = null,
    ?string $source = null,
    int $epoch = 18,
    int $generation = 44,
    bool $deleted = true,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext167(
    $path ?? $databasePath,
    $mjPath ?? $masterPath,
    $master ?? $masterBytes,
    $size ?? $pageSize,
    $pages ?? $currentPages,
    $readerCache ?? $cache,
    $nextReads ?? $reads,
    $source ?? $sourceId,
    $epoch,
    $generation,
    $deleted,
);

$samePresent = static function () use ($plan, $cache, $reads): array {
    $present = $cache;
    $present[1]['master_deleted'] = false;
    return $plan(null, [1 => $present[1]], [$reads[0]], null, null, null, null, null, 18, 44, false);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next167'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_deleted_generation_is_part_of_reader_cache_current_source_ticket'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next167-meta.sqlite-journal']],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 18],
    'source generation' => [static fn (): mixed => $plan()['current_source']['master_generation'], 44],
    'source deleted' => [static fn (): mixed => $plan()['current_source']['master_journal_deleted'], true],
    'master digest length' => [static fn (): mixed => strlen($plan()['current_source']['master_journal_digest']), 64],
    'row count' => [static fn (): mixed => count($plan()['cache_rows']), 6],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema row admitted' => [static fn (): mixed => $plan()['cache_rows'][0]['admitted'], true],
    'schema row reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_current_master_generation'],
    'schema shared row' => [static fn (): mixed => $plan()['cache_rows'][0]['shared'], true],
    'options row reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_current_master_generation'],
    'options image mismatch' => [static fn (): mixed => $plan()['cache_rows'][1]['image_matches_current_source'], false],
    'old generation reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_master_generation_mismatch'],
    'deleted mismatch reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'reader_cache_master_deleted_state_mismatch'],
    'dirty reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'dirty_reader_cache_after_master_journal_recovery'],
    'pinned reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'pinned_reader_cache_image_mismatch_after_master_delete'],
    'invalidated dirty flag' => [static fn (): mixed => $plan()['invalidated_entries'][2]['dirty'], true],
    'invalidated pinned flag' => [static fn (): mixed => $plan()['invalidated_entries'][3]['pinned'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 6],
    'schema read ticket current' => [static fn (): mixed => $plan()['next_reads'][0]['ticket_current'], true],
    'schema read cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'schema read source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-retained-current-master-generation'],
    'schema read prefix' => [static fn (): mixed => $plan()['next_reads'][0]['prefix'], 'next167 current schema after master journal delete'],
    'options read cache hit after refresh' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'options read source' => [static fn (): mixed => $plan()['next_reads'][1]['source'], 'reader-cache-refreshed-current-master-generation'],
    'options read prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next167 current wp_options root after master journal delete'],
    'old generation ticket stale' => [static fn (): mixed => $plan()['next_reads'][2]['ticket_current'], false],
    'old generation cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'present master cache miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'dirty reader current page' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next167 current transients after master journal delete'],
    'pinned reader stale ticket' => [static fn (): mixed => $plan()['next_reads'][5]['ticket_current'], false],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['old-generation-reader', 'pinned-reader']],
    'hit map schema' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'hit map dirty' => [static fn (): mixed => $plan()['read_cache_hits']['dirty-reader'], false],
    'prefix map options' => [static fn (): mixed => $plan()['read_prefixes']['options-reader'], 'next167 current wp_options root after master journal delete'],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache_source_ticket'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_current_master_generation'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][2]['op'], 'refresh_reader_cache_from_current_master_generation'],
    'operation invalidate generation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_master_generation_ticket'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][7]['op'], 'next_reader_cache_hit_current_master_generation'],
    'operation read reopen' => [static fn (): mixed => $plan()['operations'][9]['op'], 'next_reader_reopen_current_master_generation_page'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next167', $plan()['dependencies'], true), true],
    'dependency prior current source' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next164', $plan()['dependencies'], true), true],
    'dependency generation ticket' => [static fn (): mixed => in_array('sqlite-master-journal-deleted-generation-reader-ticket', $plan()['dependencies'], true), true],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, null, $masterBytes . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next167-meta.sqlite-journal']],
    'present master can retain present cache' => [static fn (): mixed => $samePresent()['retained_page_numbers'], [1]],
    'present master source deleted false' => [static fn (): mixed => $samePresent()['current_source']['master_journal_deleted'], false],
    'present master read cache hit' => [static fn (): mixed => $samePresent()['next_reads'][0]['cache_hit'], true],
    'source mismatch invalidates' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['source_id' => 'old-source'])], [$reads[0]])['cache_rows'][0]['reason'], 'reader_cache_source_id_mismatch'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['epoch' => 17])], [$reads[0]])['cache_rows'][0]['reason'], 'reader_cache_epoch_mismatch'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next167 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
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
    'bad generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 18, 0),
    'empty pages rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty reads rejected' => static fn () => $plan(null, null, []),
    'zero current page rejected' => static fn () => $plan([0 => $currentPages[1]]),
    'short current page rejected' => static fn () => $plan([1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cache[1]]),
    'short cache page rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['epoch' => 0])]),
    'bad cache generation rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['master_generation' => 0])]),
    'cache outside rejected' => static fn () => $plan(null, [7 => array_merge($cache[1], ['image' => $page('outside')])]),
    'empty read id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44]]),
    'empty read source rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 18, 'master_generation' => 44]]),
    'bad read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 0, 'master_generation' => 44]]),
    'bad read generation rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 0]]),
    'read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'outside', 'page_number' => 7, 'source_id' => $sourceId, 'epoch' => 18, 'master_generation' => 44]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next167 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

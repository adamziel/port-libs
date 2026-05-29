<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next171.sqlite';
$masterPath = '/srv/wp-content/database/wp-next171.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next171-meta.sqlite-journal\n";
$membersDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next171-meta.sqlite-journal");
$sourceId = 'next171-current-after-master-recovery-ticket';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('next171 current schema after master recovery ticket'),
    2 => $page('next171 current wp_options root after master recovery ticket'),
    3 => $page('next171 current active_plugins after master recovery ticket'),
    4 => $page('next171 current plugin settings after master recovery ticket'),
    5 => $page('next171 current transients after master recovery ticket'),
    6 => $page('next171 current cron after master recovery ticket'),
    7 => $page('next171 current rewrite rules after master recovery ticket'),
    8 => $page('next171 current option autoload after master recovery ticket'),
];
$cache = [
    1 => ['image' => $currentPages[1], 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'schema-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 14, 'shared' => true],
    2 => ['image' => $page('next171 stale wp_options root before recovery ticket'), 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'options-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    3 => ['image' => $currentPages[3], 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'old-recovery-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 8, 'read_lock_generation' => 14],
    4 => ['image' => $currentPages[4], 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'old-lock-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 13],
    5 => ['image' => $currentPages[5], 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'old-digest-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => str_repeat('a', 64), 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    6 => ['image' => $currentPages[6], 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'dirty-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 14, 'dirty' => true],
    7 => ['image' => $page('next171 stale rewrite pinned before recovery ticket'), 'source_id' => $sourceId, 'epoch' => 22, 'reader_id' => 'pinned-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 14, 'pinned' => true],
    8 => ['image' => $currentPages[8], 'source_id' => 'old-source', 'epoch' => 22, 'reader_id' => 'old-source-reader', 'master_generation' => 51, 'master_deleted' => true, 'master_digest' => $membersDigest, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    ['reader_id' => 'options-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    ['reader_id' => 'old-recovery-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 8, 'read_lock_generation' => 14],
    ['reader_id' => 'old-lock-reader', 'page_number' => 4, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 13],
    ['reader_id' => 'old-digest-reader', 'page_number' => 5, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    ['reader_id' => 'dirty-reader', 'page_number' => 6, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    ['reader_id' => 'pinned-reader', 'page_number' => 7, 'source_id' => $sourceId, 'epoch' => 21, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
    ['reader_id' => 'old-source-reader', 'page_number' => 8, 'source_id' => 'old-source', 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14],
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
    int $epoch = 22,
    int $generation = 51,
    bool $deleted = true,
    int $recovery = 9,
    int $lockGeneration = 14,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext171(
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
    $recovery,
    $lockGeneration,
);

$cleanRetain = static function () use ($plan, $cache, $reads): array {
    return $plan(null, [1 => $cache[1]], [$reads[0]]);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next171'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_sequence_and_read_lock_are_part_of_reader_cache_ticket'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next171-meta.sqlite-journal']],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 22],
    'source generation' => [static fn (): mixed => $plan()['current_source']['master_generation'], 51],
    'source deleted' => [static fn (): mixed => $plan()['current_source']['master_journal_deleted'], true],
    'source recovery sequence' => [static fn (): mixed => $plan()['current_source']['recovery_sequence'], 9],
    'source read lock generation' => [static fn (): mixed => $plan()['current_source']['read_lock_generation'], 14],
    'master digest' => [static fn (): mixed => $plan()['current_source']['master_journal_digest'], $membersDigest],
    'row count' => [static fn (): mixed => count($plan()['cache_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema admitted' => [static fn (): mixed => $plan()['cache_rows'][0]['admitted'], true],
    'schema reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_current_master_recovery_ticket'],
    'schema shared' => [static fn (): mixed => $plan()['cache_rows'][0]['shared'], true],
    'schema digest matches' => [static fn (): mixed => $plan()['cache_rows'][0]['master_digest_matches'], true],
    'schema recovery sequence' => [static fn (): mixed => $plan()['cache_rows'][0]['recovery_sequence'], 9],
    'schema read lock generation' => [static fn (): mixed => $plan()['cache_rows'][0]['read_lock_generation'], 14],
    'options reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_current_master_recovery_ticket'],
    'options image mismatch' => [static fn (): mixed => $plan()['cache_rows'][1]['image_matches_current_source'], false],
    'old recovery reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_recovery_sequence_mismatch'],
    'old lock reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'reader_cache_read_lock_generation_mismatch'],
    'old digest reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'reader_cache_master_digest_mismatch'],
    'old digest mismatch flag' => [static fn (): mixed => $plan()['cache_rows'][4]['master_digest_matches'], false],
    'dirty reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'dirty_reader_cache_after_master_journal_recovery'],
    'pinned reason' => [static fn (): mixed => $plan()['cache_rows'][6]['reason'], 'pinned_reader_cache_image_mismatch_after_master_recovery'],
    'source mismatch reason' => [static fn (): mixed => $plan()['cache_rows'][7]['reason'], 'reader_cache_source_id_mismatch'],
    'invalidated recovery sequence' => [static fn (): mixed => $plan()['invalidated_entries'][0]['recovery_sequence'], 8],
    'invalidated lock generation' => [static fn (): mixed => $plan()['invalidated_entries'][1]['read_lock_generation'], 13],
    'invalidated dirty flag' => [static fn (): mixed => $plan()['invalidated_entries'][3]['dirty'], true],
    'invalidated pinned flag' => [static fn (): mixed => $plan()['invalidated_entries'][4]['pinned'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'schema read ticket current' => [static fn (): mixed => $plan()['next_reads'][0]['ticket_current'], true],
    'schema read cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'schema read source' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-retained-current-master-recovery-ticket'],
    'schema read prefix' => [static fn (): mixed => $plan()['next_reads'][0]['prefix'], 'next171 current schema after master recovery ticket'],
    'options read cache hit after refresh' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'options read source' => [static fn (): mixed => $plan()['next_reads'][1]['source'], 'reader-cache-refreshed-current-master-recovery-ticket'],
    'options read prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next171 current wp_options root after master recovery ticket'],
    'old recovery ticket stale' => [static fn (): mixed => $plan()['next_reads'][2]['ticket_current'], false],
    'old recovery cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'old lock ticket stale' => [static fn (): mixed => $plan()['next_reads'][3]['ticket_current'], false],
    'old lock cache miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'old digest current read ticket still current' => [static fn (): mixed => $plan()['next_reads'][4]['ticket_current'], true],
    'old digest invalidated cache miss' => [static fn (): mixed => $plan()['next_reads'][4]['cache_hit'], false],
    'dirty reader current page' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next171 current cron after master recovery ticket'],
    'pinned reader stale ticket' => [static fn (): mixed => $plan()['next_reads'][6]['ticket_current'], false],
    'old source ticket stale' => [static fn (): mixed => $plan()['next_reads'][7]['ticket_current'], false],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['old-recovery-reader', 'old-lock-reader', 'pinned-reader', 'old-source-reader']],
    'hit map schema' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'hit map old digest' => [static fn (): mixed => $plan()['read_cache_hits']['old-digest-reader'], false],
    'prefix map options' => [static fn (): mixed => $plan()['read_prefixes']['options-reader'], 'next171 current wp_options root after master recovery ticket'],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_reader_cache_recovery_ticket'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_current_master_recovery_ticket'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][2]['op'], 'refresh_reader_cache_from_current_master_recovery_ticket'],
    'operation invalidate recovery' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_recovery_ticket'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][9]['op'], 'next_reader_cache_hit_current_master_recovery_ticket'],
    'operation read reopen' => [static fn (): mixed => $plan()['operations'][11]['op'], 'next_reader_reopen_current_master_recovery_page'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next171', $plan()['dependencies'], true), true],
    'dependency prior current source' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next167', $plan()['dependencies'], true), true],
    'dependency recovery ticket' => [static fn (): mixed => in_array('sqlite-master-journal-recovery-sequence-reader-ticket', $plan()['dependencies'], true), true],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, null, $masterBytes . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next171-meta.sqlite-journal']],
    'clean retain page' => [static fn (): mixed => $cleanRetain()['retained_page_numbers'], [1]],
    'clean retain no reopen' => [static fn (): mixed => $cleanRetain()['requires_reader_reopen'], false],
    'present master can retain present cache' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['master_deleted' => false])], [$reads[0]], null, null, null, null, null, 22, 51, false)['retained_page_numbers'], [1]],
    'deleted mismatch invalidates' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['master_deleted' => false])], [$reads[0]])['cache_rows'][0]['reason'], 'reader_cache_master_deleted_state_mismatch'],
    'generation mismatch invalidates' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['master_generation' => 50])], [$reads[0]])['cache_rows'][0]['reason'], 'reader_cache_master_generation_mismatch'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan(null, [1 => array_merge($cache[1], ['epoch' => 21])], [$reads[0]])['cache_rows'][0]['reason'], 'reader_cache_epoch_mismatch'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next171 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
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
    'bad generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 22, 0),
    'bad recovery sequence rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 22, 51, true, 0),
    'bad read-lock generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 22, 51, true, 9, 0),
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
    'bad cache recovery rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['recovery_sequence' => 0])]),
    'bad cache read-lock rejected' => static fn () => $plan(null, [1 => array_merge($cache[1], ['read_lock_generation' => 0])]),
    'cache outside rejected' => static fn () => $plan(null, [9 => array_merge($cache[1], ['image' => $page('outside')])]),
    'empty read id rejected' => static fn () => $plan(null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
    'zero read page rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
    'empty read source rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
    'bad read epoch rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 0, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
    'bad read generation rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 0, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
    'bad read recovery rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 0, 'read_lock_generation' => 14]]),
    'bad read lock rejected' => static fn () => $plan(null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 0]]),
    'read outside rejected' => static fn () => $plan(null, null, [['reader_id' => 'outside', 'page_number' => 9, 'source_id' => $sourceId, 'epoch' => 22, 'master_generation' => 51, 'recovery_sequence' => 9, 'read_lock_generation' => 14]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next171 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

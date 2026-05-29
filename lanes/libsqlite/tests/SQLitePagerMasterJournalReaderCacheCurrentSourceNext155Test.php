<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next155.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp-content/database/wp-next155.sqlite-mj';
$masterBytes = $journalPath . "\n/srv/wp-content/database/site-next155.sqlite-journal\n";
$sourceId = 'master-reader-current-next155';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('next155 stale schema page before master recovery'),
    2 => $page('next155 stale wp_options root before master recovery'),
    3 => $page('next155 stale autoload index before master recovery'),
    4 => $page('next155 unchanged comments page before master recovery'),
    5 => $page('next155 stale plugin settings before master recovery'),
    6 => $page('next155 stale pinned reader before master recovery'),
    7 => $page('next155 stale dirty reader before master recovery'),
];
$databaseBytes = implode('', $before);
$recovered = [
    1 => $page('next155 recovered schema page current source'),
    2 => $page('next155 recovered wp_options root current source'),
    3 => $page('next155 recovered autoload index current source'),
    5 => $page('next155 recovered plugin settings current source'),
    6 => $page('next155 recovered pinned reader current source'),
    7 => $page('next155 recovered dirty reader current source'),
];
$cache = [
    1 => ['image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 3, 'source' => 'reader-cache-after-master-recovery'],
    2 => ['image' => $before[2], 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 3, 'source' => 'clean-reader-cache-before-master-recovery'],
    3 => ['image' => $recovered[3], 'source_id' => 'old-master-reader-source', 'epoch' => 9, 'reader_generation' => 3],
    4 => ['image' => $before[4], 'source_id' => $sourceId, 'epoch' => 8, 'reader_generation' => 3],
    5 => ['image' => $recovered[5], 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 2],
    6 => ['image' => $before[6], 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 3, 'pinned' => true],
    7 => ['image' => $before[7], 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 3, 'dirty' => true],
];

$plan = static fn (
    ?array $recoveredPages = null,
    ?array $cachePages = null,
    ?array $readPages = null,
    mixed $master = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $journal = null,
    ?string $masterJournal = null,
    ?string $source = null,
    int $epoch = 9,
    int $generation = 3,
    bool $refresh = true,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext155(
    $path ?? $databasePath,
    $journal ?? $journalPath,
    $masterJournal ?? $masterPath,
    $master === '__default__' ? $masterBytes : $master,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $cachePages ?? $cache,
    $readPages ?? [1, 2, 3, 4, 5, 6, 7],
    $source ?? $sourceId,
    $epoch,
    $generation,
    $refresh,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_master_journal_reader_cache_current_source_next155'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_revalidates_reader_cache_before_next_read'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'master members' => [static fn (): mixed => $plan()['master_members'], [$journalPath, '/srv/wp-content/database/site-next155.sqlite-journal']],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'current id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 9],
    'current generation' => [static fn (): mixed => $plan()['current_source']['reader_generation'], 3],
    'next id prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-cache:'), true],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 10],
    'next generation' => [static fn (): mixed => $plan()['next_source']['reader_generation'], 4],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 5, 6, 7]],
    'retained page' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed page' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'invalidated source reason' => [static fn (): mixed => $plan()['invalidated_cache_pages'][3], ['stale_master_source_id']],
    'invalidated epoch reason' => [static fn (): mixed => $plan()['invalidated_cache_pages'][4], ['stale_master_source_epoch']],
    'invalidated generation reason' => [static fn (): mixed => $plan()['invalidated_cache_pages'][5], ['stale_reader_generation']],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['invalidated_cache_pages'][6], ['pinned_reader_cache_predates_master_recovery']],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['invalidated_cache_pages'][7], ['dirty_reader_cache_page']],
    'row count' => [static fn (): mixed => count($plan()['reader_cache_rows']), 7],
    'row one match' => [static fn (): mixed => $plan()['reader_cache_rows_by_page'][1]['image_matches_current_source'], true],
    'row two mismatch' => [static fn (): mixed => $plan()['reader_cache_rows_by_page'][2]['image_matches_current_source'], false],
    'row six pinned' => [static fn (): mixed => $plan()['reader_cache_rows_by_page'][6]['pinned'], true],
    'row seven dirty' => [static fn (): mixed => $plan()['reader_cache_rows_by_page'][7]['dirty'], true],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read one cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read one source retained' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'reader-cache-after-master-recovery'],
    'read two cache refreshed' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read two prefix recovered' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next155 recovered wp_options root current source'],
    'read three miss source' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read four unchanged miss prefix' => [static fn (): mixed => $plan()['next_reads'][3]['prefix'], 'next155 unchanged comments page before master recovery'],
    'read five recovered miss prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next155 recovered plugin settings current source'],
    'read six recovered miss prefix' => [static fn (): mixed => $plan()['next_reads'][5]['prefix'], 'next155 recovered pinned reader current source'],
    'read seven recovered miss prefix' => [static fn (): mixed => $plan()['next_reads'][6]['prefix'], 'next155 recovered dirty reader current source'],
    'operation reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal_for_reader_cache'],
    'operation restores page one' => [static fn (): mixed => $plan()['operations'][1]['op'], 'restore_master_journal_page_before_reader_cache'],
    'operation restores page seven' => [static fn (): mixed => $plan()['operations'][6]['page_number'], 7],
    'operation retains page one' => [static fn (): mixed => $plan()['operations'][7]['op'], 'retain_master_journal_reader_cache_page'],
    'operation refreshes page two' => [static fn (): mixed => $plan()['operations'][8]['op'], 'refresh_master_journal_reader_cache_page'],
    'operation invalidates source page' => [static fn (): mixed => $plan()['operations'][9]['reasons'], ['stale_master_source_id']],
    'operation invalidates dirty page' => [static fn (): mixed => $plan()['operations'][13]['reasons'], ['dirty_reader_cache_page']],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][14]['op'], 'next_reader_master_journal_cache_hit'],
    'operation read miss' => [static fn (): mixed => $plan()['operations'][16]['op'], 'next_reader_master_journal_cache_miss'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next155', $plan()['dependencies'], true), true],
    'dependency member validation' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-member-validation', $plan()['dependencies'], true), true],
    'dependency reader cache' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-current-source', $plan()['dependencies'], true), true],
    'duplicate master members collapsed' => [static fn (): mixed => $plan(null, null, [1], $masterBytes . $journalPath . "\n")['master_members'], [$journalPath, '/srv/wp-content/database/site-next155.sqlite-journal']],
    'no refresh invalidates page two' => [static fn (): mixed => $plan(null, null, [2], '__default__', null, null, null, null, null, null, 9, 3, false)['invalidated_cache_pages'][2], ['clean_reader_cache_refresh_disabled']],
    'no shared lock rejected' => [static fn (): mixed => $plan(null, [1 => array_replace($cache[1], ['shared_lock' => false])])['invalidated_cache_pages'][1], ['reader_without_shared_lock']],
    'multiple rejection reasons' => [static fn (): mixed => $plan(null, [1 => array_replace($cache[1], ['shared_lock' => false, 'dirty' => true, 'source_id' => 'old'])])['invalidated_cache_pages'][1], ['reader_without_shared_lock', 'dirty_reader_cache_page', 'stale_master_source_id']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next155 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => $plan(null, null, null, '__default__', null, null, ''),
    'rejects empty journal path' => static fn () => $plan(null, null, null, '__default__', null, null, null, ''),
    'rejects empty master path' => static fn () => $plan(null, null, null, '__default__', null, null, null, null, ''),
    'rejects missing master' => static fn () => $plan(null, null, null, null),
    'rejects master without member' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'rejects empty database bytes' => static fn () => $plan(null, null, null, '__default__', ''),
    'rejects small page size' => static fn () => $plan(null, null, null, '__default__', null, 256),
    'rejects non power page size' => static fn () => $plan([1 => str_pad('x', 768, '.')], [1 => ['image' => str_pad('x', 768, '.'), 'source_id' => $sourceId, 'epoch' => 9, 'reader_generation' => 3]], [1], '__default__', str_pad('db', 768, '.') . str_pad('db2', 768, '.'), 768),
    'rejects unaligned database bytes' => static fn () => $plan(null, null, null, '__default__', $databaseBytes . 'x'),
    'rejects empty recovered pages' => static fn () => $plan([]),
    'rejects empty cache pages' => static fn () => $plan(null, []),
    'rejects empty reads' => static fn () => $plan(null, null, []),
    'rejects empty source id' => static fn () => $plan(null, null, null, '__default__', null, null, null, null, null, ''),
    'rejects bad epoch' => static fn () => $plan(null, null, null, '__default__', null, null, null, null, null, null, 0),
    'rejects bad generation' => static fn () => $plan(null, null, null, '__default__', null, null, null, null, null, null, 9, 0),
    'rejects zero recovered page' => static fn () => $plan([0 => $recovered[1]]),
    'rejects outside recovered page' => static fn () => $plan([8 => $recovered[1]]),
    'rejects short recovered image' => static fn () => $plan([1 => 'short']),
    'rejects zero cache page' => static fn () => $plan(null, [0 => $cache[1]]),
    'rejects outside cache page' => static fn () => $plan(null, [8 => $cache[1]]),
    'rejects short cache image' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'rejects empty cache source' => static fn () => $plan(null, [1 => array_replace($cache[1], ['source_id' => ''])]),
    'rejects bad cache epoch' => static fn () => $plan(null, [1 => array_replace($cache[1], ['epoch' => 0])]),
    'rejects bad cache generation' => static fn () => $plan(null, [1 => array_replace($cache[1], ['reader_generation' => 0])]),
    'rejects zero read page' => static fn () => $plan(null, null, [0]),
    'rejects outside read page' => static fn () => $plan(null, null, [8]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next155 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

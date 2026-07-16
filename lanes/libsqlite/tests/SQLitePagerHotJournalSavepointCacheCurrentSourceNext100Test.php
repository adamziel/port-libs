<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = static fn (): array => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(
    $pageSize,
    'plugin_import',
    'wal-salt-11:journal-7',
    'hot-recovered-12:journal-deleted',
    [
        1 => ['image' => $page('page1 schema cache'), 'source' => 'database', 'epoch' => 11, 'source_id' => 'wal-salt-11:journal-7'],
        2 => ['image' => $page('page2 stale wal cache'), 'source' => 'wal', 'epoch' => 11, 'source_id' => 'wal-salt-11:journal-7'],
        3 => ['image' => $page('page3 dirty aborted'), 'source' => 'savepoint-current-write', 'epoch' => 11, 'source_id' => 'wal-salt-11:journal-7', 'dirty' => true],
        4 => ['image' => $page('page4 stale epoch'), 'source' => 'database', 'epoch' => 10, 'source_id' => 'wal-salt-11:journal-7'],
        5 => ['image' => $page('page5 stale source'), 'source' => 'database', 'epoch' => 11, 'source_id' => 'wal-salt-10:journal-6'],
        7 => ['image' => $page('page7 option cache'), 'source' => 'database', 'epoch' => 11, 'source_id' => 'wal-salt-11:journal-7'],
    ],
    [
        2 => $page('page2 hot journal restored'),
        6 => $page('page6 hot journal restored'),
    ],
    [
        2 => $page('page2 plugin current write'),
        6 => $page('page6 index current write'),
    ],
    [1, 2, 3, 6, 7, 8],
    11,
);

$cases = [
    'status names next100 boundary' => [static fn (): mixed => $plan()['status'], 'hot_journal_savepoint_cache_current_source_next100'],
    'page size preserved' => [static fn (): mixed => $plan()['page_size'], 64],
    'savepoint name preserved' => [static fn (): mixed => $plan()['savepoint']['name'], 'plugin_import'],
    'savepoint released after rollback' => [static fn (): mixed => $plan()['savepoint']['released'], true],
    'current source id recorded' => [static fn (): mixed => $plan()['current_source']['id'], 'wal-salt-11:journal-7'],
    'next source id recorded' => [static fn (): mixed => $plan()['next_source']['id'], 'hot-recovered-12:journal-deleted'],
    'current epoch recorded' => [static fn (): mixed => $plan()['current_source']['epoch'], 11],
    'next epoch increments' => [static fn (): mixed => $plan()['next_source']['epoch'], 12],
    'recovered page numbers sorted' => [static fn (): mixed => $plan()['cache']['recovered_page_numbers'], [2, 6]],
    'invalidated page numbers sorted by cache order' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5]],
    'hot journal invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'hot_journal_recovered_page'],
    'hot journal invalidation source' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['source'], 'wal'],
    'dirty aborted invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'dirty_cache_from_aborted_savepoint'],
    'dirty aborted flag preserved' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['dirty'], true],
    'stale epoch invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'stale_current_source_epoch'],
    'stale epoch value preserved' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['epoch'], 10],
    'stale source invalidation reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'stale_current_source_id'],
    'stale source id preserved' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['source_id'], 'wal-salt-10:journal-6'],
    'preserved page numbers' => [static fn (): mixed => $plan()['cache']['preserved_page_numbers'], [1, 7]],
    'preserved first page source' => [static fn (): mixed => $plan()['cache']['preserved_entries'][0]['source'], 'database'],
    'preserved second page number' => [static fn (): mixed => $plan()['cache']['preserved_entries'][1]['page_number'], 7],
    'final page numbers exclude stale cache only pages' => [static fn (): mixed => $plan()['cache']['final_page_numbers'], [1, 2, 6, 7]],
    'final page one source remains database' => [static fn (): mixed => $plan()['cache']['final_sources'][1], 'database'],
    'final page two source is rollback image' => [static fn (): mixed => $plan()['cache']['final_sources'][2], 'savepoint-rollback-before-image'],
    'final page six source is rollback image' => [static fn (): mixed => $plan()['cache']['final_sources'][6], 'savepoint-rollback-before-image'],
    'final page seven source remains database' => [static fn (): mixed => $plan()['cache']['final_sources'][7], 'database'],
    'final page one source id advanced' => [static fn (): mixed => $plan()['cache']['final_source_ids'][1], 'hot-recovered-12:journal-deleted'],
    'final page two source id advanced' => [static fn (): mixed => $plan()['cache']['final_source_ids'][2], 'hot-recovered-12:journal-deleted'],
    'no dirty pages remain after rollback' => [static fn (): mixed => $plan()['cache']['dirty_page_numbers'], []],
    'savepoint captures recovered pages' => [static fn (): mixed => $plan()['savepoint']['captured_page_numbers'], [2, 6]],
    'savepoint rollback restores recovered pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 6]],
    'release read count' => [static fn (): mixed => count($plan()['release_reads']), 6],
    'release read page one cache hit' => [static fn (): mixed => $plan()['release_reads'][0]['cache_hit'], true],
    'release read page one source' => [static fn (): mixed => $plan()['release_reads'][0]['source'], 'database'],
    'release read page two cache hit' => [static fn (): mixed => $plan()['release_reads'][1]['cache_hit'], true],
    'release read page two matches rollback image' => [static fn (): mixed => $plan()['release_reads'][1]['matches_rollback_before_image'], true],
    'release read page three cache miss after dirty invalidation' => [static fn (): mixed => $plan()['release_reads'][2]['cache_hit'], false],
    'release read page three zero fills' => [static fn (): mixed => $plan()['release_reads'][2]['zero_filled_short_read'], true],
    'release read page six matches rollback image' => [static fn (): mixed => $plan()['release_reads'][3]['matches_rollback_before_image'], true],
    'release read page seven cache hit' => [static fn (): mixed => $plan()['release_reads'][4]['cache_hit'], true],
    'release read page eight miss' => [static fn (): mixed => $plan()['release_reads'][5]['cache_hit'], false],
    'release read page eight source id is next source' => [static fn (): mixed => $plan()['release_reads'][5]['source_id'], 'hot-recovered-12:journal-deleted'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 14],
    'operation zero installs hot journal' => [static fn (): mixed => $plan()['operations'][0]['op'], 'install_hot_journal_page'],
    'operation one installs second hot journal page' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 6],
    'operation two captures page two before image' => [static fn (): mixed => $plan()['operations'][2]['op'], 'capture_savepoint_before_image'],
    'operation three writes savepoint page' => [static fn (): mixed => $plan()['operations'][3]['op'], 'write_savepoint_page'],
    'operation six rolls back first page' => [static fn (): mixed => $plan()['operations'][6]['op'], 'rollback_savepoint_before_image'],
    'operation eight release read cache hit' => [static fn (): mixed => $plan()['operations'][8]['op'], 'release_read_cache_hit'],
    'operation ten release read cache miss' => [static fn (): mixed => $plan()['operations'][10]['op'], 'release_read_cache_miss'],
    'operation thirteen final read miss' => [static fn (): mixed => $plan()['operations'][13]['page_number'], 8],
    'dependency includes next100 marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-cache-current-source-next100', $plan()['dependencies'], true), true],
    'dependency includes hot journal recovery' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $plan()['dependencies'], true), true],
    'dependency includes savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'dependency includes source token cache' => [static fn (): mixed => in_array('sqlite-pager-cache-current-source-token', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint cache current source next100 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager hot journal savepoint cache current source next100 rejects bad page size'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(0, 's', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects empty savepoint'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, '', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects empty current source'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', '', 'b', [], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects unchanged source'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'a', [], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects zero epoch'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1], 0));
};

$tests['pager hot journal savepoint cache current source next100 rejects empty hot pages'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects empty writes'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects empty reads'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], []));
};

$tests['pager hot journal savepoint cache current source next100 rejects bad cache page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [0 => ['image' => $page('c')]], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects short cache image'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [1 => ['image' => 'short']], [1 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects bad hot page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [0 => $page('a')], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects short hot page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => 'short'], [1 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects bad write page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [0 => $page('b')], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects short write page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [1 => 'short'], [1]));
};

$tests['pager hot journal savepoint cache current source next100 rejects bad read page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(64, 's', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [0]));
};

return $tests;

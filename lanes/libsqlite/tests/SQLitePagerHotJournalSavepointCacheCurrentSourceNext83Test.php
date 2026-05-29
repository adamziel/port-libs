<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = static fn (): array => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(
    $pageSize,
    'plugin_import',
    [
        1 => ['image' => $page('page1 schema cache'), 'source' => 'database', 'epoch' => 7],
        2 => ['image' => $page('page2 stale cache before hot'), 'source' => 'wal', 'epoch' => 7],
        3 => ['image' => $page('page3 stale old epoch'), 'source' => 'database', 'epoch' => 6],
        5 => ['image' => $page('page5 autoload cache'), 'source' => 'database', 'epoch' => 7],
    ],
    [
        2 => $page('page2 recovered hot journal'),
        4 => $page('page4 recovered hot journal'),
    ],
    [
        2 => $page('page2 current dirty plugin'),
        4 => $page('page4 current dirty index'),
    ],
    [
        2 => $page('page2 next retry plugin'),
        3 => $page('page3 next append plugin'),
        6 => $page('page6 next append option'),
    ],
    7,
);

$cases = [
    'status names current source boundary' => [static fn (): mixed => $plan()['status'], 'hot_journal_savepoint_cache_current_source_next'],
    'page size preserved' => [static fn (): mixed => $plan()['page_size'], 64],
    'savepoint preserved at top level' => [static fn (): mixed => $plan()['savepoint']['name'], 'plugin_import'],
    'current epoch preserved' => [static fn (): mixed => $plan()['current_source_epoch'], 7],
    'next epoch increments after hot recovery' => [static fn (): mixed => $plan()['next_source_epoch'], 8],
    'recovered page numbers sorted' => [static fn (): mixed => $plan()['cache']['recovered_page_numbers'], [2, 4]],
    'invalidated hot page and stale epoch' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3]],
    'first invalidated reason is hot journal' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'hot_journal_recovered_page'],
    'second invalidated reason is stale epoch' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'stale_current_source_epoch'],
    'first invalidated source preserved for diagnostics' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['source'], 'wal'],
    'second invalidated epoch preserved for diagnostics' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['epoch'], 6],
    'preserved page numbers skip recovered pages' => [static fn (): mixed => $plan()['cache']['preserved_page_numbers'], [1, 5]],
    'preserved first source' => [static fn (): mixed => $plan()['cache']['preserved_entries'][0]['source'], 'database'],
    'preserved second page number' => [static fn (): mixed => $plan()['cache']['preserved_entries'][1]['page_number'], 5],
    'final page numbers include retry append' => [static fn (): mixed => $plan()['cache']['final_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'final page one source remains database' => [static fn (): mixed => $plan()['cache']['final_sources'][1], 'database'],
    'final page two source is next write' => [static fn (): mixed => $plan()['cache']['final_sources'][2], 'savepoint-next-write'],
    'final page three source is next write' => [static fn (): mixed => $plan()['cache']['final_sources'][3], 'savepoint-next-write'],
    'final page four source restored before image' => [static fn (): mixed => $plan()['cache']['final_sources'][4], 'savepoint-rollback-before-image'],
    'final page five source remains database' => [static fn (): mixed => $plan()['cache']['final_sources'][5], 'database'],
    'final page six source is next write' => [static fn (): mixed => $plan()['cache']['final_sources'][6], 'savepoint-next-write'],
    'savepoint captured recovered page numbers' => [static fn (): mixed => $plan()['savepoint']['captured_page_numbers'], [2, 4]],
    'savepoint captured page two from hot journal' => [static fn (): mixed => $plan()['savepoint']['captured_sources'][2], 'hot-journal'],
    'savepoint captured page four from hot journal' => [static fn (): mixed => $plan()['savepoint']['captured_sources'][4], 'hot-journal'],
    'savepoint rollback restores current pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 4]],
    'next written page numbers preserve retry order' => [static fn (): mixed => $plan()['next']['written_page_numbers'], [2, 3, 6]],
    'next first capture is page two' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['page_number'], 2],
    'next first capture source is restored before image' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['source'], 'savepoint-rollback-before-image'],
    'next first capture matches savepoint before image' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['matches_savepoint_before_image'], true],
    'next first capture is not zero fill' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['zero_filled_short_read'], false],
    'next second capture is stale cache miss zero fill' => [static fn (): mixed => $plan()['next']['captured_pages'][1]['zero_filled_short_read'], true],
    'next second capture source is zero fill' => [static fn (): mixed => $plan()['next']['captured_pages'][1]['source'], 'zero-fill'],
    'next second capture does not match savepoint image' => [static fn (): mixed => $plan()['next']['captured_pages'][1]['matches_savepoint_before_image'], false],
    'next third capture is appended zero fill' => [static fn (): mixed => $plan()['next']['captured_pages'][2]['zero_filled_short_read'], true],
    'next third capture epoch uses next source' => [static fn (): mixed => $plan()['next']['captured_pages'][2]['epoch'], 8],
    'operation count covers captures writes restore and retry' => [static fn (): mixed => count($plan()['operations']), 12],
    'operation 0 captures recovered before image' => [static fn (): mixed => $plan()['operations'][0]['op'], 'capture_savepoint_before_image'],
    'operation 0 uses hot journal source' => [static fn (): mixed => $plan()['operations'][0]['source'], 'hot-journal'],
    'operation 1 writes current page' => [static fn (): mixed => $plan()['operations'][1]['op'], 'write_current_savepoint_page'],
    'operation 2 captures second recovered before image' => [static fn (): mixed => $plan()['operations'][2]['page_number'], 4],
    'operation 3 writes second current page' => [static fn (): mixed => $plan()['operations'][3]['source'], 'savepoint-current-write'],
    'operation 4 restores first before image' => [static fn (): mixed => $plan()['operations'][4]['op'], 'restore_savepoint_before_image'],
    'operation 5 restores second before image' => [static fn (): mixed => $plan()['operations'][5]['page_number'], 4],
    'operation 6 captures retry before image' => [static fn (): mixed => $plan()['operations'][6]['op'], 'capture_next_retry_before_image'],
    'operation 7 writes retry page' => [static fn (): mixed => $plan()['operations'][7]['op'], 'write_next_retry_page'],
    'operation 8 captures stale-miss page' => [static fn (): mixed => $plan()['operations'][8]['source'], 'zero-fill'],
    'operation 10 captures appended page' => [static fn (): mixed => $plan()['operations'][10]['page_number'], 6],
    'dependency includes slice marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-cache-current-source-next83', $plan()['dependencies'], true), true],
    'dependency includes hot journal recovery' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery', $plan()['dependencies'], true), true],
    'dependency includes savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'dependency includes pager cache current source' => [static fn (): mixed => in_array('sqlite-pager-cache-current-source', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint cache current source next83 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager hot journal savepoint cache current source next83 rejects bad page size'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(0, 's', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects empty savepoint'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, '', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects empty hot journal pages'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects empty current writes'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects empty next writes'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [1 => $page('b')], []));
};

$tests['pager hot journal savepoint cache current source next83 rejects zero source epoch'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], 0));
};

$tests['pager hot journal savepoint cache current source next83 rejects zero cache page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [0 => ['image' => $page('cache')]], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects short cache image'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [1 => ['image' => 'short']], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects zero hot journal page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [0 => $page('a')], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects short hot journal image'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => 'short'], [1 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects zero current page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [0 => $page('b')], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects short current image'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [1 => 'short'], [1 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects zero next page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [1 => $page('b')], [0 => $page('c')]));
};

$tests['pager hot journal savepoint cache current source next83 rejects short next image'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(64, 's', [], [1 => $page('a')], [1 => $page('b')], [1 => 'short']));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan;

$tests = [];

$pageSize = 88;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = static fn (): array => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(
    $pageSize,
    'wp_outer_import',
    'plugin_batch',
    'retry_option_write',
    'journal-before-hot:29',
    'hot-recovered:30',
    [
        1 => ['image' => $page('page1 schema clean'), 'source' => 'database', 'source_id' => 'journal-before-hot:29', 'epoch' => 29, 'pin' => 'wp_outer_import'],
        2 => ['image' => $page('page2 stale active plugins'), 'source' => 'page-cache', 'source_id' => 'journal-before-hot:29', 'epoch' => 29],
        3 => ['image' => $page('page3 dirty plugin option'), 'source' => 'inner-savepoint-write', 'source_id' => 'journal-before-hot:29', 'epoch' => 29, 'dirty' => true, 'pin' => 'plugin_batch'],
        4 => ['image' => $page('page4 stale source'), 'source' => 'database', 'source_id' => 'journal-before-hot:28', 'epoch' => 29],
        5 => ['image' => $page('page5 stale epoch'), 'source' => 'database', 'source_id' => 'journal-before-hot:29', 'epoch' => 28],
        6 => ['image' => $page('page6 autoload clean'), 'source' => 'database', 'source_id' => 'journal-before-hot:29', 'epoch' => 29],
    ],
    [
        2 => $page('page2 hot recovered active plugins'),
        7 => $page('page7 hot recovered index'),
    ],
    [
        1 => $page('page1 outer schema before'),
        6 => $page('page6 outer autoload before'),
    ],
    [
        2 => $page('page2 inner active plugins before'),
        7 => $page('page7 inner index before'),
    ],
    [
        2 => $page('page2 retry active plugins'),
        6 => $page('page6 retry autoload list'),
        8 => $page('page8 retry overflow'),
    ],
    [1, 2, 3, 6, 7, 8],
    29,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_cache_savepoint_current_source_next131'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovery_retags_page_cache_before_savepoint_cursor_and_next_statement'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 88],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'journal-before-hot:29'],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 29],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], 'hot-recovered:30'],
    'recovered source epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 30],
    'outer savepoint name' => [static fn (): mixed => $plan()['savepoints']['outer']['name'], 'wp_outer_import'],
    'outer remains active' => [static fn (): mixed => $plan()['savepoints']['outer']['active_after_rollback_to_inner'], true],
    'outer page numbers' => [static fn (): mixed => $plan()['savepoints']['outer']['page_numbers'], [1, 6]],
    'inner savepoint name' => [static fn (): mixed => $plan()['savepoints']['inner']['name'], 'plugin_batch'],
    'inner remains active' => [static fn (): mixed => $plan()['savepoints']['inner']['active_after_rollback_to_inner'], true],
    'inner page numbers' => [static fn (): mixed => $plan()['savepoints']['inner']['page_numbers'], [2, 7]],
    'hot journal pages sorted' => [static fn (): mixed => $plan()['cache']['hot_journal_page_numbers'], [2, 7]],
    'invalidated pages' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5]],
    'invalidated hot reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'hot_journal_replaces_cached_page'],
    'invalidated hot source' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['source'], 'page-cache'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'dirty_inner_savepoint_cache_discarded'],
    'invalidated dirty pin' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['pin'], 'plugin_batch'],
    'invalidated stale source reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'stale_source_token_discarded'],
    'invalidated stale source id' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['source_id'], 'journal-before-hot:28'],
    'invalidated stale epoch reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'stale_source_epoch_discarded'],
    'invalidated stale epoch value' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['epoch'], 28],
    'retagged pages' => [static fn (): mixed => $plan()['cache']['retagged_page_numbers'], [1, 6]],
    'retagged first pin' => [static fn (): mixed => $plan()['cache']['retagged_entries'][0]['pin'], 'wp_outer_import'],
    'retagged first old source' => [static fn (): mixed => $plan()['cache']['retagged_entries'][0]['old_source_id'], 'journal-before-hot:29'],
    'retagged first new source' => [static fn (): mixed => $plan()['cache']['retagged_entries'][0]['new_source_id'], 'hot-recovered:30'],
    'final page numbers' => [static fn (): mixed => $plan()['cache']['final_page_numbers'], [1, 2, 6, 7, 8]],
    'final page one source' => [static fn (): mixed => $plan()['cache']['final_sources'][1], 'outer-savepoint-before-image'],
    'final page two source' => [static fn (): mixed => $plan()['cache']['final_sources'][2], 'next-statement-write-after-savepoint-rollback'],
    'final page six source' => [static fn (): mixed => $plan()['cache']['final_sources'][6], 'next-statement-write-after-savepoint-rollback'],
    'final page seven source' => [static fn (): mixed => $plan()['cache']['final_sources'][7], 'inner-savepoint-before-image'],
    'final page eight source' => [static fn (): mixed => $plan()['cache']['final_sources'][8], 'next-statement-write-after-savepoint-rollback'],
    'final page one source id' => [static fn (): mixed => $plan()['cache']['final_source_ids'][1], 'hot-recovered:30'],
    'final page seven source id' => [static fn (): mixed => $plan()['cache']['final_source_ids'][7], 'hot-recovered:30'],
    'dirty pages after retry' => [static fn (): mixed => $plan()['cache']['dirty_page_numbers'], [2, 6, 8]],
    'cursor read count' => [static fn (): mixed => count($plan()['cursor_reads']), 6],
    'cursor page one hit' => [static fn (): mixed => $plan()['cursor_reads'][0]['cache_hit'], true],
    'cursor page one source' => [static fn (): mixed => $plan()['cursor_reads'][0]['source'], 'outer-savepoint-before-image'],
    'cursor page one pin' => [static fn (): mixed => $plan()['cursor_reads'][0]['pin'], 'wp_outer_import'],
    'cursor page one matches outer' => [static fn (): mixed => $plan()['cursor_reads'][0]['matches_outer_before_image'], true],
    'cursor page two matches inner' => [static fn (): mixed => $plan()['cursor_reads'][1]['matches_inner_before_image'], true],
    'cursor page three zero fill' => [static fn (): mixed => $plan()['cursor_reads'][2]['source'], 'zero-fill-current-source'],
    'cursor page three hit false' => [static fn (): mixed => $plan()['cursor_reads'][2]['cache_hit'], false],
    'cursor page six outer source' => [static fn (): mixed => $plan()['cursor_reads'][3]['source'], 'outer-savepoint-before-image'],
    'cursor page seven inner source' => [static fn (): mixed => $plan()['cursor_reads'][4]['source'], 'inner-savepoint-before-image'],
    'cursor page eight zero fill' => [static fn (): mixed => $plan()['cursor_reads'][5]['source'], 'zero-fill-current-source'],
    'next statement name' => [static fn (): mixed => $plan()['next_statement']['name'], 'retry_option_write'],
    'next statement before pages' => [static fn (): mixed => $plan()['next_statement']['before_page_numbers'], [2, 6, 8]],
    'next statement write pages' => [static fn (): mixed => $plan()['next_statement']['write_page_numbers'], [2, 6, 8]],
    'outer before page one prefix' => [static fn (): mixed => $plan()['outer_before_prefixes'][1], 'page1 outer schema before'],
    'outer before page six prefix' => [static fn (): mixed => $plan()['outer_before_prefixes'][6], 'page6 outer autoload before'],
    'inner before page two prefix' => [static fn (): mixed => $plan()['inner_before_prefixes'][2], 'page2 inner active plugins before'],
    'inner before page seven prefix' => [static fn (): mixed => $plan()['inner_before_prefixes'][7], 'page7 inner index before'],
    'next before page two prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][2], 'page2 inner active plugins before'],
    'next before page six prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][6], 'page6 outer autoload before'],
    'next before page eight prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][8], ''],
    'final page two prefix' => [static fn (): mixed => $plan()['final_prefixes'][2], 'page2 retry active plugins'],
    'final page six prefix' => [static fn (): mixed => $plan()['final_prefixes'][6], 'page6 retry autoload list'],
    'final page seven prefix' => [static fn (): mixed => $plan()['final_prefixes'][7], 'page7 inner index before'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 20],
    'operation zero retags page one' => [static fn (): mixed => $plan()['operations'][0]['op'], 'retag_clean_cache_page'],
    'operation one retags page six' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 6],
    'operation two installs hot page two' => [static fn (): mixed => $plan()['operations'][2]['op'], 'install_hot_journal_page'],
    'operation three installs hot page seven' => [static fn (): mixed => $plan()['operations'][3]['page_number'], 7],
    'operation four restores outer' => [static fn (): mixed => $plan()['operations'][4]['op'], 'restore_outer_savepoint_before_image'],
    'operation six rolls back inner' => [static fn (): mixed => $plan()['operations'][6]['op'], 'rollback_to_inner_savepoint_before_image'],
    'operation eight cursor reads page one' => [static fn (): mixed => $plan()['operations'][8]['op'], 'cursor_read_recovered_current_source'],
    'operation ten cursor zero fills page three' => [static fn (): mixed => $plan()['operations'][10]['op'], 'cursor_read_zero_fill_current_source'],
    'operation fourteen captures page two' => [static fn (): mixed => $plan()['operations'][14]['op'], 'capture_next_statement_before_image'],
    'operation fifteen writes page two' => [static fn (): mixed => $plan()['operations'][15]['op'], 'write_next_statement_page'],
    'operation nineteen writes page eight' => [static fn (): mixed => $plan()['operations'][19]['page_number'], 8],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-cache-savepoint-current-source-next131', $plan()['dependencies'], true), true],
    'dependency source retag' => [static fn (): mixed => in_array('sqlite-hot-journal-cache-source-retag', $plan()['dependencies'], true), true],
    'dependency cursor refresh' => [static fn (): mixed => in_array('sqlite-savepoint-cursor-current-source-refresh', $plan()['dependencies'], true), true],
    'dependency next statement capture' => [static fn (): mixed => in_array('sqlite-next-statement-captures-after-savepoint-source-refresh', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal cache savepoint current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager hot journal cache savepoint current source next131 rejects bad page size'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(0, 'outer', 'inner', 'stmt', 'a', 'b', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects empty name'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, '', 'inner', 'stmt', 'a', 'b', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects duplicate savepoint'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 's', 's', 'stmt', 'a', 'b', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects unchanged source'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'a', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects zero epoch'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'b', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1], 0));
};

$tests['pager hot journal cache savepoint current source next131 rejects empty pages'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'b', [], [], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects bad cache page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'b', [0 => ['image' => $page('cache')]], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects short hot page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'b', [], [1 => 'short'], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [1]));
};

$tests['pager hot journal cache savepoint current source next131 rejects bad cursor page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(88, 'outer', 'inner', 'stmt', 'a', 'b', [], [1 => $page('hot')], [1 => $page('outer')], [1 => $page('inner')], [1 => $page('next')], [0]));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 80;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = static fn (): array => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(
    $pageSize,
    'wp_plugin_import',
    'retry-active-plugins',
    'rollback-journal:salt-41',
    'hot-recovered:salt-42',
    [
        1 => ['image' => $page('page1 schema stable'), 'source' => 'database', 'epoch' => 41, 'source_id' => 'rollback-journal:salt-41'],
        2 => ['image' => $page('page2 stale autoload cache'), 'source' => 'cache', 'epoch' => 41, 'source_id' => 'rollback-journal:salt-41'],
        3 => ['image' => $page('page3 dirty option write'), 'source' => 'savepoint-write', 'epoch' => 41, 'source_id' => 'rollback-journal:salt-41', 'dirty' => true],
        4 => ['image' => $page('page4 stale epoch'), 'source' => 'database', 'epoch' => 40, 'source_id' => 'rollback-journal:salt-41'],
        5 => ['image' => $page('page5 stale source'), 'source' => 'database', 'epoch' => 41, 'source_id' => 'rollback-journal:salt-40'],
        6 => ['image' => $page('page6 options stable'), 'source' => 'database', 'epoch' => 41, 'source_id' => 'rollback-journal:salt-41'],
    ],
    [
        2 => $page('page2 hot autoload recovered'),
        7 => $page('page7 hot index recovered'),
    ],
    [
        2 => $page('page2 savepoint dirty autoload'),
        7 => $page('page7 savepoint dirty index'),
    ],
    [
        2 => $page('page2 retry active plugins'),
        6 => $page('page6 retry option list'),
        8 => $page('page8 retry overflow zero'),
    ],
    [1, 2, 3, 6, 7, 8, 9],
    41,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_savepoint_hot_journal_cache_current_source_next128'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'retry_statement_uses_hot_journal_recovered_cache_after_rollback_to_savepoint'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 80],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'wp_plugin_import'],
    'savepoint remains active' => [static fn (): mixed => $plan()['savepoint']['still_active_after_rollback_to'], true],
    'statement name' => [static fn (): mixed => $plan()['statement']['name'], 'retry-active-plugins'],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'rollback-journal:salt-41'],
    'next source id' => [static fn (): mixed => $plan()['next_source']['id'], 'hot-recovered:salt-42'],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 41],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 42],
    'hot journal pages sorted' => [static fn (): mixed => $plan()['cache']['hot_journal_page_numbers'], [2, 7]],
    'invalidated pages' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5]],
    'invalidated hot reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'hot_journal_current_source_replaces_cached_page'],
    'invalidated hot source' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['source'], 'cache'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'dirty_savepoint_cache_discarded_before_retry_statement'],
    'invalidated dirty flag' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['dirty'], true],
    'invalidated epoch reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'stale_epoch_cache_discarded_before_retry_statement'],
    'invalidated epoch value' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['epoch'], 40],
    'invalidated source reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'stale_source_cache_discarded_before_retry_statement'],
    'invalidated source id' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['source_id'], 'rollback-journal:salt-40'],
    'preserved pages' => [static fn (): mixed => $plan()['cache']['preserved_page_numbers'], [1, 6]],
    'preserved first source' => [static fn (): mixed => $plan()['cache']['preserved_entries'][0]['source'], 'database'],
    'preserved second page' => [static fn (): mixed => $plan()['cache']['preserved_entries'][1]['page_number'], 6],
    'savepoint before pages' => [static fn (): mixed => $plan()['savepoint']['before_page_numbers'], [2, 7]],
    'savepoint restored pages' => [static fn (): mixed => $plan()['savepoint']['rollback_restored_page_numbers'], [2, 7]],
    'statement before pages' => [static fn (): mixed => $plan()['statement']['before_page_numbers'], [2, 6, 8]],
    'statement write pages' => [static fn (): mixed => $plan()['statement']['write_page_numbers'], [2, 6, 8]],
    'savepoint before recovered page prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][2], 'page2 hot autoload recovered'],
    'savepoint before index prefix' => [static fn (): mixed => $plan()['savepoint_before_prefixes'][7], 'page7 hot index recovered'],
    'next before page two sees rollback restored prefix' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][2], 'page2 hot autoload recovered'],
    'next before page six sees preserved database prefix' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][6], 'page6 options stable'],
    'next before page eight zero fill prefix' => [static fn (): mixed => $plan()['next_statement_before_prefixes'][8], ''],
    'final pages' => [static fn (): mixed => $plan()['cache']['final_page_numbers'], [1, 2, 6, 7, 8]],
    'final page one source' => [static fn (): mixed => $plan()['cache']['final_sources'][1], 'database'],
    'final page two source' => [static fn (): mixed => $plan()['cache']['final_sources'][2], 'next-statement-write-after-rollback-to'],
    'final page six source' => [static fn (): mixed => $plan()['cache']['final_sources'][6], 'next-statement-write-after-rollback-to'],
    'final page seven source' => [static fn (): mixed => $plan()['cache']['final_sources'][7], 'rollback-to-savepoint-before-image'],
    'final page eight source' => [static fn (): mixed => $plan()['cache']['final_sources'][8], 'next-statement-write-after-rollback-to'],
    'final page one source id' => [static fn (): mixed => $plan()['cache']['final_source_ids'][1], 'hot-recovered:salt-42'],
    'final page two source id' => [static fn (): mixed => $plan()['cache']['final_source_ids'][2], 'hot-recovered:salt-42'],
    'dirty pages after retry statement' => [static fn (): mixed => $plan()['cache']['dirty_page_numbers'], [2, 6, 8]],
    'final page two prefix' => [static fn (): mixed => $plan()['final_prefixes'][2], 'page2 retry active plugins'],
    'final page six prefix' => [static fn (): mixed => $plan()['final_prefixes'][6], 'page6 retry option list'],
    'final page seven prefix' => [static fn (): mixed => $plan()['final_prefixes'][7], 'page7 hot index recovered'],
    'final page eight prefix' => [static fn (): mixed => $plan()['final_prefixes'][8], 'page8 retry overflow zero'],
    'read count' => [static fn (): mixed => count($plan()['reads']), 7],
    'read page one hit' => [static fn (): mixed => $plan()['reads'][0]['cache_hit'], true],
    'read page one source' => [static fn (): mixed => $plan()['reads'][0]['source'], 'database'],
    'read page two dirty hit' => [static fn (): mixed => $plan()['reads'][1]['dirty'], true],
    'read page two no longer matches before image' => [static fn (): mixed => $plan()['reads'][1]['matches_next_statement_before_image'], false],
    'read page three miss' => [static fn (): mixed => $plan()['reads'][2]['cache_hit'], false],
    'read page six dirty hit' => [static fn (): mixed => $plan()['reads'][3]['dirty'], true],
    'read page seven matches savepoint before image' => [static fn (): mixed => $plan()['reads'][4]['matches_savepoint_before_image'], true],
    'read page eight dirty hit' => [static fn (): mixed => $plan()['reads'][5]['dirty'], true],
    'read page nine miss' => [static fn (): mixed => $plan()['reads'][6]['cache_hit'], false],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 21],
    'operation zero installs hot page' => [static fn (): mixed => $plan()['operations'][0]['op'], 'install_hot_journal_current_source_page'],
    'operation one installs second hot page' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 7],
    'operation two captures savepoint before image' => [static fn (): mixed => $plan()['operations'][2]['op'], 'capture_savepoint_before_image'],
    'operation four captures second savepoint before image' => [static fn (): mixed => $plan()['operations'][4]['page_number'], 7],
    'operation six rollback to savepoint' => [static fn (): mixed => $plan()['operations'][6]['op'], 'rollback_to_savepoint_before_image'],
    'operation eight next statement capture' => [static fn (): mixed => $plan()['operations'][8]['op'], 'capture_next_statement_before_image'],
    'operation nine next statement write' => [static fn (): mixed => $plan()['operations'][9]['op'], 'write_next_statement_page'],
    'operation twelve next statement writes zero page' => [static fn (): mixed => $plan()['operations'][13]['page_number'], 8],
    'operation fourteen read page one' => [static fn (): mixed => $plan()['operations'][14]['page_number'], 1],
    'operation twenty final miss' => [static fn (): mixed => $plan()['operations'][20]['op'], 'read_current_source_cache_miss'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-hot-journal-cache-current-source-next128', $plan()['dependencies'], true), true],
    'dependency hot journal current source cache' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery-current-source-cache', $plan()['dependencies'], true), true],
    'dependency rollback to current source' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-keeps-current-source-token', $plan()['dependencies'], true), true],
    'dependency next statement subjournal' => [static fn (): mixed => in_array('sqlite-next-statement-subjournal-captures-recovered-source', $plan()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint hot journal cache current source next128 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager savepoint hot journal cache current source next128 rejects bad page size'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(0, 's', 'stmt', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects empty savepoint'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, '', 'stmt', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects empty statement'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', '', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects empty source'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', '', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects unchanged source'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'a', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects zero epoch'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1], 0));
};

$tests['pager savepoint hot journal cache current source next128 rejects empty hot pages'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'b', [], [], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects bad cache page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'b', [0 => ['image' => $page('cache')]], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects short hot page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'b', [], [1 => 'short'], [1 => $page('b')], [1 => $page('c')], [1]));
};

$tests['pager savepoint hot journal cache current source next128 rejects bad read page'] = static function (TestRunner $t) use ($page): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(80, 's', 'stmt', 'a', 'b', [], [1 => $page('a')], [1 => $page('b')], [1 => $page('c')], [0]));
};

return $tests;

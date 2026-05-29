<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cache = [
    1 => ['image' => $page('next149 cached schema root clean'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:41', 'epoch' => 41],
    2 => ['image' => $page('next149 cached active plugins stale'), 'source' => 'wal-cache', 'source_id' => 'journal-before-hot:41', 'epoch' => 41],
    3 => ['image' => $page('next149 cached plugin option dirty'), 'source' => 'savepoint-write', 'source_id' => 'journal-before-hot:41', 'epoch' => 41, 'dirty' => true, 'savepoint' => 'plugin-settings'],
    4 => ['image' => $page('next149 cached autoload pinned'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:41', 'epoch' => 41, 'pinned' => true],
    5 => ['image' => $page('next149 cached stale source'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:40', 'epoch' => 41],
    6 => ['image' => $page('next149 cached stale epoch'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:41', 'epoch' => 40],
    7 => ['image' => $page('next149 cached preserved comments'), 'source' => 'database-cache', 'source_id' => 'journal-before-hot:41', 'epoch' => 41],
];
$hotPages = [
    2 => $page('next149 hot recovered active plugins'),
    8 => $page('next149 hot recovered overflow tail'),
];
$savepointBefore = [
    3 => $page('next149 savepoint before plugin option'),
    4 => $page('next149 savepoint before autoload index'),
];
$nextWrites = [
    2 => $page('next149 retry active plugins write'),
    3 => $page('next149 retry plugin option write'),
    9 => $page('next149 retry missing overflow write'),
];
$reads = [1, 2, 3, 4, 5, 7, 8, 9];

$plan = static fn (
    ?array $cachePages = null,
    ?array $hot = null,
    ?array $before = null,
    ?array $writes = null,
    ?array $readPages = null,
    int $epoch = 41,
    string $savepoint = 'plugin-settings',
    string $statement = 'retry-option-import',
    string $currentSource = 'journal-before-hot:41',
    string $recoveredSource = 'hot-recovered:42',
): array => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext149(
    $pageSize,
    $savepoint,
    $statement,
    $currentSource,
    $recoveredSource,
    $cachePages ?? $cache,
    $hot ?? $hotPages,
    $before ?? $savepointBefore,
    $writes ?? $nextWrites,
    $readPages ?? $reads,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_savepoint_cache_current_source_next149'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'hot_journal_recovery_and_savepoint_rollback_refresh_page_cache_for_next_statement'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'savepoint name' => [static fn (): mixed => $plan()['savepoint']['name'], 'plugin-settings'],
    'savepoint active' => [static fn (): mixed => $plan()['savepoint']['active_after_rollback'], true],
    'next statement name' => [static fn (): mixed => $plan()['next_statement']['name'], 'retry-option-import'],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], 'journal-before-hot:41'],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 41],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source']['id'], 'hot-recovered:42'],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 42],
    'retained pages' => [static fn (): mixed => $plan()['cache']['retained_page_numbers'], [1, 7]],
    'retained first old source' => [static fn (): mixed => $plan()['cache']['retained_entries'][0]['old_source_id'], 'journal-before-hot:41'],
    'retained first new source' => [static fn (): mixed => $plan()['cache']['retained_entries'][0]['new_source_id'], 'hot-recovered:42'],
    'invalidated pages' => [static fn (): mixed => $plan()['cache']['invalidated_page_numbers'], [2, 3, 4, 5, 6]],
    'invalidated hot reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'], 'hot_journal_recovered_page_replaces_cache'],
    'invalidated dirty reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'], 'rollback_to_savepoint_restores_before_image'],
    'invalidated dirty flag' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['dirty'], true],
    'invalidated pinned reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'], 'rollback_to_savepoint_restores_before_image'],
    'invalidated stale source reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'], 'stale_current_source_id'],
    'invalidated stale epoch reason' => [static fn (): mixed => $plan()['cache']['invalidated_entries'][4]['reason'], 'stale_current_source_epoch'],
    'hot page numbers' => [static fn (): mixed => $plan()['cache']['hot_journal_page_numbers'], [2, 8]],
    'savepoint before page numbers' => [static fn (): mixed => $plan()['cache']['savepoint_before_page_numbers'], [3, 4]],
    'final page numbers' => [static fn (): mixed => $plan()['cache']['final_page_numbers'], [1, 2, 3, 4, 7, 8, 9]],
    'final page one source' => [static fn (): mixed => $plan()['cache']['final_sources'][1], 'database-cache'],
    'final page two source' => [static fn (): mixed => $plan()['cache']['final_sources'][2], 'next-statement-write-after-recovered-savepoint'],
    'final page three source' => [static fn (): mixed => $plan()['cache']['final_sources'][3], 'next-statement-write-after-recovered-savepoint'],
    'final page four source' => [static fn (): mixed => $plan()['cache']['final_sources'][4], 'savepoint-before-image-after-hot-journal'],
    'final page eight source' => [static fn (): mixed => $plan()['cache']['final_sources'][8], 'hot-journal-recovered-page'],
    'final source ids all recovered' => [static fn (): mixed => array_values(array_unique($plan()['cache']['final_source_ids'])), ['hot-recovered:42']],
    'dirty pages after next write' => [static fn (): mixed => $plan()['cache']['dirty_page_numbers'], [2, 3, 9]],
    'read row count' => [static fn (): mixed => count($plan()['read_pages']), 8],
    'read page one hit' => [static fn (): mixed => $plan()['read_pages'][0]['cache_hit'], true],
    'read page one prefix' => [static fn (): mixed => $plan()['read_pages'][0]['prefix'], 'next149 cached schema root clean'],
    'read page two hot' => [static fn (): mixed => $plan()['read_pages'][1]['matches_hot_journal'], true],
    'read page three savepoint' => [static fn (): mixed => $plan()['read_pages'][2]['matches_savepoint_before'], true],
    'read page five miss' => [static fn (): mixed => $plan()['read_pages'][4]['cache_hit'], false],
    'read page five zero fill' => [static fn (): mixed => $plan()['read_pages'][4]['source'], 'zero-fill-recovered-current-source'],
    'read page eight hot' => [static fn (): mixed => $plan()['read_pages'][6]['source'], 'hot-journal-recovered-page'],
    'read page nine miss before write' => [static fn (): mixed => $plan()['read_pages'][7]['cache_hit'], false],
    'next before pages' => [static fn (): mixed => $plan()['next_statement']['before_page_numbers'], [2, 3, 9]],
    'next write pages' => [static fn (): mixed => $plan()['next_statement']['write_page_numbers'], [2, 3, 9]],
    'next before page two prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][2], 'next149 hot recovered active plugins'],
    'next before page three prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][3], 'next149 savepoint before plugin option'],
    'next before page nine prefix' => [static fn (): mixed => $plan()['next_before_prefixes'][9], ''],
    'final page two prefix' => [static fn (): mixed => $plan()['final_prefixes'][2], 'next149 retry active plugins write'],
    'final page four prefix' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next149 savepoint before autoload index'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 25],
    'operation zero retags page one' => [static fn (): mixed => $plan()['operations'][0]['op'], 'retag_clean_cache_page_for_recovered_source'],
    'operation one invalidates hot page' => [static fn (): mixed => $plan()['operations'][1]['reason'], 'hot_journal_recovered_page_replaces_cache'],
    'operation two invalidates savepoint page' => [static fn (): mixed => $plan()['operations'][2]['reason'], 'rollback_to_savepoint_restores_before_image'],
    'operation seven installs hot page two' => [static fn (): mixed => $plan()['operations'][7]['op'], 'install_hot_journal_recovered_page'],
    'operation nine restores savepoint page three' => [static fn (): mixed => $plan()['operations'][9]['op'], 'restore_savepoint_before_image'],
    'operation eleven reads page one' => [static fn (): mixed => $plan()['operations'][11]['op'], 'read_recovered_current_source_cache_page'],
    'operation fifteen reads miss' => [static fn (): mixed => $plan()['operations'][15]['op'], 'read_zero_fill_after_cache_invalidation'],
    'operation nineteen captures page two' => [static fn (): mixed => $plan()['operations'][19]['op'], 'capture_next_statement_before_image'],
    'operation twenty writes page two' => [static fn (): mixed => $plan()['operations'][20]['op'], 'write_next_statement_page'],
    'operation twenty three writes page nine' => [static fn (): mixed => $plan()['operations'][23]['page_number'], 9],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-savepoint-cache-current-source-next149', $plan()['dependencies'], true), true],
    'dependency cache refresh' => [static fn (): mixed => in_array('sqlite-hot-journal-cache-current-source-refresh', $plan()['dependencies'], true), true],
    'dependency savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'dependency next statement capture' => [static fn (): mixed => in_array('sqlite-next-statement-before-image-capture', $plan()['dependencies'], true), true],
    'pinned without savepoint is rechecked' => [static fn (): mixed => $plan([10 => ['image' => $page('next149 pinned no savepoint'), 'source_id' => 'journal-before-hot:41', 'epoch' => 41, 'pinned' => true]], [2 => $hotPages[2]], [3 => $savepointBefore[3]], [2 => $nextWrites[2]], [10])['cache']['invalidated_entries'][0]['reason'], 'pinned_cache_page_rechecked_after_recovery'],
    'clean new page can be retained' => [static fn (): mixed => $plan([10 => ['image' => $page('next149 clean extra retained'), 'source_id' => 'journal-before-hot:41', 'epoch' => 41]], [2 => $hotPages[2]], [3 => $savepointBefore[3]], [2 => $nextWrites[2]], [10])['cache']['retained_page_numbers'], [10]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal savepoint cache current source next149 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects bad page size' => static fn () => SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext149(0, 's', 'n', 'a', 'b', $cache, $hotPages, $savepointBefore, $nextWrites, $reads),
    'rejects empty savepoint' => static fn () => $plan(null, null, null, null, null, 41, ''),
    'rejects empty statement' => static fn () => $plan(null, null, null, null, null, 41, 's', ''),
    'rejects empty current source' => static fn () => $plan(null, null, null, null, null, 41, 's', 'n', ''),
    'rejects empty recovered source' => static fn () => $plan(null, null, null, null, null, 41, 's', 'n', 'a', ''),
    'rejects unchanged source' => static fn () => $plan(null, null, null, null, null, 41, 's', 'n', 'same', 'same'),
    'rejects zero epoch' => static fn () => $plan(null, null, null, null, null, 0),
    'rejects empty cache' => static fn () => $plan([]),
    'rejects empty hot pages' => static fn () => $plan(null, []),
    'rejects empty savepoint pages' => static fn () => $plan(null, null, []),
    'rejects empty writes' => static fn () => $plan(null, null, null, []),
    'rejects empty reads' => static fn () => $plan(null, null, null, null, []),
    'rejects bad cache page' => static fn () => $plan([0 => ['image' => $page('bad')]]),
    'rejects short cache image' => static fn () => $plan([1 => ['image' => 'short']]),
    'rejects bad hot page' => static fn () => $plan(null, [0 => $hotPages[2]]),
    'rejects short hot page' => static fn () => $plan(null, [2 => 'short']),
    'rejects bad savepoint page' => static fn () => $plan(null, null, [0 => $savepointBefore[3]]),
    'rejects short savepoint page' => static fn () => $plan(null, null, [3 => 'short']),
    'rejects bad write page' => static fn () => $plan(null, null, null, [0 => $nextWrites[2]]),
    'rejects short write page' => static fn () => $plan(null, null, null, [2 => 'short']),
    'rejects bad read page' => static fn () => $plan(null, null, null, null, [0]),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal savepoint cache current source next149 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;

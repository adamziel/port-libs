<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan::currentSourceNext(
    $pageSize,
    'wp_import',
    'plugin_settings',
    'retry_autoload_update',
    'pre-hot-journal',
    'post-hot-journal',
    [
        1 => ['image' => $page('wp_options schema cache'), 'source' => 'database', 'source_id' => 'pre-hot-journal', 'epoch' => 12, 'pin' => 'wp_import'],
        2 => ['image' => $page('stale active plugins cache'), 'source' => 'page-cache', 'source_id' => 'pre-hot-journal', 'epoch' => 12],
        3 => ['image' => $page('dirty failed plugin write'), 'source' => 'plugin_settings', 'source_id' => 'pre-hot-journal', 'epoch' => 12, 'dirty' => true, 'pin' => 'plugin_settings'],
        5 => ['image' => $page('autoload index clean'), 'source' => 'database', 'source_id' => 'pre-hot-journal', 'epoch' => 12],
    ],
    [
        2 => $page('hot recovered active plugins'),
        4 => $page('hot recovered option index'),
    ],
    [
        1 => $page('outer schema before retry'),
        5 => $page('outer autoload before retry'),
    ],
    [
        2 => $page('inner active plugins before retry'),
        4 => $page('inner option index before retry'),
    ],
    [
        2 => $page('retry active plugins'),
        5 => $page('retry autoload index'),
    ],
    [1, 2, 3, 4, 5],
    12,
);

$summary = [
    'scenario' => 'wordpress-pager-hot-journal-cache-savepoint-current-source-next131',
    'wordpressUse' => 'After a copied wp_options import crashes with a hot rollback journal, refresh active savepoint page-cache sources before retrying plugin settings writes so stale dirty pages cannot shadow recovered current-source images.',
    'status' => $plan['status'],
    'invalidated' => $plan['cache']['invalidated_page_numbers'],
    'retagged' => $plan['cache']['retagged_page_numbers'],
    'cursor_sources' => array_column($plan['cursor_reads'], 'source', 'page_number'),
    'next_before' => $plan['next_statement']['before_page_numbers'],
    'dirty_after_retry' => $plan['cache']['dirty_page_numbers'],
    'dependencyClosure' => 'no new support component needed; this composes lane-local pager cache source tokens with savepoint before-image and hot-journal recovery models',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'pager_hot_journal_cache_savepoint_current_source_next131');
    assert($summary['invalidated'] === [2, 3]);
    assert($summary['retagged'] === [1, 5]);
    assert($summary['cursor_sources'][2] === 'inner-savepoint-before-image');
    assert($summary['cursor_sources'][3] === 'zero-fill-current-source');
    assert($summary['next_before'] === [2, 5]);
    assert($summary['dirty_after_retry'] === [2, 5]);

    echo "wordpress-pager-hot-journal-cache-savepoint-current-source-next131 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

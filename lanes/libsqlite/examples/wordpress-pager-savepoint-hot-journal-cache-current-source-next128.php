<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan::currentSourceNext(
    $pageSize,
    'wp_plugin_import',
    'retry-active-plugins',
    'journal-before-crash',
    'journal-hot-recovered',
    [
        1 => ['image' => $page('schema table root'), 'source' => 'database', 'epoch' => 7, 'source_id' => 'journal-before-crash'],
        2 => ['image' => $page('stale active plugins cache'), 'source' => 'page-cache', 'epoch' => 7, 'source_id' => 'journal-before-crash'],
        3 => ['image' => $page('dirty failed plugin import'), 'source' => 'savepoint-write', 'epoch' => 7, 'source_id' => 'journal-before-crash', 'dirty' => true],
        5 => ['image' => $page('autoload index stable'), 'source' => 'database', 'epoch' => 7, 'source_id' => 'journal-before-crash'],
    ],
    [
        2 => $page('hot recovered active plugins'),
        4 => $page('hot recovered autoload index'),
    ],
    [
        2 => $page('failed savepoint active plugins'),
        4 => $page('failed savepoint autoload index'),
    ],
    [
        2 => $page('retry active plugins option'),
        5 => $page('retry autoload option list'),
    ],
    [1, 2, 3, 4, 5],
    7,
);

$summary = [
    'status' => $plan['status'],
    'invalidated' => $plan['cache']['invalidated_page_numbers'],
    'savepoint_restored' => $plan['savepoint']['rollback_restored_page_numbers'],
    'retry_before' => $plan['statement']['before_page_numbers'],
    'dirty_after_retry' => $plan['cache']['dirty_page_numbers'],
    'final_sources' => $plan['cache']['final_sources'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'pager_savepoint_hot_journal_cache_current_source_next128');
    assert($summary['invalidated'] === [2, 3]);
    assert($summary['savepoint_restored'] === [2, 4]);
    assert($summary['retry_before'] === [2, 5]);
    assert($summary['dirty_after_retry'] === [2, 5]);
    assert($summary['final_sources'][4] === 'rollback-to-savepoint-before-image');

    echo "wordpress-pager-savepoint-hot-journal-cache-current-source-next128 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

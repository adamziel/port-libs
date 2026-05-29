<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredHotJournalSavepointRetry(
    $pageSize,
    'wp_plugin_import',
    [
        1 => ['image' => $page('wp_options schema cached'), 'source' => 'database', 'epoch' => 83],
        2 => ['image' => $page('active_plugins stale wal'), 'source' => 'wal', 'epoch' => 83],
        3 => ['image' => $page('autoload index stale'), 'source' => 'database', 'epoch' => 82],
    ],
    [
        2 => $page('active_plugins hot restore'),
        4 => $page('plugin settings hot restore'),
    ],
    [
        2 => $page('active_plugins current'),
        4 => $page('plugin settings current'),
    ],
    [
        2 => $page('active_plugins retry'),
        3 => $page('autoload index retry'),
    ],
    83,
);

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint']['name'],
    'invalidatedPageNumbers' => $plan['cache']['invalidated_page_numbers'],
    'recoveredPageNumbers' => $plan['cache']['recovered_page_numbers'],
    'capturedSources' => $plan['savepoint']['captured_sources'],
    'nextCapturedSources' => array_column($plan['next']['captured_pages'], 'source', 'page_number'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'hot_journal_savepoint_cache_current_source_next');
    assert($summary['invalidatedPageNumbers'] === [2, 3]);
    assert($summary['capturedSources'][2] === 'hot-journal');
    assert($summary['nextCapturedSources'][2] === 'savepoint-rollback-before-image');
    assert($summary['nextCapturedSources'][3] === 'zero-fill');
    echo "wordpress-hot-journal-savepoint-cache-current-source-next83 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

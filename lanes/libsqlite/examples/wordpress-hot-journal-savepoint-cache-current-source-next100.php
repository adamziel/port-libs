<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan;

$pageSize = 64;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan::planRecoveredSourceReleaseReads(
    $pageSize,
    'wp_plugin_import',
    'wal-salt-23:journal-9',
    'hot-recovered-24:journal-deleted',
    [
        1 => ['image' => $page('wp_options schema cache'), 'source' => 'database', 'epoch' => 23, 'source_id' => 'wal-salt-23:journal-9'],
        2 => ['image' => $page('active_plugins stale wal'), 'source' => 'wal', 'epoch' => 23, 'source_id' => 'wal-salt-23:journal-9'],
        3 => ['image' => $page('plugin dirty aborted'), 'source' => 'savepoint-current-write', 'epoch' => 23, 'source_id' => 'wal-salt-23:journal-9', 'dirty' => true],
        5 => ['image' => $page('autoload stale source'), 'source' => 'database', 'epoch' => 23, 'source_id' => 'wal-salt-22:journal-8'],
    ],
    [
        2 => $page('active_plugins hot restore'),
        4 => $page('plugin settings hot restore'),
    ],
    [
        2 => $page('active_plugins current'),
        4 => $page('plugin settings current'),
    ],
    [1, 2, 3, 4, 5],
    23,
);

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint']['name'],
    'currentSource' => $plan['current_source'],
    'nextSource' => $plan['next_source'],
    'invalidatedPageNumbers' => $plan['cache']['invalidated_page_numbers'],
    'recoveredPageNumbers' => $plan['cache']['recovered_page_numbers'],
    'preservedPageNumbers' => $plan['cache']['preserved_page_numbers'],
    'releaseReads' => array_map(
        static fn (array $read): array => [
            'page' => $read['page_number'],
            'cacheHit' => $read['cache_hit'],
            'source' => $read['source'],
        ],
        $plan['release_reads']
    ),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'hot_journal_savepoint_cache_current_source_next100');
    assert($summary['invalidatedPageNumbers'] === [2, 3, 5]);
    assert($summary['recoveredPageNumbers'] === [2, 4]);
    assert($summary['releaseReads'][0]['cacheHit'] === true);
    assert($summary['releaseReads'][1]['source'] === 'savepoint-rollback-before-image');
    assert($summary['releaseReads'][2]['cacheHit'] === false);
    echo "wordpress-hot-journal-savepoint-cache-current-source-next100 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

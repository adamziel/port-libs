<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan;

$currentOptions = [
    ['rowid' => 11, 'blog_id' => '1', 'autoload' => 'yes', 'option_name' => 'active_plugins', 'priority' => '10', 'bytes' => 130, 'include' => 1],
    ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_cache', 'priority' => '02', 'bytes' => 35, 'include' => 1],
    ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'rewrite_rules ', 'priority' => '1', 'bytes' => 20, 'include' => 1],
    ['rowid' => 14, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'network_plugins', 'priority' => '2', 'bytes' => 70, 'include' => 1],
];

$nextOptions = [
    ['rowid' => 11, 'blog_id' => '1', 'autoload' => 'yes', 'option_name' => 'active_plugins', 'priority' => '01', 'bytes' => 130, 'include' => 1],
    ['rowid' => 12, 'blog_id' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_cache', 'priority' => '02', 'bytes' => 35, 'include' => 1],
    ['rowid' => 13, 'blog_id' => 1, 'autoload' => 'no', 'option_name' => 'rewrite_rules ', 'priority' => '1', 'bytes' => 20, 'include' => 1],
    ['rowid' => 14, 'blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'network_plugins', 'priority' => '2', 'bytes' => 70, 'include' => 1],
    ['rowid' => 15, 'blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_cache', 'priority' => '2', 'bytes' => 45, 'include' => 1],
];

$plan = SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare(
    $currentOptions,
    $nextOptions,
    ['blog_id', 'autoload', 'priority', 'option_name'],
    'rowid',
    [
        'sortAffinities' => ['NUMERIC', 'TEXT', 'NUMERIC', 'TEXT'],
        'sortCollations' => ['BINARY', 'NOCASE', 'BINARY', 'RTRIM'],
        'valueColumn' => 'bytes',
        'partitionColumns' => ['blog_id'],
        'partitionAffinities' => ['NUMERIC'],
        'orderColumns' => ['autoload', 'priority', 'option_name'],
        'orderAffinities' => ['TEXT', 'NUMERIC', 'TEXT'],
        'orderCollations' => ['NOCASE', 'BINARY', 'RTRIM'],
        'orderNulls' => ['LAST', 'LAST', null],
        'filterColumn' => 'include',
        'frameUnit' => 'GROUPS',
        'exclude' => 'CURRENT ROW',
        'following' => 1,
        'separator' => '|',
    ]
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'sorter-affinity-window-current-source-changed');
    assert($plan['inserted'] === [15]);
    assert($plan['nextOrder'] === [13, 11, 12, 15, 14]);
    assert(array_column($plan['nextWindow'], 'sum') === [130, 35, 45, 35, null]);
    assert(count($plan['peerChanges']) === 3);
    echo "wordpress-vdbe-sorter-affinity-window-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentOrder' => $plan['currentOrder'],
    'nextOrder' => $plan['nextOrder'],
    'inserted' => $plan['inserted'],
    'peerChanges' => array_column($plan['peerChanges'], 'id'),
    'nextWindowSums' => array_column($plan['nextWindow'], 'sum'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

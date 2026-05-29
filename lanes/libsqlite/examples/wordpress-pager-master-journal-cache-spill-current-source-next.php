<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/var/www/html/wp-content/database/wp-next132.sqlite';
$masterPath = '/var/www/html/wp-content/database/wp-next132.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next132 stale header'),
    2 => $page('wp next132 stale options root'),
    3 => $page('wp next132 stale autoload index'),
];

$plan = SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    implode('', $before),
    $pageSize,
    [
        1 => $page('wp next132 recovered header'),
        2 => $page('wp next132 recovered options root'),
        3 => $page('wp next132 recovered autoload index'),
    ],
    [
        2 => [
            'image' => $before[2],
            'before' => $page('wp next132 recovered options root'),
            'journaled' => true,
            'dirty' => true,
        ],
        3 => [
            'image' => $page('wp next132 recovered autoload index'),
            'before' => $page('wp next132 recovered autoload index'),
            'journaled' => true,
            'dirty' => true,
        ],
    ],
    6,
    3,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager-master-journal-cache-spill-current-source-next132') {
        throw new RuntimeException('Unexpected pager master-journal cache-spill status');
    }
    if ($plan['spilled_page_numbers'] !== [2, 3]) {
        throw new RuntimeException('Unexpected spilled page set');
    }
    if (!str_contains($plan['final_database_bytes'], 'wp next132 recovered options root')) {
        throw new RuntimeException('Recovered wp_options root was not preserved');
    }
    echo "wordpress-pager-master-journal-cache-spill-current-source-next132 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'spilled_pages' => $plan['spilled_page_numbers'],
    'refreshed_pages' => $plan['refreshed_cache_page_numbers'],
    'deferred_pages' => $plan['deferred_cache_page_numbers'],
    'lock_after' => $plan['lock_after'],
], JSON_PRETTY_PRINT) . "\n";

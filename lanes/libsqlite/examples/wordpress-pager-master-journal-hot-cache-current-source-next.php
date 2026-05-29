<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next136.sqlite';
$masterPath = '/srv/wp-content/database/wp-next136.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next136 stale schema before hot cache recovery'),
    2 => $page('wp next136 stale wp_options before hot cache recovery'),
    3 => $page('wp next136 stale plugin settings before hot cache recovery'),
];
$recovered = [
    1 => $page('wp next136 recovered schema current source'),
    2 => $page('wp next136 recovered wp_options current source'),
    3 => $page('wp next136 recovered plugin settings current source'),
];

$plan = SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/site-next136.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['image' => $recovered[1], 'source_id' => 'wp-next136-source', 'epoch' => 3, 'source' => 'cache-after-hot-recovery'],
        2 => ['image' => $before[2], 'source_id' => 'wp-next136-source', 'epoch' => 3, 'source' => 'clean-stale-wp-options-cache'],
        3 => ['image' => $before[3], 'source_id' => 'wp-next136-source', 'epoch' => 3, 'dirty' => true, 'source' => 'dirty-plugin-cache-from-crash'],
    ],
    [1, 2, 3],
    [3 => $page('wp next136 plugin setting rewrite after current hot cache')],
    'wp-next136-source',
    3,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-hot-cache-current-source-next136',
    'status' => $plan['status'],
    'cacheStaleRejected' => $plan['cache_stale_rejected'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'Copied WordPress option repair reads and the next plugin-setting write reuse only cache pages rebased to the current master-journal hot-recovery source; dirty crash cache pages are invalidated before before-images are captured.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager cache and master-journal current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-hot-cache-current-source-next136'
    || $summary['cacheStaleRejected'] !== true
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3]
    || $summary['nextWriteBeforePrefix'] !== 'wp next136 recovered plugin settings current source'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-hot-cache-current-source-next136 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-hot-cache-current-source-next136 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-current-source-retry.sqlite';
$masterPath = '/srv/wp-content/database/wp-current-source-retry.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp current-source-retry stale schema before master hot recovery'),
    2 => $page('wp current-source-retry stale options before master hot recovery'),
    3 => $page('wp current-source-retry stale plugin cache before master hot recovery'),
    4 => $page('wp current-source-retry stale transient overflow before master hot recovery'),
];
$recovered = [
    1 => $page('wp current-source-retry recovered schema current source'),
    2 => $page('wp current-source-retry recovered options current source'),
    3 => $page('wp current-source-retry recovered plugin cache current source'),
    4 => $page('wp current-source-retry recovered transient overflow current source'),
];

$plan = SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::planHotSavepointRetryCache(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/old/site.sqlite-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/site-current-source-retry.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    'wp_plugin_import_batch',
    'retry_plugin_cache_update',
    $recovered,
    [
        1 => ['image' => $recovered[1], 'source_id' => 'wp-current-source-retry-source', 'epoch' => 4, 'source' => 'schema-cache-current'],
        2 => ['image' => $before[2], 'source_id' => 'wp-current-source-retry-source', 'epoch' => 4, 'source' => 'clean-stale-options-cache'],
        3 => ['image' => $before[3], 'source_id' => 'wp-current-source-retry-source', 'epoch' => 4, 'dirty' => true, 'source' => 'dirty-plugin-cache'],
    ],
    [2 => $page('wp current-source-retry failed options savepoint write')],
    [
        2 => $page('wp current-source-retry retry options after rollback'),
        4 => $page('wp current-source-retry retry transient overflow after rollback'),
    ],
    [1, 2, 3, 4],
    'wp-current-source-retry-source',
    4,
    true,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-savepoint-cache-current-source-retry',
    'status' => $plan['status'],
    'cacheStaleRejected' => $plan['cache_stale_rejected'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'savepointBeforePrefix' => $plan['savepoint_before_prefixes'][2],
    'retryBeforePrefix' => $plan['retry_statement_before_prefixes'][2],
    'dirtyPages' => $plan['dirty_page_numbers'],
    'releaseMergedPages' => $plan['savepoint']['release_merged_page_numbers'],
    'wordpressUse' => 'A copied WordPress SQLite options import recovers through the current master journal, rebases stale pager-cache pages, rolls back a failed savepoint write, and captures retry before-images from the recovered current source instead of crashed cache bytes.',
    'dependencyClosure' => 'no new support component needed; this composes lane-local master-journal hot-cache rebasing with native savepoint before-image and retry-statement current-source modeling',
];

if ($summary['status'] !== 'pager-master-journal-savepoint-cache-current-source-retry'
    || $summary['cacheStaleRejected'] !== true
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3]
    || $summary['savepointBeforePrefix'] !== 'wp current-source-retry recovered options current source'
    || $summary['retryBeforePrefix'] !== 'wp current-source-retry recovered options current source'
    || $summary['dirtyPages'] !== [2, 4]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-savepoint-cache-current-source-retry self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-savepoint-cache-current-source-retry self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

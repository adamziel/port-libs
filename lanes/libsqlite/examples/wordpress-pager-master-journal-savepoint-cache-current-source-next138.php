<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next138.sqlite';
$masterPath = '/srv/wp-content/database/wp-next138.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next138 stale schema before master hot recovery'),
    2 => $page('wp next138 stale options before master hot recovery'),
    3 => $page('wp next138 stale plugin cache before master hot recovery'),
    4 => $page('wp next138 stale transient overflow before master hot recovery'),
];
$recovered = [
    1 => $page('wp next138 recovered schema current source'),
    2 => $page('wp next138 recovered options current source'),
    3 => $page('wp next138 recovered plugin cache current source'),
    4 => $page('wp next138 recovered transient overflow current source'),
];

$plan = SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::plan138(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/old/site.sqlite-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/site-next138.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    'wp_plugin_import_batch',
    'retry_plugin_cache_update',
    $recovered,
    [
        1 => ['image' => $recovered[1], 'source_id' => 'wp-next138-source', 'epoch' => 4, 'source' => 'schema-cache-current'],
        2 => ['image' => $before[2], 'source_id' => 'wp-next138-source', 'epoch' => 4, 'source' => 'clean-stale-options-cache'],
        3 => ['image' => $before[3], 'source_id' => 'wp-next138-source', 'epoch' => 4, 'dirty' => true, 'source' => 'dirty-plugin-cache'],
    ],
    [2 => $page('wp next138 failed options savepoint write')],
    [
        2 => $page('wp next138 retry options after rollback'),
        4 => $page('wp next138 retry transient overflow after rollback'),
    ],
    [1, 2, 3, 4],
    'wp-next138-source',
    4,
    true,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-savepoint-cache-current-source-next138',
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

if ($summary['status'] !== 'pager-master-journal-savepoint-cache-current-source-next138'
    || $summary['cacheStaleRejected'] !== true
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [3]
    || $summary['savepointBeforePrefix'] !== 'wp next138 recovered options current source'
    || $summary['retryBeforePrefix'] !== 'wp next138 recovered options current source'
    || $summary['dirtyPages'] !== [2, 4]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-savepoint-cache-current-source-next138 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-savepoint-cache-current-source-next138 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLitePagerCacheSpillSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next141.sqlite';
$masterPath = '/srv/wp-content/database/wp-next141.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next141 stale schema before recovery'),
    2 => $page('wp next141 stale active_plugins before recovery'),
    3 => $page('wp next141 stale plugin settings before recovery'),
    4 => $page('wp next141 stale transient cache before recovery'),
];
$recovered = [
    1 => $page('wp next141 recovered schema current source'),
    2 => $page('wp next141 recovered active_plugins current source'),
    3 => $page('wp next141 recovered plugin settings current source'),
    4 => $page('wp next141 recovered transient cache current source'),
];

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-copy');
$savepoints->recordPageImageWrite(1, $recovered[1]);
$savepoints->savepoint('plugin-batch');
$savepoints->recordPageImageWrite(2, $recovered[2]);
$savepoints->recordPageImageWrite(3, $recovered[3]);

$plan = SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/old/site.sqlite-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/site-next141.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    'plugin-batch',
    $savepoints,
    $recovered,
    [
        2 => ['image' => $page('wp next141 dirty active_plugins spill'), 'source_id' => 'wp-next141-source', 'epoch' => 3, 'source' => 'dirty-active-plugins', 'journaled' => true],
        3 => ['image' => $page('wp next141 dirty plugin settings spill'), 'source_id' => 'wp-next141-source', 'epoch' => 3, 'source' => 'dirty-plugin-settings', 'journaled' => true],
        4 => ['image' => $page('wp next141 dirty stale transient spill'), 'source_id' => 'old-wp-next141-source', 'epoch' => 3, 'source' => 'stale-transient-cache', 'journaled' => true],
    ],
    [1, 2, 3, 4],
    'wp-next141-source',
    3,
    6,
    3,
    'wal',
    true,
    'shared',
);

$summary = [
    'scenario' => 'wordpress-pager-cache-spill-savepoint-master-current-source-next141',
    'status' => $plan['status'],
    'eligiblePages' => $plan['eligible_page_numbers'],
    'masterRejectedPages' => $plan['master_rejected_page_numbers'],
    'walFramePages' => $plan['wal_frame_pages'],
    'currentSourceEpoch' => $plan['current_source']['epoch'],
    'nextSourceEpoch' => $plan['next_source']['epoch'],
    'wordpressUse' => 'A copied WordPress SQLite options import recovers through the current master journal, rejects stale pager-cache spill candidates, and only spills savepoint-protected dirty pages whose source still matches the recovered current master-journal source.',
    'dependencyClosure' => 'no new support component needed; this composes native PHP master-journal hot-cache rebasing with savepoint before-image cache-spill admission',
];

if (
    $summary['status'] !== 'pager-cache-spill-savepoint-master-current-source-next141'
    || $summary['eligiblePages'] !== [2, 3]
    || $summary['masterRejectedPages'] !== [4]
    || $summary['walFramePages'] !== [2, 3]
    || $summary['nextSourceEpoch'] !== 4
) {
    fwrite(STDERR, "wordpress-pager-cache-spill-savepoint-master-current-source-next141 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-cache-spill-savepoint-master-current-source-next141 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";

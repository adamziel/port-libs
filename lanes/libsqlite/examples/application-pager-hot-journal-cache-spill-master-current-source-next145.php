<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next145.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp-content/database/wp-next145.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$beforeOptions = $page('wp next145 before active_plugins dirty crash');
$beforeAutoload = $page('wp next145 before autoload index dirty crash');
$beforeTransient = $page('wp next145 before transient cache dirty crash');
$databaseBytes = $beforeOptions . $beforeAutoload . $beforeTransient;

$hotOptions = $page('wp next145 recovered active_plugins master source');
$hotAutoload = $page('wp next145 recovered autoload index master source');
$hotTransient = $page('wp next145 recovered transient cache master source');

$plan = SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan::plan(
    $databasePath,
    $journalPath,
    $masterPath,
    $journalPath . "\n/srv/wp-content/database/site-next145.sqlite-journal\n",
    $databaseBytes,
    $pageSize,
    [
        1 => $hotOptions,
        2 => $hotAutoload,
        3 => $hotTransient,
    ],
    [
        1 => ['image' => $page('wp next145 dirty active_plugins cache after recovery'), 'current_image' => $hotOptions, 'source_id' => 'wp-next145-master-source', 'epoch' => 2, 'journaled' => true, 'walFrame' => 21],
        2 => ['image' => $page('wp next145 dirty autoload cache stale source'), 'current_image' => $beforeAutoload, 'source_id' => 'wp-next145-master-source', 'epoch' => 2, 'journaled' => true, 'walFrame' => 22],
        3 => ['image' => $page('wp next145 dirty transient cache pinned reader'), 'current_image' => $hotTransient, 'source_id' => 'wp-next145-master-source', 'epoch' => 2, 'journaled' => true, 'pinned' => true, 'walFrame' => 23],
    ],
    5,
    3,
    'wal',
    true,
    'shared',
    true,
    null,
    'wp-next145-master-source',
    2,
);

if (
    $plan['status'] !== 'pager_hot_journal_cache_spill_master_current_source_next145'
    || $plan['admitted_page_numbers'] !== [1]
    || $plan['rejected_pages'][2] !== ['current_source_mismatch_after_hot_recovery']
    || $plan['rejected_pages'][3] !== ['cache_page_pinned']
    || $plan['wal_frame_pages'] !== [1]
) {
    fwrite(STDERR, "application-pager-hot-journal-cache-spill-master-current-source-next145 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "application-pager-hot-journal-cache-spill-master-current-source-next145 self-test passed\n";
}

return [
    'scenario' => 'application-pager-hot-journal-cache-spill-master-current-source-next145',
    'applicationUse' => 'After copied wp_options recovery through a master hot journal, dirty pager-cache spill is admitted only for pages whose rollback source and current image match the recovered master current source; stale and pinned cache pages wait for a safer retry.',
    'status' => $plan['status'],
    'journalMode' => $plan['journal_mode'],
    'admittedPages' => $plan['admitted_page_numbers'],
    'rejectedPages' => $plan['rejected_pages'],
    'walFramePages' => $plan['wal_frame_pages'],
    'dependencyClosure' => 'no new support component needed; this composes native PHP master-journal current-source validation with the existing pager cache-spill journal-mode planner',
];

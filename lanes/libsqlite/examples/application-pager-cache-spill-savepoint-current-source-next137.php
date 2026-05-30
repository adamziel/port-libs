<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLitePagerCacheSpillSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$beforeSchema = $page('wp next137 before schema root');
$beforeOptions = $page('wp next137 before active_plugins option');
$beforeTheme = $page('wp next137 before theme_mods option');
$beforeTransient = $page('wp next137 before transient cache');
$databaseBytes = $beforeSchema . $beforeOptions . $beforeTheme . $beforeTransient;

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-copy');
$savepoints->recordPageImageWrite(1, $beforeSchema);
$savepoints->savepoint('plugin-batch');
$savepoints->recordPageImageWrite(2, $beforeOptions);
$savepoints->recordPageImageWrite(3, $beforeTheme);

$plan = SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
    $databaseBytes,
    $pageSize,
    'plugin-batch',
    $savepoints,
    [
        ['page' => 2, 'image' => $page('wp next137 dirty active_plugins cache'), 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 5],
        ['page' => 3, 'image' => $page('wp next137 dirty theme_mods cache'), 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 6],
        ['page' => 4, 'image' => $page('wp next137 stale transient cache'), 'current_image' => $page('wp next137 stale before recovery'), 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 7],
    ],
    6,
    3,
    'wal',
    true,
    'shared'
);

if (
    $plan['status'] !== 'pager_cache_spill_savepoint_current_source_next137'
    || $plan['admitted_page_numbers'] !== [2, 3]
    || $plan['rejected_pages'][4] !== ['current_source_mismatch', 'missing_savepoint_before_image']
    || $plan['wal_frame_pages'] !== [2, 3]
) {
    fwrite(STDERR, "application-pager-cache-spill-savepoint-current-source-next137 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "application-pager-cache-spill-savepoint-current-source-next137 self-test passed\n";
}

return [
    'scenario' => 'application-pager-cache-spill-savepoint-current-source-next137',
    'applicationUse' => 'During a copied wp_options import, dirty cache pages may spill while a plugin-batch savepoint is open only when their current source still matches the database image and the savepoint has a before-image that can undo the spill.',
    'status' => $plan['status'],
    'journalMode' => $plan['journal_mode'],
    'admittedPages' => $plan['admitted_page_numbers'],
    'rejectedPages' => $plan['rejected_pages'],
    'walFramePages' => $plan['wal_frame_pages'],
    'dependencyClosure' => 'no new support component needed; this composes native PHP savepoint page-image tracking with the existing pager cache-spill journal-mode planner',
];

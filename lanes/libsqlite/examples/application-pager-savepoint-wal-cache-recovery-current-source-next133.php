<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$baseActivePlugins = $page('wp_options active_plugins before failed WAL import');
$basePluginSettings = $page('wp_options plugin settings before failed WAL import');
$baseAutoload = $page('wp_options autoload index before failed WAL import');
$databaseBytes = $page('application sqlite header before failed WAL import') . $baseActivePlugins . $basePluginSettings . $baseAutoload;

$walFrames = [
    1 => ['page' => 1, 'image' => $page('retained WAL schema cookie frame'), 'commit_frame' => true],
    2 => ['page' => 2, 'image' => $page('discarded active_plugins import frame')],
    3 => ['page' => 3, 'image' => $page('discarded plugin settings import frame')],
];

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-options-import');
$savepoints->recordWalFrameWrite(1, 1, true);
$savepoints->savepoint('plugin-options');
$savepoints->recordWalFrameWrite(2, 2);
$savepoints->recordWalFrameWrite(3, 3);

$plan = SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan::plan(
    $databaseBytes,
    $pageSize,
    'plugin-options',
    $savepoints,
    [
        1 => ['image' => $walFrames[1]['image'], 'frame' => 1, 'source' => 'wal-cache-retained-schema'],
        2 => ['image' => $walFrames[2]['image'], 'frame' => 2, 'source' => 'wal-cache-failed-active_plugins-import', 'dirty' => true],
        3 => ['image' => $walFrames[3]['image'], 'frame' => 3, 'source' => 'wal-cache-failed-plugin-settings-import', 'dirty' => true],
        4 => ['image' => $baseAutoload, 'frame' => 0, 'source' => 'database-cache-autoload-index'],
    ],
    $walFrames,
    [1, 2, 3, 4],
);

$summary = [
    'scenario' => 'application-pager-savepoint-wal-cache-recovery-current-source-next133',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'rollbackToFrame' => $plan['rollback_to_frame'],
    'staleCachePages' => $plan['stale_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'readPrefixes' => array_column($plan['read_rows'], 'prefix', 'page_number'),
    'applicationUse' => 'A failed plugin option import in WAL mode may leave pager-cache entries sourced from WAL frames that ROLLBACK TO discards. This smoke verifies later reads refresh those cache pages from the retained WAL prefix or base database image before Application retries the import.',
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$beforeSchema = $page('wp_options schema before plugin import');
$beforeActivePlugins = $page('wp_options active_plugins before failed import');
$beforePluginSettings = $page('wp_options plugin settings before failed import');
$beforeTransient = $page('wp_options transient cache before failed import');
$databaseBytes = $beforeSchema . $beforeActivePlugins . $beforePluginSettings . $beforeTransient;

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-option-import');
$savepoints->recordPageImageWrite(1, $beforeSchema);
$savepoints->savepoint('plugin-settings');
$savepoints->recordPageImageWrite(2, $beforeActivePlugins);
$savepoints->recordPageImageWrite(3, $beforePluginSettings);
$savepoints->recordPageImageWrite(4, $beforeTransient);

$plan = SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext(
    $databaseBytes,
    $pageSize,
    'plugin-settings',
    $savepoints,
    [
        ['page' => 2, 'image' => $page('spilled active_plugins dirty cache page'), 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 3, 'image' => $page('spilled plugin settings dirty cache page'), 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 4, 'image' => $page('spilled transient cache dirty cache page'), 'bytes' => $pageSize, 'journaled' => true],
    ],
    7,
    3,
    true,
    'reserved',
    true,
    3
);

$summary = [
    'scenario' => 'application-pager-cache-spill-savepoint-recovery-current-source-next120',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'spilledPages' => $plan['spilled_page_numbers'],
    'restoredSpilledPages' => $plan['restored_spilled_page_numbers'],
    'spillSurvivedPages' => $plan['spill_survived_page_numbers'],
    'page2AfterRollback' => $plan['page_sources'][2]['rolled_back_prefix'],
    'page3AfterRollback' => $plan['page_sources'][3]['rolled_back_prefix'],
    'applicationUse' => 'During a copied wp_options import, dirty cache pages may spill to the database before a later statement fails. This smoke proves native PHP pager recovery can model ROLLBACK TO restoring the original savepoint page images instead of preserving spilled dirty bytes.',
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;

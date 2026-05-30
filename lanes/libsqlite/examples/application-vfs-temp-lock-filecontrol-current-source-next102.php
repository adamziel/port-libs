<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsTempLockingFileControlCurrentSourcePlan;

$plan = SQLiteVfsTempLockingFileControlCurrentSourcePlan::planTempLockDataVersionFileControl(
    [
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'lock', 'handle' => 'temp-wp-import-102-1', 'value' => 'reserved'],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-1', 'control' => 'chunk_size', 'value' => 4096],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-2', 'control' => 'data_version'],
        ['op' => 'lock', 'handle' => 'temp-wp-import-102-2', 'value' => 'exclusive'],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-2', 'control' => 'size_hint', 'value' => 8192],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-1', 'control' => 'data_version'],
        ['op' => 'close', 'handle' => 'temp-wp-import-102-1'],
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-102-3', 'control' => 'data_version'],
    ],
    [
        'temp_dir' => '/tmp/wp-cache',
        'connection_id' => 'wp import 102',
    ],
);

$summary = [
    'scenario' => 'application-vfs-temp-lock-data-version-filecontrol',
    'applicationUse' => 'Preview copied wp_options temp statement-journal handles where xFileControl writes bump temp data_version only after a write lock, stale sibling handles detect the newer current source, and close/reopen starts from the latest persisted temp state without ext/sqlite.',
    'status' => $plan['status'],
    'staleReader' => [
        'value' => $plan['events'][4]['value'],
        'openedGeneration' => $plan['events'][4]['opened_generation'],
        'stale' => $plan['events'][4]['stale_current_source'],
    ],
    'staleWriterAfterSiblingWrite' => [
        'value' => $plan['events'][7]['value'],
        'openedGeneration' => $plan['events'][7]['opened_generation'],
        'stale' => $plan['events'][7]['stale_current_source'],
    ],
    'reopenedTempDataVersion' => $plan['events'][10]['value'],
    'persistentGenerationCount' => $plan['next']['persistent_generation_count'],
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'ok');
    assert($summary['staleReader']['value'] === 2);
    assert($summary['staleReader']['openedGeneration'] === 1);
    assert($summary['staleReader']['stale'] === true);
    assert($summary['staleWriterAfterSiblingWrite']['value'] === 3);
    assert($summary['staleWriterAfterSiblingWrite']['stale'] === true);
    assert($summary['reopenedTempDataVersion'] === 3);
    assert($summary['persistentGenerationCount'] === 1);
    echo "application-vfs-temp-lock-data-version-filecontrol self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

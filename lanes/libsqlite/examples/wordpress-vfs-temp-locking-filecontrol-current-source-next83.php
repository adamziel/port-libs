<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsTempLockingFileControlCurrentSourcePlan;

$plan = SQLiteVfsTempLockingFileControlCurrentSourcePlan::planTempLockingFileControl(
    [
        ['op' => 'open', 'source' => 'main', 'suffix' => 'db', 'delete_on_close' => false],
        ['op' => 'filecontrol', 'source' => 'main', 'control' => 'name_hint', 'value' => 'main wp_options'],
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => 'temp wp_options import'],
        ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 4096],
        ['op' => 'lock', 'value' => 'reserved'],
        ['op' => 'filecontrol', 'source' => 'main', 'control' => 'chunk_size', 'value' => 8192],
        ['op' => 'close', 'source' => 'temp'],
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
    ],
    [
        'temp_dir' => '/tmp/wp-cache',
        'connection_id' => 'wp import 83',
    ],
);

$summary = [
    'scenario' => 'wordpress-vfs-temp-locking-filecontrol',
    'wordpressUse' => 'Route copied wp_options temp statement-journal locks and xFileControl calls through SQLite current-source rules: unqualified controls follow temp after a temp open, schema-qualified main controls still reach the main handle, and non-delete temp handles reuse persisted file-control state after close/reopen without ext/sqlite.',
    'status' => $plan['status'],
    'currentSource' => $plan['next']['current_source'],
    'openBySource' => $plan['next']['open_by_source'],
    'lockedBySource' => $plan['next']['locked_by_source'],
    'persistentControlCount' => $plan['next']['persistent_control_count'],
    'persistentLockCount' => $plan['next']['persistent_lock_count'],
    'tempHandleControls' => $plan['current']['handles']['temp-wp-import-83-3']['controls'] ?? [],
    'mainHandleControls' => $plan['current']['handles']['temp-wp-import-83-1']['controls'] ?? [],
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'temp-open');
    assert($summary['currentSource'] === 'temp');
    assert($summary['openBySource']['main'] === 1);
    assert($summary['openBySource']['temp'] === 1);
    assert($summary['tempHandleControls']['name_hint'] === 'temp wp_options import');
    assert($summary['tempHandleControls']['chunk_size'] === 4096);
    assert($summary['mainHandleControls']['chunk_size'] === 8192);
    echo "wordpress-vfs-temp-locking-filecontrol self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

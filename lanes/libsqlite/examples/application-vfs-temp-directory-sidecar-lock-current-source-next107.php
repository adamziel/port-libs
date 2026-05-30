<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan;

$plan = SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan::planTempDirectorySidecarLock(
    [
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'lock', 'source' => 'temp', 'value' => 'reserved'],
        ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'chunk_size', 'value' => 4096],
        ['op' => 'temp_directory', 'path' => '/tmp/wp-import-next'],
        ['op' => 'open', 'source' => 'temp', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'lock', 'source' => 'temp', 'value' => 'exclusive'],
        ['op' => 'filecontrol', 'source' => 'temp', 'control' => 'size_hint', 'value' => 8192],
        ['op' => 'filecontrol', 'handle' => 'temp-wp-import-107-1', 'control' => 'lock_timeout', 'value' => 50],
        ['op' => 'close', 'handle' => 'temp-wp-import-107-1'],
        ['op' => 'close', 'handle' => 'temp-wp-import-107-2'],
    ],
    [
        'temp_dir' => '/tmp/wp-import-current',
        'connection_id' => 'wp import 107',
    ],
);

$summary = [
    'scenario' => 'application-vfs-temp-directory-sidecar-lock',
    'applicationUse' => 'Preview copied wp_options temp statement-journal handles when PRAGMA temp_store_directory or a VFS temp directory handoff moves the next temp file into a new directory. Current handles keep the old sidecar lock namespace, while next handles use a distinct sidecar key and do not inherit stale locks or xFileControl state.',
    'status' => $plan['status'],
    'currentDirectory' => $plan['events'][0]['current']['temp_dir'],
    'nextDirectory' => $plan['next']['temp_dir'],
    'oldSidecarKey' => $plan['events'][0]['sidecar_key'],
    'newSidecarKey' => $plan['events'][4]['sidecar_key'],
    'oldSidecarAfterMove' => $plan['events'][5]['next']['sidecar_locks'][$plan['events'][0]['sidecar_key']],
    'newSidecarAfterMove' => $plan['events'][5]['next']['sidecar_locks'][$plan['events'][4]['sidecar_key']],
    'oldControls' => $plan['events'][7]['next']['sidecar_controls'][$plan['events'][0]['sidecar_key']],
    'newControls' => $plan['events'][7]['next']['sidecar_controls'][$plan['events'][4]['sidecar_key']],
    'finalOpenCount' => $plan['next']['open_count'],
    'finalLockedSidecars' => $plan['next']['locked_sidecar_count'],
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'closed');
    assert($summary['currentDirectory'] === '/tmp/wp-import-current');
    assert($summary['nextDirectory'] === '/tmp/wp-import-next');
    assert($summary['oldSidecarKey'] !== $summary['newSidecarKey']);
    assert($summary['oldSidecarAfterMove'] === 'reserved');
    assert($summary['newSidecarAfterMove'] === 'exclusive');
    assert($summary['oldControls']['chunk_size'] === 4096);
    assert($summary['oldControls']['lock_timeout'] === 50);
    assert($summary['newControls']['size_hint'] === 8192);
    assert($summary['finalOpenCount'] === 0);
    assert($summary['finalLockedSidecars'] === 0);
    echo "application-vfs-temp-directory-sidecar-lock self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$profile = SQLiteVfsIoDynamicPlan::win32NoLockProfile('win32nolock-1.7');

if (($argv[1] ?? null) === '--self-test') {
    assert($profile['status'] === 'ok');
    assert($profile['script'] === 'win32nolock.test');
    assert($profile['primary_vfs'] === 'win32-none');
    assert($profile['peer_vfs'] === 'win32-none');
    assert($profile['observed_rows'] === [[1, 2], [3, 4]]);
    assert($profile['memory_release_required_for_peer_refresh'] === true);
    assert(in_array('sqlite-vfs-win32-none-no-lock', $profile['dependencies'], true));

    echo "application-vfs-win32-nolock self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-vfs-win32-nolock',
    'applicationUse' => 'Model a Windows no-lock VFS handle pair where stale peer cache state survives commit until memory pressure refreshes it.',
    'source' => $profile['script'],
    'upstream' => $profile['upstream'],
    'status' => $profile['status'],
    'primaryVfs' => $profile['primary_vfs'],
    'peerVfs' => $profile['peer_vfs'],
    'observedPhase' => $profile['observed_phase'],
    'observedRows' => $profile['observed_rows'],
    'dependencies' => $profile['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

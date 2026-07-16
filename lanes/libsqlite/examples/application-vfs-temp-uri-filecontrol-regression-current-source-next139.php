<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::planTempUriFileControlRegression([
    ['op' => 'open', 'source' => 'temp', 'filename' => 'file:/tmp/wp%20import%20scratch.sqlite?mode=rw&cache=private&role=sorter&checkpoint=on&busy=150'],
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => false]],
    'file_control(chunk_size, 8192)',
    ['op' => 'open', 'source' => 'main', 'filename' => 'file:/srv/www/wp-content/database/wp%20live.sqlite?mode=rw&cache=shared&role=main'],
    'lock reserved wp-import 2 on main',
    'file_control(persist_wal, on)',
]);

$payload = [
    'scenario' => 'application-vfs-temp-uri-filecontrol-regression-current-source-next139',
    'applicationUse' => 'Keep Application import scratch/temp SQLite URI file-controls handle-local while the copied live database persists WAL controls through the normal current-source owner.',
    'status' => $plan['status'],
    'tempOwner' => $plan['events'][0]['owner'],
    'tempUriRole' => $plan['events'][1]['value'],
    'tempCheckpoint' => $plan['events'][2]['value'],
    'tempChunkRoute' => $plan['events'][3]['routed_to'],
    'tempChunkSize' => $plan['events'][3]['next']['handles']['vfs97-1']['controls']['chunk_size'],
    'mainPersistWalRoute' => $plan['events'][6]['routed_to'],
    'mainPersistWal' => $plan['events'][6]['next']['owners']['/srv/www/wp-content/database/wp live.sqlite']['controls']['persist_wal'],
    'persistentControlOwners' => $plan['next']['persistent_control_count'],
    'dependencies' => $plan['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($payload['status'] === 'ok');
    assert($payload['tempOwner'] === 'temp:vfs97-1:/tmp/wp import scratch.sqlite');
    assert($payload['tempUriRole'] === 'sorter');
    assert($payload['tempCheckpoint'] === true);
    assert($payload['tempChunkRoute'] === 'temporary-handle');
    assert($payload['tempChunkSize'] === 8192);
    assert($payload['mainPersistWalRoute'] === 'database');
    assert($payload['mainPersistWal'] === true);
    assert($payload['persistentControlOwners'] === 1);
    assert(in_array('vfs-temp-uri-filecontrol-regression-current-source-next139', $payload['dependencies'], true));
    echo "application-vfs-temp-uri-filecontrol-regression-current-source-next139 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

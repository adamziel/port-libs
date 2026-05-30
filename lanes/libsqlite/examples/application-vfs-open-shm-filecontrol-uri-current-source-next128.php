<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteBusyHandler.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::planOpenShmFileControlUri([
    'open(file://localhost/srv/www/wp-content/database/wp%20uri.sqlite-shm?mode=rw&cache=shared&role=reader&readmark=2&checkpoint=on)',
    ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'readmark', 'default' => 0]],
    'open(file:/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&role=writer&busy=2500&checkpoint=off&psow=1)',
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'busy', 'default' => 100]],
    'lock reserved wp-import 17 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'checkpoint', 'default' => false]],
    'file_control(data_version)',
]);

$summary = [
    'scenario' => 'application-vfs-open-shm-filecontrol-uri-current-source-next128',
    'applicationUse' => 'Expose SQLite URI xFileControl probes from copied Application main/WAL/SHM current sources so import and repair code can inspect per-handle role, busy/readmark, and checkpoint hints while preserving byte-lock, SHM-lock, and data-version freshness state without ext/sqlite.',
    'dependency' => 'vfs-open-shm-filecontrol-uri-current-source-next128',
    'shmRole' => $plan['events'][1]['value'],
    'shmReadmark' => $plan['events'][2]['value'],
    'mainBusy' => $plan['events'][4]['value'],
    'persistWalGeneration' => $plan['events'][6]['source_generation'],
    'staleShmCheckpoint' => $plan['events'][8]['value'],
    'staleShmDataVersion' => $plan['events'][9]['stale_current_source'],
    'openBySource' => $plan['next']['open_by_source'],
];

assert($summary['shmRole'] === 'reader');
assert($summary['shmReadmark'] === 2);
assert($summary['mainBusy'] === 2500);
assert($summary['persistWalGeneration'] === 2);
assert($summary['staleShmCheckpoint'] === true);
assert($summary['staleShmDataVersion'] === true);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

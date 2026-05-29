<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteBusyHandler.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::planUriShmFileControlRegression([
    'open(file:/srv/www/wp-content/database/wp%20close.sqlite-shm?mode=rw&cache=shared&role=reader&readmark=1)',
    'shm read0 shared wp-reader',
    ['op' => 'close', 'source' => 'shm', 'connection' => 'wp-reader'],
    'open(file:/srv/www/wp-content/database/wp%20close.sqlite-shm?mode=rw&cache=shared&role=checkpoint&readmark=2)',
    'shm read0 exclusive wp-checkpoint',
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'readmark', 'default' => 0]],
    'open(file:/srv/www/wp-content/database/wp%20close.sqlite?mode=rw&cache=shared&role=writer)',
    'lock reserved wp-import 11 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
]);

$summary = [
    'scenario' => 'wordpress-vfs-uri-shm-filecontrol-regression-current-source-next141',
    'wordpressUse' => 'When a copied WordPress SQLite connection closes its SHM mapping, release that connection read lock before checkpoint/import code reopens the sidecar and probes URI/file-control data-version state.',
    'dependency' => 'vfs-uri-shm-filecontrol-regression-current-source-next141',
    'closeReleasedShmLocks' => $plan['events'][2]['released_shm_locks'],
    'checkpointLockStatus' => $plan['events'][4]['status'],
    'checkpointReadmark' => $plan['events'][5]['value'],
    'staleBeforeRefresh' => $plan['events'][10]['stale_current_source'],
    'freshAfterRefresh' => $plan['events'][11]['stale_current_source'] === false,
    'openBySource' => $plan['next']['open_by_source'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['closeReleasedShmLocks'] !== true
        || $summary['checkpointLockStatus'] !== 'acquired'
        || $summary['checkpointReadmark'] !== 2
        || $summary['staleBeforeRefresh'] !== true
        || $summary['freshAfterRefresh'] !== true
        || $summary['openBySource'] !== ['main' => 1, 'wal' => 0, 'shm' => 1]
    ) {
        fwrite(STDERR, "wordpress-vfs-uri-shm-filecontrol-regression-current-source-next141 self-test failed\n");
        exit(1);
    }

    echo "wordpress-vfs-uri-shm-filecontrol-regression-current-source-next141 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmOpenFileControlCurrentSourcePlan;

$plan = SQLiteVfsShmOpenFileControlCurrentSourcePlan::planShmOpenFileControl([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-shm?mode=rw&cache=shared)',
    'file_control(persist_wal, on)',
    'file_control(chunk_size, 16384)',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private)',
    'source(main)',
    'file_control(mmap_size, 262144)',
    'close(shm)',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite-wal?mode=rw&cache=private)',
    'file_control(reserve_bytes, 32)',
]);

$owner = '/srv/www/wp-content/database/wp copy.sqlite';
$summary = [
    'scenario' => 'application-vfs-shm-filecontrol-open-current-source-next91',
    'status' => $plan['status'],
    'firstOpenSource' => $plan['events'][0]['source'],
    'firstOpenOwner' => $plan['events'][0]['owner'],
    'sidecarOpenFirst' => $plan['events'][0]['sidecar_open_first'],
    'persistWal' => $plan['next']['persistent_controls'][$owner]['persist_wal'] ?? null,
    'chunkSize' => $plan['next']['persistent_controls'][$owner]['chunk_size'] ?? null,
    'mmapSize' => $plan['next']['persistent_controls'][$owner]['mmap_size'] ?? null,
    'reserveBytes' => $plan['next']['persistent_controls'][$owner]['reserve_bytes'] ?? null,
    'openBySource' => $plan['next']['open_by_source'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'ok');
    assert($summary['firstOpenSource'] === 'shm');
    assert($summary['firstOpenOwner'] === $owner);
    assert($summary['sidecarOpenFirst'] === true);
    assert($summary['persistWal'] === true);
    assert($summary['chunkSize'] === 16384);
    assert($summary['mmapSize'] === 262144);
    assert($summary['reserveBytes'] === 32);
    assert($summary['openBySource'] === ['main' => 1, 'wal' => 1, 'shm' => 0]);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

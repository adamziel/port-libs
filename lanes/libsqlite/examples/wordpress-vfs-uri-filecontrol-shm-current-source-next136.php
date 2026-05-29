<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext136([
    'open(file:/srv/www/wp-content/database/wp%20136.sqlite-shm?mode=rw&cache=shared&role=reader&readmark=3&checkpoint=on)',
    'shm read0 shared wp-reader',
    'shm write exclusive wp-import',
    'file_control(uri_parameter, role) on shm',
    'open(file:/srv/www/wp-content/database/wp%20136.sqlite?mode=rw&cache=shared&role=writer&busy=200&checkpoint=off&psow=1)',
    'file_control(uri_parameter, role) on main',
    'lock reserved wp-import 8 on main',
    'file_control(persist_wal, on) on main',
    'source(shm)',
    'file_control(data_version)',
    'close(shm)',
    'open(file:/srv/www/wp-content/database/wp%20136.sqlite-shm?mode=rw&cache=shared&role=reopened&readmark=4&checkpoint=no)',
    'file_control(uri_boolean, checkpoint) on shm',
]);

$owner = '/srv/www/wp-content/database/wp 136.sqlite';
$summary = [
    'scenario' => 'wordpress-vfs-uri-filecontrol-shm-current-source-next136',
    'wordpressUse' => 'During a copied WordPress SQLite import, route URI xFileControl probes to the selected main or SHM handle, detect stale SHM current-source data-version after persist-WAL changes, and release SHM xLock ownership when the SHM handle closes without dropping the main database byte lock.',
    'dependency' => 'vfs-uri-filecontrol-shm-current-source-next136',
    'shmRole' => $plan['events'][3]['value'],
    'mainRole' => $plan['events'][5]['value'],
    'persistWalGeneration' => $plan['events'][7]['source_generation'],
    'staleShmDataVersion' => $plan['events'][9]['stale_current_source'],
    'releasedShmLocks' => $plan['events'][10]['released_shm_locks'],
    'checkpointAfterReopen' => $plan['events'][12]['value'],
    'byteLocksAfterClose' => $plan['next']['owners'][$owner]['holders'],
    'shmLockCountAfterReopen' => $plan['next']['shm_lock_count'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['shmRole'] === 'reader');
    assert($summary['mainRole'] === 'writer');
    assert($summary['persistWalGeneration'] === 2);
    assert($summary['staleShmDataVersion'] === true);
    assert($summary['releasedShmLocks'] === ['read0:wp-reader', 'write:wp-import']);
    assert($summary['checkpointAfterReopen'] === false);
    assert($summary['byteLocksAfterClose'] === ['wp-import' => 'reserved']);
    assert($summary['shmLockCountAfterReopen'] === 0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

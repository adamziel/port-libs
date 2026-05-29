<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::plan([
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 shared wp-reader',
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared)',
    'lock shared wp-reader 11 on main',
    'source(shm)',
    'shm write exclusive wp-import',
    'source(main)',
    'lock reserved wp-import 22',
    'open(file:/srv/www/wp-content/database/wp%20copy.sqlite-wal?mode=rw&cache=private)',
    'shm checkpoint exclusive wp-import',
    'source(main)',
    'lock exclusive wp-import 22',
    'yield wp-reader',
    'lock exclusive wp-import 22',
]);

$owner = '/srv/www/wp-content/database/wp copy.sqlite';
$summary = [
    'scenario' => 'wordpress-vfs-lock-byte-uri-shm-current-source-next97',
    'status' => $plan['status'],
    'firstOpenSource' => $plan['events'][0]['source'],
    'owner' => $plan['events'][0]['owner'],
    'sidecarOpenFirst' => $plan['events'][0]['sidecar_open_first'],
    'exclusiveBeforeYield' => $plan['events'][11]['status'],
    'exclusiveAfterYield' => $plan['events'][13]['status'],
    'finalCurrentSource' => $plan['next']['current_source'],
    'openBySource' => $plan['next']['open_by_source'],
    'holders' => $plan['next']['owners'][$owner]['holders'],
    'shmLocks' => [
        'write' => $plan['next']['owners'][$owner]['shm_locks']['write'],
        'checkpoint' => $plan['next']['owners'][$owner]['shm_locks']['checkpoint'],
    ],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'planned');
    assert($summary['firstOpenSource'] === 'shm');
    assert($summary['owner'] === $owner);
    assert($summary['sidecarOpenFirst'] === true);
    assert($summary['exclusiveBeforeYield'] === 'blocked');
    assert($summary['exclusiveAfterYield'] === 'planned');
    assert($summary['finalCurrentSource'] === 'main');
    assert($summary['openBySource'] === ['main' => 1, 'wal' => 1, 'shm' => 1]);
    assert($summary['holders'] === ['wp-import' => 'exclusive']);
    assert($summary['shmLocks']['write'] === ['wp-import' => 'exclusive']);
    assert($summary['shmLocks']['checkpoint'] === ['wp-import' => 'exclusive']);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

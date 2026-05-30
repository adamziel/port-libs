<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::planShmLockByteUriFileControl([
    'open(file://localhost/srv/www/wp-content/database/wp%20refresh.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 shared wp-reader',
    'open(file:/srv/www/wp-content/database/wp%20refresh.sqlite?mode=rw&cache=shared)',
    'lock reserved wp-import 19 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
    'open(file:/srv/www/wp-content/database/wp%20refresh.sqlite-wal?mode=rw&cache=shared)',
    'source(main)',
    'file_control(reserve_bytes, 24)',
    'source(wal)',
    'file_control(data_version)',
    'file_control(data_version, refresh)',
]);

$owner = '/srv/www/wp-content/database/wp refresh.sqlite';
$summary = [
    'scenario' => 'application-vfs-shm-lockbyte-uri-filecontrol-current-source-next117',
    'status' => $plan['status'],
    'owner' => $owner,
    'shmStaleAfterPersistWal' => $plan['events'][6]['stale_current_source'],
    'shmRefreshChanged' => $plan['events'][7]['changed'],
    'shmRefreshGeneration' => $plan['events'][7]['opened_generation'],
    'walStaleAfterReserveBytes' => $plan['events'][12]['stale_current_source'],
    'walRefreshChanged' => $plan['events'][13]['changed'],
    'walRefreshGeneration' => $plan['events'][13]['opened_generation'],
    'controls' => $plan['next']['owners'][$owner]['controls'],
    'dependencies' => $plan['dependencies'],
];

assert($summary['status'] === 'ok');
assert($summary['shmStaleAfterPersistWal'] === true);
assert($summary['shmRefreshChanged'] === true);
assert($summary['shmRefreshGeneration'] === 2);
assert($summary['walStaleAfterReserveBytes'] === true);
assert($summary['walRefreshChanged'] === true);
assert($summary['walRefreshGeneration'] === 3);
assert($summary['controls']['persist_wal'] === true);
assert($summary['controls']['reserve_bytes'] === 24);
assert(in_array('vfs-shm-lockbyte-uri-filecontrol-current-source-next117', $summary['dependencies'], true));

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

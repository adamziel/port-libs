<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::planShmLockByteFileControl([
    'open(file:/srv/www/wp-content/database/wp%20options.sqlite-shm?mode=rw&cache=shared)',
    'shm read0 shared wp-reader',
    'open(file:/srv/www/wp-content/database/wp%20options.sqlite?mode=rw&cache=shared)',
    'file_control(persist_wal, on)',
    'lock reserved wp-import 17 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
    'source(main)',
    'file_control(reserve_bytes, 32)',
]);

$owner = '/srv/www/wp-content/database/wp options.sqlite';
$summary = [
    'scenario' => 'application-vfs-shm-lockbyte-filecontrol-current-source-next112',
    'status' => $plan['status'],
    'blockedBeforeReserved' => $plan['events'][3]['status'],
    'persistWalStored' => $plan['events'][5]['next']['owners'][$owner]['controls']['persist_wal'],
    'generationAfterPersistWal' => $plan['events'][5]['source_generation'],
    'staleShmDataVersion' => $plan['events'][7]['stale_current_source'],
    'reserveBytes' => $plan['events'][9]['next']['owners'][$owner]['controls']['reserve_bytes'],
    'dependencies' => $plan['dependencies'],
];

assert($summary['blockedBeforeReserved'] === 'blocked');
assert($summary['persistWalStored'] === true);
assert($summary['generationAfterPersistWal'] === 2);
assert($summary['staleShmDataVersion'] === true);
assert($summary['reserveBytes'] === 32);
assert(in_array('vfs-shm-lockbyte-filecontrol-current-source-next112', $summary['dependencies'], true));

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

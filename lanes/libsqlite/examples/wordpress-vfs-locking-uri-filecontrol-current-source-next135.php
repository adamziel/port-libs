<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockByteUriShmCurrentSourceNext97.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext97;

$plan = SQLiteVfsLockByteUriShmCurrentSourceNext97::currentSourceNext135([
    'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite-shm?mode=rw&cache=shared&vfs=unix-dotfile)',
    'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite?mode=rw&cache=shared&vfs=unix-dotfile)',
    'lock reserved wp-import 19 on main',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(chunk_size, 8192)',
    'file_control(data_version, refresh)',
    'file_control(chunk_size, 8192)',
    'open(file:/srv/www/wp-content/database/wp%20fresh.sqlite-wal?mode=rw&cache=shared&checkpoint_fullfsync=1)',
    'source(main)',
    'file_control(data_version, refresh)',
    'file_control(reserve_bytes, 32)',
    'source(wal)',
    'file_control(powersafe_overwrite, on)',
    'file_control(data_version, refresh)',
    'file_control(powersafe_overwrite, on)',
]);

$owner = '/srv/www/wp-content/database/wp fresh.sqlite';
$summary = [
    'scenario' => 'wordpress-vfs-locking-uri-filecontrol-current-source-next135',
    'status' => $plan['status'],
    'owner' => $owner,
    'staleShmWriteStatus' => $plan['events'][5]['status'],
    'staleShmWriteReason' => $plan['events'][5]['reason'],
    'refreshedShmChunkSize' => $plan['events'][7]['next']['owners'][$owner]['controls']['chunk_size'],
    'mainReserveBytes' => $plan['events'][11]['next']['owners'][$owner]['controls']['reserve_bytes'],
    'staleWalWriteStatus' => $plan['events'][13]['status'],
    'staleWalWriteReason' => $plan['events'][13]['reason'],
    'refreshedWalPowersafeOverwrite' => $plan['events'][15]['next']['owners'][$owner]['controls']['powersafe_overwrite'],
    'generation' => $plan['next']['owners'][$owner]['generation'],
    'dependencies' => $plan['dependencies'],
];

assert($summary['status'] === 'ok');
assert($summary['staleShmWriteStatus'] === 'blocked');
assert($summary['staleShmWriteReason'] === 'stale_current_source_requires_data_version_refresh');
assert($summary['refreshedShmChunkSize'] === 8192);
assert($summary['mainReserveBytes'] === 32);
assert($summary['staleWalWriteStatus'] === 'blocked');
assert($summary['staleWalWriteReason'] === 'stale_current_source_requires_data_version_refresh');
assert($summary['refreshedWalPowersafeOverwrite'] === true);
assert($summary['generation'] === 5);
assert(in_array('vfs-locking-uri-filecontrol-current-source-next135', $summary['dependencies'], true));

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

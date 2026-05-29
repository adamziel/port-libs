<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlWithGeneration([
    'open(shm, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite-shm?mode=rw&cache=shared)',
    'open(main, file:/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared)',
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
    'shm_lock(read0, shared)',
    'open(wal, file:/srv/www/wp-content/database/wp%20cache.sqlite-wal?mode=rw&cache=shared)',
    'file_control(reserve_bytes, 48)',
    'source(main)',
    'file_control(data_version)',
], [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared',
]);

$summary = [
    'scenario' => 'wordpress-vfs-uri-shm-filecontrol-current-source-next104',
    'owner' => $plan['events'][0]['owner'],
    'persistWalGeneration' => $plan['events'][3]['source_generation'],
    'shmStaleAfterPersistWal' => $plan['events'][5]['stale_current_source'],
    'reserveGeneration' => $plan['events'][8]['source_generation'],
    'mainStaleAfterWalReserve' => $plan['events'][10]['stale_current_source'],
    'shmLocks' => $plan['current']['handles']['vfs87-1']['shm_locks'] ?? [],
    'persistentControls' => $plan['current']['persistent_controls']['/srv/www/wp-content/database/wp cache.sqlite'] ?? [],
    'wordpressUse' => 'Track copied WordPress SQLite URI main/WAL/SHM sidecar handles against one current-source generation so xFileControl writes on any sidecar make older handles data_version-stale while SHM byte locks remain isolated on the SHM handle.',
];

if (
    $summary['owner'] !== '/srv/www/wp-content/database/wp cache.sqlite'
    || $summary['persistWalGeneration'] !== 2
    || $summary['shmStaleAfterPersistWal'] !== true
    || $summary['reserveGeneration'] !== 3
    || $summary['mainStaleAfterWalReserve'] !== true
    || ($summary['shmLocks']['read0'] ?? null) !== 'shared'
    || ($summary['persistentControls']['reserve_bytes'] ?? null) !== 48
) {
    fwrite(STDERR, "wordpress-vfs-uri-shm-filecontrol-current-source-next104 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

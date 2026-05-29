<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControl([
    'open(main, file://localhost/srv/www/wp-content/database/site%20cache.sqlite?mode=rw&cache=shared)',
    'file_control(chunk_size, 4096)',
    'open(wal, file://localhost/srv/www/wp-content/database/site%20cache.sqlite-wal?mode=rw&cache=shared)',
    'file_control(mmap_size, 65536)',
    'open(shm, file://localhost/srv/www/wp-content/database/site%20cache.sqlite-shm?mode=rw&cache=shared)',
    'shm_lock(read0, shared)',
    'source(wal)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'shm_lock(write, exclusive)',
]);

$owner = '/srv/www/wp-content/database/site cache.sqlite';
$summary = [
    'scenario' => 'wordpress-vfs-uri-shm-filecontrol-current-source-next92',
    'status' => $plan['status'],
    'owner' => $owner,
    'databaseControls' => $plan['current']['handles']['vfs87-1']['controls'],
    'walOwner' => $plan['events'][2]['owner'],
    'shmOwner' => $plan['events'][4]['owner'],
    'shmLocks' => $plan['current']['handles']['vfs87-3']['shm_locks'],
    'currentSource' => $plan['next']['current_source'],
    'wordpressUse' => 'Canonicalize localhost file: URIs and explicit -wal/-shm sidecar filenames to one decoded database owner so copied wp_options import tools keep xFileControl state on the database handle and SHM byte locks on the SHM handle.',
    'dependencies' => $plan['dependencies'],
];

if (
    ($summary['databaseControls']['mmap_size'] ?? null) !== 65536
    || ($summary['databaseControls']['persist_wal'] ?? null) !== true
    || ($summary['shmLocks']['write'] ?? null) !== 'exclusive'
    || $summary['walOwner'] !== $owner
    || $summary['shmOwner'] !== $owner
) {
    fwrite(STDERR, "wordpress-vfs-uri-shm-filecontrol-current-source-next92 self-test failed\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "wordpress-vfs-uri-shm-filecontrol-current-source-next92 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

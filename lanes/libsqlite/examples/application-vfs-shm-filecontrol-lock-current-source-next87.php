<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmFileControlLock([
    'open(main, file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared)',
    'file_control(chunk_size, 8192)',
    'open(shm, file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared)',
    'file_control(mmap_size, 65536)',
    'shm_lock(read1, shared)',
    'shm_lock(write, exclusive)',
    'source(main)',
    'file_control(persist_wal, on)',
]);

$summary = [
    'scenario' => 'application-vfs-shm-filecontrol-lock-current-source-next87',
    'status' => $plan['status'],
    'databaseControls' => $plan['current']['handles']['vfs87-1']['controls'],
    'shmLocks' => $plan['current']['handles']['vfs87-2']['shm_locks'],
    'currentSource' => $plan['next']['current_source'],
    'applicationUse' => 'Track copied wp_options WAL-index SHM locks separately from database xFileControl state so checkpoint/import code does not misroute mmap/chunk/persist-WAL controls to the -shm sidecar.',
    'dependencies' => $plan['dependencies'],
];

if (($summary['databaseControls']['mmap_size'] ?? null) !== 65536 || ($summary['shmLocks']['write'] ?? null) !== 'exclusive') {
    fwrite(STDERR, "application-vfs-shm-filecontrol-lock-current-source-next87 self-test failed\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "application-vfs-shm-filecontrol-lock-current-source-next87 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression([
    'open(main, file:/srv/www/wp-content/database/bad-source.sqlite?mode=rw&cache=shared)',
    'open(shm, file:/srv/www/wp-content/database/bad-source.sqlite-shm?mode=rw&cache=shared)',
    ['op' => 'shmlock', 'source' => 'shm', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-reader'],
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
]);

$badSourceRejected = false;
try {
    SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression([
        'open(main, file:/srv/www/wp-content/database/bad-source.sqlite?mode=rw&cache=shared)',
        ['op' => 'xShmLock', 'source' => 'temp', 'lock' => 'read0', 'mode' => 'shared'],
    ]);
} catch (InvalidArgumentException) {
    $badSourceRejected = true;
}

$summary = [
    'scenario' => 'application-vfs-shm-bad-source-regression-current-source-next138',
    'status' => $plan['status'],
    'badSourceRejected' => $badSourceRejected,
    'staleCurrentSource' => $plan['events'][6]['stale_current_source'],
    'openedGeneration' => $plan['events'][6]['opened_generation'],
    'currentGeneration' => $plan['events'][6]['value'],
    'shmOwners' => $plan['current']['handles']['vfs87-2']['shm_lock_owners']['read0'] ?? [],
    'applicationUse' => 'Reject malformed VFS/SHM current-source operation arrays before they can create a partial WAL-index state during Application SQLite copy/import preflight.',
    'dependencies' => $plan['dependencies'],
];

if (!$badSourceRejected || $summary['staleCurrentSource'] !== true || $summary['currentGeneration'] !== 2) {
    fwrite(STDERR, "application-vfs-shm-bad-source-regression-current-source-next138 self-test failed\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "application-vfs-shm-bad-source-regression-current-source-next138 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

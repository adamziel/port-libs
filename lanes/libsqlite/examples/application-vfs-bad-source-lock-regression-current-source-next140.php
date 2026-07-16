<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$badCurrent = [
    'persistent_shm_locks' => [
        '/srv/www/wp-content/database/.ht.sqlite' => ['temp' => 'shared'],
    ],
];

$blocked = false;
try {
    SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression([
        'open(main)',
    ], [
        'current' => $badCurrent,
        'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
    ]);
} catch (InvalidArgumentException $e) {
    $blocked = str_contains($e->getMessage(), 'SQLite SHM lock name is unsupported');
}

$valid = SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmBadSourceRegression([
    'open(main)',
    'open(shm)',
    ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-cron'],
], [
    'current' => [
        'persistent_shm_locks' => [
            '/srv/www/wp-content/database/.ht.sqlite' => ['read0' => 'shared'],
        ],
        'persistent_shm_lock_owners' => [
            '/srv/www/wp-content/database/.ht.sqlite' => ['read0' => ['wp-admin']],
        ],
    ],
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);

$summary = [
    'scenario' => 'application-vfs-bad-source-lock-regression-current-source-next140',
    'badSourceBlocked' => $blocked,
    'validHydratedOwners' => $valid['events'][2]['owner_locks']['read0'],
    'validStatus' => $valid['status'],
    'applicationUse' => 'Reject stale or corrupted hydrated SHM source/lock names before a copied wp_options WAL import reuses current-source lock state.',
    'dependencies' => $valid['dependencies'],
];

if (!$blocked || $summary['validHydratedOwners'] !== ['wp-admin', 'wp-cron']) {
    fwrite(STDERR, "application-vfs-bad-source-lock-regression-current-source-next140 self-test failed\n");
    exit(1);
}

if (in_array('--self-test', $argv, true)) {
    echo "application-vfs-bad-source-lock-regression-current-source-next140 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsWalShmLockByteCurrentSourceNext;

$path = '/srv/www/wp-content/database/.ht.sqlite';
$plan = SQLiteVfsWalShmLockByteCurrentSourceNext::plan([
    'selected_path' => $path,
    'sources' => [
        $path => [
            'path' => $path,
            'generation' => 12,
            'holders' => ['wp-reader' => 'shared'],
            'shared_slots' => ['wp-reader' => 5],
            'shm_locks' => ['read1' => ['wp-reader' => 'shared']],
        ],
    ],
], [
    'lock reserved wp-import 9',
    'lock pending wp-import 9',
    'lock exclusive wp-import 9',
    'yield wp-reader',
    'lock exclusive wp-import 9',
    'shm checkpoint exclusive wp-import',
]);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['events'][2]['status'] === 'blocked');
    assert($plan['events'][2]['reason'] === 'main_lock_conflict');
    assert($plan['events'][4]['status'] === 'planned');
    assert($plan['events'][5]['status'] === 'acquired');
    assert($plan['next']['selected']['holders']['wp-import'] === 'exclusive');
    echo "wordpress-vfs-wal-shm-lock-byte-current-source-next89 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-vfs-wal-shm-lock-byte-current-source-next89',
    'database' => $path,
    'blocked_before_reader_yield' => $plan['events'][2]['reason'],
    'exclusive_after_reader_yield' => $plan['events'][4]['status'],
    'checkpoint_shm_lock' => $plan['events'][5]['status'],
    'generation' => $plan['next']['selected']['generation'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";

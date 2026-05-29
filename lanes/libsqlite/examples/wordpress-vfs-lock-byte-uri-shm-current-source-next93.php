<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsLockByteUriShmCurrentSourceNext;

$filename = 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix';
$plan = SQLiteVfsLockByteUriShmCurrentSourceNext::plan([], [
    'main shared wp-reader 12',
    'shm read0 shared wp-reader',
    'main reserved wp-import 21',
    'shm write exclusive wp-import',
    'main pending wp-import 21',
    'main exclusive wp-import 21',
    'release wp-reader',
    'main exclusive wp-import 21',
    'shm checkpoint exclusive wp-import',
], $filename);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['events'][5]['status'] === 'blocked');
    assert($plan['events'][5]['reason'] === 'main_lock_conflict');
    assert($plan['events'][7]['status'] === 'planned');
    assert($plan['events'][8]['status'] === 'acquired');
    assert($plan['next']['selected']['source_key'] === '/srv/www/wp-content/database/wp copy.sqlite');
    echo "wordpress-vfs-lock-byte-uri-shm-current-source-next93 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-vfs-lock-byte-uri-shm-current-source-next93',
    'database_source' => $plan['next']['selected']['source_key'],
    'shm_source' => $plan['next']['selected']['shm_key'],
    'blocked_before_reader_release' => $plan['events'][5]['reason'],
    'exclusive_after_reader_release' => $plan['events'][7]['status'],
    'checkpoint_shm_lock' => $plan['events'][8]['status'],
    'generation' => $plan['next']['selected']['generation'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";

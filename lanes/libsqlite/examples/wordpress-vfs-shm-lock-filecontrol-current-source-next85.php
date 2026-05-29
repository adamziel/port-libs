<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmLockFileControlCurrentSource;

$plan = SQLiteVfsShmLockFileControlCurrentSource::planShmLockFileControl([
    'open',
    'shm_lock(write, exclusive)',
    'file_control(persist_wal, on)',
    'file_control(chunk_size, 8192)',
    'file_control(mmap_size, 65536)',
    'release',
    'close',
    'open',
], [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared',
]);

$summary = [
    'scenario' => 'wordpress-vfs-shm-lock-filecontrol-current-source-next85',
    'status' => $plan['status'],
    'generation' => $plan['next']['generation'],
    'controls' => $plan['next']['controls'],
    'openCount' => $plan['next']['open_count'],
    'lockedCount' => $plan['next']['locked_count'],
    'wordpressUse' => 'Gate copied wp_options WAL-mode xFileControl persistence behind SHM write/checkpoint locks and current-source generations, so stale handles cannot persist controls after another writer advances the WAL-index source without requiring ext/sqlite.',
    'dependencies' => $plan['dependencies'],
];

if (($summary['controls']['persist_wal'] ?? null) !== true || ($summary['controls']['chunk_size'] ?? null) !== 8192) {
    fwrite(STDERR, "wordpress-vfs-shm-lock-filecontrol-current-source-next85 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

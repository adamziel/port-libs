<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$plan = SQLiteVfsOpenLockFileControlCurrentSource::planOpenLockFileControl([
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared)',
    'file_control(chunk_size, 8192)',
    'file_control(mmap_size, 65536)',
    'file_control(persist_wal, on)',
    'lock(reserved)',
    'close',
    'open(file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared)',
]);

$summary = [
    'scenario' => 'wordpress-vfs-open-lock-filecontrol-current-source-next82',
    'status' => $plan['status'],
    'reopenedControls' => $plan['events'][6]['next']['handles']['db-2']['controls'],
    'persistentControlCount' => $plan['next']['persistent_control_count'],
    'persistentLockCount' => $plan['next']['persistent_lock_count'],
    'wordpressUse' => 'Track copied wp_options database open/reopen current-source xFileControl controls and lock handoff before native SQLite writes run without ext/sqlite.',
    'dependencies' => $plan['dependencies'],
];

if (($summary['reopenedControls']['chunk_size'] ?? null) !== 8192 || ($summary['reopenedControls']['persist_wal'] ?? null) !== true) {
    fwrite(STDERR, "wordpress-vfs-open-lock-filecontrol-current-source-next82 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

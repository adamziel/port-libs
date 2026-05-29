<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistence;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$root = sys_get_temp_dir() . '/port-libsqlite-wordpress-vfs-filecontrol-persist75-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Failed to create WordPress SQLite fixture directory');
}
file_put_contents($localDatabase, str_repeat('wp option page', 128));

$persistence = new SQLiteVfsFileControlPersistence($root);
$first = $persistence->persistentFileControlApply(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
    true,
    true,
    [
        ['op' => 'persist_wal', 'value' => true],
        ['op' => 'chunk_size', 'value' => 8192],
        ['op' => 'reserve_bytes', 'value' => 32],
        ['op' => 'mmap_size', 'value' => 262144],
        ['op' => 'name_hint', 'value' => 'wp import current connection'],
        ['op' => 'lock_timeout', 'value' => 2500],
    ],
    'wp-admin'
);
$second = $persistence->persistentFileControlApply(
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
    true,
    true,
    [['op' => 'persist_wal', 'value' => true]],
    'wp-cron'
);

echo json_encode([
    'scenario' => 'wordpress-vfs-filecontrol-persistence-persistent-file-control-apply',
    'wordpressUse' => 'Persist durable SQLite xFileControl settings for a copied wp_options database across close/reopen while releasing process locks and dropping per-connection hints/timeouts.',
    'path' => $databasePath,
    'firstStatus' => $first['status'],
    'firstLock' => $first['lock']['status'],
    'firstRelease' => $first['release']['status'],
    'persisted' => $first['persisted'],
    'secondCurrentChunkSize' => $second['current']['controls']['chunk_size'],
    'secondCurrentReserveBytes' => $second['current']['controls']['reserve_bytes'],
    'secondCurrentMmapSize' => $second['current']['controls']['mmap_size'],
    'secondCurrentNameHint' => $second['current']['controls']['name_hint'],
    'secondCurrentLockTimeout' => $second['current']['controls']['lock_timeout'],
    'sidecarExists' => is_file($first['sidecar']),
    'dependencies' => $first['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

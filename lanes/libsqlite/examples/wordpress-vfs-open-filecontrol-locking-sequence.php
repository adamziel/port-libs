<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsOpenFileControl;

$root = sys_get_temp_dir() . '/port-libsqlite-wordpress-vfs-open-filecontrol-74-' . bin2hex(random_bytes(4));

$plan = SQLiteVfsOpenFileControl::openFileControlSequence(
    [
        'file_control(chunk_size, 4096)',
        'file_control(size_hint, 5000)',
        'lock shared by wp-cli-reader 11',
        'lock reserved by wp-admin-import 19',
        'lock pending by wp-admin-import',
        'release wp-cli-reader',
        'lock exclusive by wp-admin-import',
        'pragma mmap_size=8192',
        'file_control(persist_wal, on)',
        'release wp-admin-import',
    ],
    [
        'root' => $root,
        'filename' => '/srv/www/wp-content/database/.ht.sqlite',
        'file_exists' => true,
        'directory_writable' => true,
        'sector_size' => 4096,
        'device_flags' => ['safe_append', 'powersafe_overwrite'],
        'sync_mode' => 'full',
    ],
);

echo json_encode([
    'wordpressUse' => 'Preflight copied wp_options database open file-controls and lock escalation before an import writes database pages without ext/sqlite.',
    'status' => $plan['status'],
    'preallocatedBytes' => $plan['events'][1]['result']['bytes_preallocated'],
    'exclusiveHeld' => $plan['events'][6]['result']['held'],
    'persistWal' => $plan['next']['controls']['persist_wal'],
    'mmapSize' => $plan['next']['controls']['mmap_size'],
    'holdersAfterRelease' => $plan['next']['holders'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

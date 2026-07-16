<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$plan = SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence([
    'file_control(persist_wal, on)',
    'PRAGMA reserve_bytes=16',
    'PRAGMA mmap_size=65536',
    "file_control(name_hint, 'wp import')",
    ['op' => 'write_hint', 'value' => 24576],
    'close',
    'reopen',
], [
    'filename' => 'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix',
    'file_exists' => true,
    'directory_writable' => true,
]);

echo json_encode([
    'status' => $plan['status'],
    'events' => $plan['count'],
    'persistWal' => $plan['next']['handle']['persist_wal'],
    'reserveBytes' => $plan['next']['handle']['reserve_bytes'],
    'mmapAfterReopen' => $plan['next']['handle']['mmap_size'],
    'writeHintAfterReopen' => $plan['next']['handle']['write_hint_bytes'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
